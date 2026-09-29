<?php

namespace App\Http\Requests;

use App\Enums\TipoCuenta;
use App\Models\Cuenta;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCuentaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cuenta'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `moneda_codigo` y `presupuesto_id` NO están aquí: son fijos tras crear
     * (design.md Decisión 2 y 10). El controller además aplica
     * `array_diff_key` defensivo por si llegaran en el payload raw.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnicoRule()],
            'tipo' => ['required', Rule::enum(TipoCuenta::class)],
            'saldo_inicial_centavos' => ['required', 'integer', 'min:0'],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'numero_referencia' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Igual que en StoreCuentaRequest, pero el presupuesto se lee del
     * registro existente (no es editable) y se excluye el propio ID.
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $cuenta = $this->route('cuenta');

            $existe = Cuenta::where('presupuesto_id', $cuenta->presupuesto_id)
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
                ->whereKeyNot($cuenta)
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
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'tipo.required' => 'El tipo de cuenta es requerido.',
            'tipo.enum' => 'El tipo de cuenta no es válido.',
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
