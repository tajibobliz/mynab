<?php

namespace App\Http\Requests;

use App\Models\GrupoCategoria;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateGrupoCategoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('grupo_categoria'));
    }

    /**
     * presupuesto_id NO está aquí: es fijo tras crear (mismo patrón que
     * moneda_codigo en Cuenta, Change 4).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnicoRule()],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icono' => ['required', 'string', Rule::in(config('mynab.iconos_grupo_categoria'))],
        ];
    }

    /**
     * Igual que en StoreGrupoCategoriaRequest, pero el presupuesto se lee del
     * registro existente y se excluye el propio ID.
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $grupo = $this->route('grupo_categoria');

            $existe = GrupoCategoria::where('presupuesto_id', $grupo->presupuesto_id)
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
                ->whereKeyNot($grupo)
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
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'color.required' => 'El color es requerido.',
            'color.regex' => 'El color debe ser un hexadecimal válido (#RRGGBB).',
            'icono.required' => 'El icono es requerido.',
            'icono.in' => 'Selecciona un icono válido de la lista.',
        ];
    }
}
