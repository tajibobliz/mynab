<?php

namespace App\Http\Requests;

use App\Enums\TipoTransaccion;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Services\TasaCambioService;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTransaccionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('transaccion'));
    }

    /**
     * `tipo` y `es_split` NO están en las reglas: son fijos tras crear
     * (design.md Decisión 6 / tasks.md 3.2, Fase 2 podría permitir
     * cambiarlos). Se leen del registro existente, no del payload. El
     * controller además aplica `array_diff_key` defensivo por si llegaran
     * en el payload raw.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $transaccion = $this->route('transaccion');
        $presupuestoId = $this->user()->presupuesto_activo_id;

        $rules = [
            'cuenta_id' => [
                'required',
                Rule::exists('cuentas', 'id')->where('presupuesto_id', $presupuestoId),
            ],
            'monto_centavos' => ['required', 'integer', 'min:1'],
            'fecha_hora' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];

        return array_merge($rules, $this->reglasCondicionalesPorTipo($transaccion->tipo, $transaccion->es_split, $presupuestoId));
    }

    /**
     * Misma matriz que StoreTransaccionRequest (design.md Decisión 6), pero
     * `$tipo` y `$esSplit` vienen del registro existente, no del payload.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasCondicionalesPorTipo(?TipoTransaccion $tipo, bool $esSplit, mixed $presupuestoId): array
    {
        $categoriaValida = $this->categoriaValidaRule($presupuestoId);

        return match ($tipo) {
            TipoTransaccion::Transfer => [
                'cuenta_destino_id' => [
                    'required',
                    'different:cuenta_id',
                    Rule::exists('cuentas', 'id')->where('presupuesto_id', $presupuestoId),
                ],
                'categoria_id' => ['prohibited'],
                'beneficiario_id' => ['prohibited'],
            ],
            TipoTransaccion::Inflow => [
                'beneficiario_id' => [
                    'required',
                    Rule::exists('beneficiarios', 'id')->where('presupuesto_id', $presupuestoId),
                ],
                'categoria_id' => ['prohibited'],
                'cuenta_destino_id' => ['prohibited'],
            ],
            TipoTransaccion::Outflow => $this->reglasOutflow($categoriaValida, $presupuestoId, $esSplit),
            default => [],
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasOutflow(Closure $categoriaValida, mixed $presupuestoId, bool $esSplit): array
    {
        $rules = [
            'cuenta_destino_id' => ['prohibited'],
            'beneficiario_id' => [
                'nullable',
                Rule::exists('beneficiarios', 'id')->where('presupuesto_id', $presupuestoId),
            ],
        ];

        if ($esSplit) {
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
     * Idéntico a StoreTransaccionRequest: `categorias` no tiene
     * `presupuesto_id` directo, se valida vía `grupoCategoria`.
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarSumaSplits($validator);
            $this->validarTasaDisponible($validator);
        });
    }

    protected function validarSumaSplits(Validator $validator): void
    {
        if (! $this->route('transaccion')->es_split) {
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
     * Misma corrección que StoreTransaccionRequest: valida cuenta->base Y,
     * si es transfer entre monedas distintas, cuenta->cuentaDestino también
     * (ver esa clase para el detalle del gap que esto cierra).
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

        $esTransfer = $this->route('transaccion')->tipo === TipoTransaccion::Transfer;

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
            'monto_centavos.required' => 'El monto es requerido.',
            'monto_centavos.integer' => 'El monto debe ser un número entero.',
            'monto_centavos.min' => 'El monto debe ser mayor a cero.',
            'fecha_hora.required' => 'La fecha y hora son requeridas.',
            'fecha_hora.date' => 'La fecha y hora no son válidas.',
            'fecha_hora.before_or_equal' => 'La fecha no puede ser mayor a un día en el futuro.',
            'notas.max' => 'Las notas no pueden tener más de 1000 caracteres.',
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
