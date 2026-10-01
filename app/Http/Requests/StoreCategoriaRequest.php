<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Categoria::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `grupo_categoria_id` no se auto-inyecta (a diferencia de
     * `presupuesto_id` en Cuenta/GrupoCategoria) porque el usuario elige a
     * qué grupo pertenece la categoría — pero se valida que ese grupo
     * pertenezca a SU presupuesto activo. Sin este `where` extra, un usuario
     * podría enviar el ID de un grupo ajeno y la categoría quedaría creada
     * ahí (IDOR): `exists` solo comprueba que la fila existe en algún lado,
     * no que sea del usuario.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grupo_categoria_id' => [
                'required',
                'integer',
                Rule::exists('grupos_categorias', 'id')
                    ->where('presupuesto_id', $this->user()->presupuesto_activo_id),
            ],
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnicoRule()],
        ];
    }

    /**
     * Único por (grupo_categoria_id, nombre), case-insensitive. Mismo patrón
     * que GrupoCategoria/Cuenta, scope al grupo en vez del presupuesto.
     */
    protected function nombreUnicoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $existe = Categoria::where('grupo_categoria_id', $this->input('grupo_categoria_id'))
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($value)])
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
            'grupo_categoria_id.required' => 'El grupo es requerido.',
            'grupo_categoria_id.integer' => 'El grupo no es válido.',
            'grupo_categoria_id.exists' => 'El grupo seleccionado no existe o no pertenece a tu presupuesto activo.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
        ];
    }
}
