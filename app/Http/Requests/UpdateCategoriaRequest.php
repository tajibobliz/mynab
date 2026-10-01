<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCategoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('categoria'));
    }

    /**
     * grupo_categoria_id NO está aquí: no es editable en Fase 1 (design.md
     * Decisión 4 / tasks.md 5.4). Para mover una categoría a otro grupo hay
     * que borrarla y recrearla; drag & drop entre grupos queda para Fase 2.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnicoRule()],
        ];
    }

    /**
     * Igual que en StoreCategoriaRequest, pero el grupo se lee del registro
     * existente (no es editable) y se excluye el propio ID.
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $categoria = $this->route('categoria');

            $existe = Categoria::where('grupo_categoria_id', $categoria->grupo_categoria_id)
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
                ->whereKeyNot($categoria)
                ->exists();

            if ($existe) {
                $fail('Ya existe una categoría con ese nombre en este grupo.');
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
        ];
    }
}
