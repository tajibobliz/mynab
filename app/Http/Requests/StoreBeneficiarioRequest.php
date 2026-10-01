<?php

namespace App\Http\Requests;

use App\Models\Beneficiario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreBeneficiarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Beneficiario::class);
    }

    /**
     * Inyecta presupuesto_id desde el presupuesto activo del usuario. El
     * controller (Grupo 4) ya garantiza que existe antes de llegar aquí —
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
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Único por (presupuesto_id, nombre), case-insensitive. Mismo patrón que
     * Cuenta/GrupoCategoria/Categoria: closure en vez de `Rule::unique()`
     * porque la comparación base de `Rule::unique` es siempre exacta
     * (case-sensitive). Los soft-deleted no cuentan (Eloquent ya los excluye
     * por defecto de este `where`).
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $existe = Beneficiario::where('presupuesto_id', $this->input('presupuesto_id'))
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
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
            'presupuesto_id.required' => 'Necesitas un presupuesto activo para crear un beneficiario.',
            'presupuesto_id.exists' => 'El presupuesto activo ya no existe.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'notas.max' => 'Las notas no pueden tener más de 1000 caracteres.',
        ];
    }
}
