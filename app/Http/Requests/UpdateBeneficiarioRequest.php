<?php

namespace App\Http\Requests;

use App\Models\Beneficiario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateBeneficiarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('beneficiario'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `presupuesto_id` NO está aquí: es fijo tras crear (design.md Decisión
     * 5). El controller además aplica `array_diff_key` defensivo por si
     * llegara en el payload raw.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnicoRule()],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Igual que en StoreBeneficiarioRequest, pero el presupuesto se lee del
     * registro existente (no es editable) y se excluye el propio ID.
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $beneficiario = $this->route('beneficiario');

            $existe = Beneficiario::where('presupuesto_id', $beneficiario->presupuesto_id)
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
                ->whereKeyNot($beneficiario)
                ->exists();

            if ($existe) {
                $fail('Ya existe un beneficiario con ese nombre en este presupuesto.');
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
            'notas.max' => 'Las notas no pueden tener más de 1000 caracteres.',
        ];
    }
}
