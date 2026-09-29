<?php

namespace App\Http\Requests;

use App\Enums\TipoCuenta;
use App\Models\Cuenta;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCuentaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Cuenta::class);
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'presupuesto_id' => ['required', 'exists:presupuestos,id'],
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnicoRule()],
            'tipo' => ['required', Rule::enum(TipoCuenta::class)],
            'moneda_codigo' => [
                'required', 'string', 'min:3', 'max:5',
                Rule::exists('monedas', 'codigo')->where('activa', true),
            ],
            'saldo_inicial_centavos' => ['required', 'integer', 'min:0'],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'numero_referencia' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Único por (presupuesto_id, nombre), case-insensitive. Closure en vez
     * de `Rule::unique()->where(...)`: la comparación BASE de `Rule::unique`
     * siempre es exacta (case-sensitive) sobre la columna dada — un
     * `->where()` adicional solo AND-ea condiciones extra, no la reemplaza.
     * "PERSONAL" vs "Personal" se colaría. Mismo patrón que
     * `StorePresupuestoRequest` (Change 2) y `StoreTipoCambioRequest`
     * (Change 3). Los soft-deleted no cuentan (Eloquent ya los excluye por
     * defecto de este `where`).
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $existe = Cuenta::where('presupuesto_id', $this->input('presupuesto_id'))
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
                ->exists();

            if ($existe) {
                $fail('Ya existe una cuenta con ese nombre en este presupuesto.');
            }
        };
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'presupuesto_id.required' => 'Necesitas un presupuesto activo para crear una cuenta.',
            'presupuesto_id.exists' => 'El presupuesto activo ya no existe.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'tipo.required' => 'El tipo de cuenta es requerido.',
            'tipo.enum' => 'El tipo de cuenta no es válido.',
            'moneda_codigo.required' => 'La moneda es requerida.',
            'moneda_codigo.exists' => 'La moneda seleccionada no está activa.',
            'saldo_inicial_centavos.required' => 'El saldo inicial es requerido.',
            'saldo_inicial_centavos.integer' => 'El saldo inicial debe ser un número entero.',
            'saldo_inicial_centavos.min' => 'El saldo inicial no puede ser negativo.',
            'fecha_apertura.required' => 'La fecha de apertura es requerida.',
            'fecha_apertura.date' => 'La fecha de apertura no es válida.',
            'fecha_apertura.before_or_equal' => 'La fecha de apertura no puede ser futura.',
            'numero_referencia.max' => 'El número de referencia no puede tener más de 50 caracteres.',
        ];
    }
}
