<?php

namespace App\Http\Requests;

use App\Enums\TipoTransaccion;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Transaccion;
use App\Services\TasaCambioService;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransaccionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Transaccion::class);
    }

    /**
     * Inyecta presupuesto_id desde el presupuesto activo del usuario. El
     * controller (Grupo 5) ya garantiza que existe antes de llegar aquí —
     * el fallback a null es solo defensivo, nunca debería ejercitarse.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'presupuesto_id' => $this->user()->presupuesto_activo_id,
        ]);
    }

    /**
     * Reglas base (comunes a los 3 tipos) + condicionales según
     * design.md Decisión 6. `tipo` se resuelve con `tryFrom()` (no
     * comparación de string cruda): si llega un valor inválido, la regla
     * `tipo` (`required`+`enum`) ya lo reporta, y deliberadamente NO se
     * agrega ninguna regla condicional extra que generaría ruido sobre un
     * tipo que de por sí es inválido.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $presupuestoId = $this->input('presupuesto_id');
        $tipo = TipoTransaccion::tryFrom((string) $this->input('tipo'));

        $rules = [
            'presupuesto_id' => ['required', 'exists:presupuestos,id'],
            'cuenta_id' => [
                'required',
                Rule::exists('cuentas', 'id')->where('presupuesto_id', $presupuestoId),
            ],
            'tipo' => ['required', Rule::enum(TipoTransaccion::class)],
            'monto_centavos' => ['required', 'integer', 'min:1'],
            'fecha_hora' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
            'notas' => ['nullable', 'string', 'max:1000'],
            'es_split' => ['boolean'],
        ];

        return array_merge($rules, $this->reglasCondicionalesPorTipo($tipo, $presupuestoId));
    }

    /**
     * Matriz de validación condicional por tipo (design.md Decisión 6).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasCondicionalesPorTipo(?TipoTransaccion $tipo, mixed $presupuestoId): array
    {
        $categoriaValida = $this->categoriaValidaRule($presupuestoId);

        // 'sometimes' antes de 'declined': sin él, un payload que
        // simplemente OMITE es_split (en vez de enviarlo explícito en
        // false) falla la validación — a diferencia de 'prohibited',
        // 'declined' no considera "ausente" como "aceptable", solo acepta
        // los literales falsy explícitos (false/0/'0'/'off'/'no'). Ni
        // siquiera `nullable` lo arregla (declined no se salta con null).
        // Verificado empíricamente con Validator::make() antes de fijarlo
        // así — encontrado escribiendo los tests 12.2-12.4 de este mismo
        // grupo, que no mandaban es_split en absoluto.
        return match ($tipo) {
            TipoTransaccion::Transfer => [
                'cuenta_destino_id' => [
                    'required',
                    'different:cuenta_id',
                    Rule::exists('cuentas', 'id')->where('presupuesto_id', $presupuestoId),
                ],
                'categoria_id' => ['prohibited'],
                'beneficiario_id' => ['prohibited'],
                'es_split' => ['sometimes', 'declined'],
            ],
            TipoTransaccion::Inflow => [
                'beneficiario_id' => [
                    'required',
                    Rule::exists('beneficiarios', 'id')->where('presupuesto_id', $presupuestoId),
                ],
                'categoria_id' => ['prohibited'],
                'cuenta_destino_id' => ['prohibited'],
                'es_split' => ['sometimes', 'declined'],
            ],
            TipoTransaccion::Outflow => $this->reglasOutflow($categoriaValida, $presupuestoId),
            default => [],
        };
    }

    /**
     * Outflow es el único tipo que admite split. Sin split, categoria_id es
     * directa y requerida; con split, categoria_id del padre queda
     * prohibida (reside en las líneas) y se valida el array `splits`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasOutflow(Closure $categoriaValida, mixed $presupuestoId): array
    {
        $rules = [
            'cuenta_destino_id' => ['prohibited'],
            'beneficiario_id' => [
                'nullable',
                Rule::exists('beneficiarios', 'id')->where('presupuesto_id', $presupuestoId),
            ],
        ];

        if ($this->boolean('es_split')) {
            $rules['categoria_id'] = ['prohibited'];
            $rules['splits'] = ['required', 'array', 'min:2'];
            $rules['splits.*.categoria_id'] = ['required', 'distinct', $categoriaValida];
            $rules['splits.*.monto_centavos'] = ['required', 'integer', 'min:1'];
            $rules['splits.*.notas'] = ['nullable', 'string', 'max:1000'];
        } else {
            $rules['categoria_id'] = ['required', $categoriaValida];
        }

        return $rules;
    }

    /**
     * `categorias` NO tiene `presupuesto_id` directo (pertenece a
     * `grupo_categoria_id` -> `grupos_categorias.presupuesto_id`), así que
     * un `Rule::exists()->where('presupuesto_id', ...)` plano no sirve aquí
     * (sí funciona para cuentas/beneficiarios, que SÍ tienen la columna
     * directa). Closure con `whereHas` en su lugar.
     */
    protected function categoriaValidaRule(mixed $presupuestoId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($presupuestoId): void {
            $valida = Categoria::whereKey($value)
                ->whereHas('grupoCategoria', fn ($q) => $q->where('presupuesto_id', $presupuestoId))
                ->exists();

            if (! $valida) {
                $fail('La categoría seleccionada no existe o no pertenece a tu presupuesto activo.');
            }
        };
    }

    /**
     * Validaciones cross-field que no pueden expresarse como reglas por
     * campo: la suma de las líneas de split contra el monto del padre, y la
     * disponibilidad de un tipo de cambio para la conversión a moneda base.
     * `after()` corre DESPUÉS de que todas las reglas normales ya se
     * evaluaron — si cuenta_id ya falló, no tiene sentido intentar resolver
     * su moneda, por eso cada validación interna se guarda primero.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarSumaSplits($validator);
            $this->validarTasaDisponible($validator);
        });
    }

    protected function validarSumaSplits(Validator $validator): void
    {
        if (! $this->boolean('es_split')) {
            return;
        }

        $splits = $this->input('splits');

        if (! is_array($splits) || $validator->errors()->has('splits')) {
            return;
        }

        $sumaSplits = collect($splits)->sum(fn ($linea) => (int) ($linea['monto_centavos'] ?? 0));
        $montoPadre = (int) $this->input('monto_centavos');

        if ($sumaSplits !== $montoPadre) {
            $validator->errors()->add(
                'splits',
                "La suma de las líneas (Bs {$sumaSplits}) no coincide con el total (Bs {$montoPadre})."
            );
        }
    }

    /**
     * Valida DOS conversiones potenciales, no solo una: cuenta->moneda base
     * del presupuesto (necesaria para monto_moneda_base_centavos en
     * cualquier tipo), Y — si es transfer entre cuentas de monedas
     * distintas — cuenta->cuentaDestino (necesaria para
     * monto_centavos_destino). Sin este segundo chequeo, un transfer entre
     * 2 monedas sin tasa registrada pasaría la validación pero
     * CalculadoraTransaccionService::resolverMontoDestino() lanzaría una
     * RuntimeException sin capturar en el controller — un 500 feo en vez de
     * un error de validación limpio. Encontrado escribiendo el test 12.4.
     */
    protected function validarTasaDisponible(Validator $validator): void
    {
        if ($validator->errors()->has('cuenta_id') || ! $this->filled('cuenta_id')) {
            return;
        }

        $cuenta = Cuenta::with('presupuesto')->find($this->input('cuenta_id'));

        if (! $cuenta || ! $cuenta->presupuesto) {
            return;
        }

        $fecha = $this->filled('fecha_hora') ? Carbon::parse($this->input('fecha_hora')) : now();
        $monedaCuenta = $cuenta->moneda_codigo;
        $monedaBase = $cuenta->presupuesto->moneda_base_codigo;

        if ($monedaCuenta !== $monedaBase) {
            $resuelto = app(TasaCambioService::class)->resolver($this->user()->id, $monedaCuenta, $monedaBase, $fecha);

            if ($resuelto === null) {
                $validator->errors()->add(
                    'tasa_cambio',
                    "No existe un tipo de cambio registrado para convertir de {$monedaCuenta} a {$monedaBase} en la fecha {$fecha->toDateString()}. Regístralo primero en Tipos de cambio."
                );

                return;
            }
        }

        $esTransfer = $this->input('tipo') === TipoTransaccion::Transfer->value;

        if ($esTransfer && $this->filled('cuenta_destino_id') && ! $validator->errors()->has('cuenta_destino_id')) {
            $cuentaDestino = Cuenta::find($this->input('cuenta_destino_id'));

            if ($cuentaDestino && $cuentaDestino->moneda_codigo !== $monedaCuenta) {
                $resueltoDestino = app(TasaCambioService::class)->resolver($this->user()->id, $monedaCuenta, $cuentaDestino->moneda_codigo, $fecha);

                if ($resueltoDestino === null) {
                    $validator->errors()->add(
                        'tasa_cambio',
                        "No existe un tipo de cambio registrado para convertir de {$monedaCuenta} a {$cuentaDestino->moneda_codigo} en la fecha {$fecha->toDateString()}. Regístralo primero en Tipos de cambio."
                    );
                }
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'presupuesto_id.required' => 'Necesitas un presupuesto activo para registrar una transacción.',
            'presupuesto_id.exists' => 'El presupuesto activo ya no existe.',
            'cuenta_id.required' => 'La cuenta es requerida.',
            'cuenta_id.exists' => 'La cuenta seleccionada no existe o no pertenece a tu presupuesto activo.',
            'cuenta_destino_id.required' => 'La cuenta destino es requerida para una transferencia.',
            'cuenta_destino_id.different' => 'La cuenta destino debe ser distinta a la cuenta de origen.',
            'cuenta_destino_id.exists' => 'La cuenta destino seleccionada no existe o no pertenece a tu presupuesto activo.',
            'cuenta_destino_id.prohibited' => 'La cuenta destino solo aplica a transferencias.',
            'categoria_id.required' => 'La categoría es requerida.',
            'categoria_id.prohibited' => 'La categoría no aplica para este tipo de transacción.',
            'beneficiario_id.required' => 'El beneficiario es requerido para un ingreso.',
            'beneficiario_id.exists' => 'El beneficiario seleccionado no existe o no pertenece a tu presupuesto activo.',
            'beneficiario_id.prohibited' => 'El beneficiario no aplica para este tipo de transacción.',
            'tipo.required' => 'El tipo de transacción es requerido.',
            'tipo.enum' => 'El tipo de transacción no es válido.',
            'monto_centavos.required' => 'El monto es requerido.',
            'monto_centavos.integer' => 'El monto debe ser un número entero.',
            'monto_centavos.min' => 'El monto debe ser mayor a cero.',
            'fecha_hora.required' => 'La fecha y hora son requeridas.',
            'fecha_hora.date' => 'La fecha y hora no son válidas.',
            'fecha_hora.before_or_equal' => 'La fecha no puede ser mayor a un día en el futuro.',
            'notas.max' => 'Las notas no pueden tener más de 1000 caracteres.',
            'es_split.declined' => 'Este tipo de transacción no admite división en categorías.',
            'splits.required' => 'Debes agregar al menos 2 líneas para dividir la transacción.',
            'splits.array' => 'El formato de las líneas de división no es válido.',
            'splits.min' => 'Debes agregar al menos 2 líneas para dividir la transacción.',
            'splits.*.categoria_id.required' => 'Cada línea debe tener una categoría.',
            'splits.*.categoria_id.distinct' => 'No puedes repetir la misma categoría en dos líneas.',
            'splits.*.monto_centavos.required' => 'Cada línea debe tener un monto.',
            'splits.*.monto_centavos.integer' => 'El monto de la línea debe ser un número entero.',
            'splits.*.monto_centavos.min' => 'El monto de la línea debe ser mayor a cero.',
            'splits.*.notas.max' => 'La nota de la línea no puede tener más de 1000 caracteres.',
        ];
    }
}
