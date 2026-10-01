<?php

namespace App\Http\Requests;

use App\Models\GrupoCategoria;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreGrupoCategoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', GrupoCategoria::class);
    }

    /**
     * Inyecta presupuesto_id desde el presupuesto activo del usuario. El
     * controller (Grupo 6) ya garantiza que existe antes de llegar aquí.
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
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icono' => ['required', 'string', Rule::in(config('mynab.iconos_grupo_categoria'))],
        ];
    }

    /**
     * Único por (presupuesto_id, nombre), case-insensitive. Closure en vez de
     * Rule::unique()->where(): su comparación base es exacta (case-sensitive)
     * y dejaría pasar "OBLIGACIONES" vs "Obligaciones" (mismo patrón que
     * StorePresupuestoRequest/StoreCuentaRequest).
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $existe = GrupoCategoria::where('presupuesto_id', $this->input('presupuesto_id'))
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
                ->exists();

            if ($existe) {
                $fail('Ya existe un grupo con ese nombre en este presupuesto.');
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
            'presupuesto_id.required' => 'Necesitas un presupuesto activo para crear un grupo.',
            'presupuesto_id.exists' => 'El presupuesto activo ya no existe.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'color.required' => 'El color es requerido.',
            'color.regex' => 'El color debe ser un hexadecimal válido (#RRGGBB).',
            'icono.required' => 'El icono es requerido.',
            'icono.in' => 'Selecciona un icono válido de la lista.',
        ];
    }
}
