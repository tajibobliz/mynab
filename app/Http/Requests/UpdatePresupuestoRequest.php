<?php

namespace App\Http\Requests;

use App\Models\Presupuesto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePresupuestoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('presupuesto'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:100', $this->nombreUnicoRule()],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icono' => ['required', 'string', 'in:'.implode(',', config('mynab.iconos_presupuesto'))],
        ];
    }

    /**
     * Igual que en StorePresupuestoRequest, pero excluye el propio registro
     * del chequeo de unicidad.
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $existe = Presupuesto::where('user_id', $this->user()->id)
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
                ->whereKeyNot($this->route('presupuesto'))
                ->exists();

            if ($existe) {
                $fail('Ya tienes un presupuesto con ese nombre.');
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
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'descripcion.max' => 'La descripción no puede tener más de 500 caracteres.',
            'color.required' => 'El color es obligatorio.',
            'color.regex' => 'El color debe ser un hexadecimal válido (#RRGGBB).',
            'icono.required' => 'El icono es obligatorio.',
            'icono.in' => 'Selecciona un icono válido de la lista.',
        ];
    }
}
