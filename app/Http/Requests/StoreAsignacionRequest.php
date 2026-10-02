<?php

namespace App\Http\Requests;

use App\Models\Asignacion;
use App\Models\Categoria;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAsignacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Asignacion::class);
    }

    /**
     * Inyecta presupuesto_id desde el presupuesto activo del usuario. Si no
     * hay presupuesto activo, se deja sin inyectar: la propia regla
     * `exists`/whereHas de categoria_id fallará (no hay forma de que una
     * categoría "pertenezca" a un presupuesto_id null), y el controller
     * (Grupo 6) de todas formas garantiza un presupuesto activo antes de
     * llegar aquí — este caso no debería ejercitarse en la práctica.
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
            // Sin esto, $request->validated() NUNCA habría incluido
            // presupuesto_id en su salida: Laravel solo devuelve ahí las
            // claves que tienen una regla definida, aunque el valor ya esté
            // mergeado por prepareForValidation() — mismo patrón ya usado en
            // StoreBeneficiarioRequest, que se me pasó por alto en Grupo 4
            // (el bug no se vio ahí porque esa verificación solo chequeaba
            // pass/fail de la validación, nunca inspeccionaba el contenido
            // real de validated()).
            'presupuesto_id' => ['required', 'exists:presupuestos,id'],
            'categoria_id' => ['required', 'integer', $this->categoriaValidaRule()],
            'año' => ['required', 'integer', 'between:2020,2100'],
            'mes' => ['required', 'integer', 'between:1,12'],
            'monto_centavos' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * `categorias` NO tiene `presupuesto_id` directo (pertenece a
     * `grupo_categoria_id` -> `grupos_categorias.presupuesto_id`), mismo
     * patrón consolidado en `StoreTransaccionRequest` (Change 7): closure
     * con `whereHas` en vez de `Rule::exists()->where()`.
     */
    protected function categoriaValidaRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $valida = Categoria::whereKey($value)
                ->whereHas('grupoCategoria', fn ($q) => $q->where('presupuesto_id', $this->input('presupuesto_id')))
                ->exists();

            if (! $valida) {
                $fail('La categoría no existe en este presupuesto.');
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
            'presupuesto_id.required' => 'Necesitas un presupuesto activo para asignar presupuesto.',
            'presupuesto_id.exists' => 'El presupuesto activo ya no existe.',
            'categoria_id.required' => 'La categoría es requerida.',
            'categoria_id.integer' => 'La categoría no es válida.',
            'año.required' => 'El año es requerido.',
            'año.integer' => 'El año debe ser un número entero.',
            'año.between' => 'El año debe estar entre 2020 y 2100.',
            'mes.required' => 'El mes es requerido.',
            'mes.integer' => 'El mes debe ser un número entero.',
            'mes.between' => 'El mes debe estar entre 1 y 12.',
            'monto_centavos.required' => 'El monto es requerido.',
            'monto_centavos.integer' => 'El monto debe ser un número entero.',
            'monto_centavos.min' => 'El monto no puede ser negativo.',
        ];
    }
}
