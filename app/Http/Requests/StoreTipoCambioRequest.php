<?php

namespace App\Http\Requests;

use App\Models\TipoCambio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoCambioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', TipoCambio::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'moneda_origen' => ['required', 'string', Rule::exists('monedas', 'codigo')->where('activa', true)],
            'moneda_destino' => [
                'required', 'string', 'different:moneda_origen',
                Rule::exists('monedas', 'codigo')->where('activa', true),
            ],
            'tasa' => ['required', 'numeric', 'min:0.00000001', 'max:99999999'],
            'fecha' => ['required', 'date', 'before_or_equal:today', $this->fechaUnicaRule()],
        ];
    }

    /**
     * Único por (user_id, moneda_origen, moneda_destino, fecha). Closure en
     * vez de `Rule::unique` compuesto porque necesita leer moneda_origen y
     * moneda_destino del propio payload, no solo del campo validado (mismo
     * patrón que `nombreUnicoRule` en StorePresupuestoRequest, Change 2).
     *
     * `whereDate()` en vez de `where('fecha', $value)`: el cast `date` del
     * modelo normaliza el valor a "Y-m-d H:i:s" al guardar aunque la columna
     * sea DATE; un `where` crudo compara contra el input sin hora y nunca
     * matchea, dejando pasar duplicados hasta que el UNIQUE de la BD los
     * revienta como excepción sin manejar. Encontrado con un test real
     * (Grupo 12) que insertaba un duplicado sin que la validación lo cachara.
     */
    protected function fechaUnicaRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $existe = TipoCambio::where('user_id', $this->user()->id)
                ->where('moneda_origen', $this->input('moneda_origen'))
                ->where('moneda_destino', $this->input('moneda_destino'))
                ->whereDate('fecha', $value)
                ->exists();

            if ($existe) {
                $fail('Ya tienes un tipo de cambio para este par en esta fecha.');
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
            'moneda_origen.required' => 'La moneda de origen es obligatoria.',
            'moneda_origen.exists' => 'La moneda de origen no existe o no está activa.',
            'moneda_destino.required' => 'La moneda de destino es obligatoria.',
            'moneda_destino.different' => 'La moneda de destino debe ser distinta a la de origen.',
            'moneda_destino.exists' => 'La moneda de destino no existe o no está activa.',
            'tasa.required' => 'La tasa es obligatoria.',
            'tasa.numeric' => 'La tasa debe ser un número.',
            'tasa.min' => 'La tasa debe ser mayor a 0.',
            'tasa.max' => 'La tasa es demasiado alta.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha no es válida.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
