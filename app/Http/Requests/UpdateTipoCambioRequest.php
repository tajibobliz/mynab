<?php

namespace App\Http\Requests;

use App\Models\TipoCambio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTipoCambioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tipo_cambio'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Solo `tasa` y `fecha` son editables (design.md Decisión 10):
     * moneda_origen/moneda_destino son la identidad del registro y no se
     * incluyen aquí, así que la validación no los exige ni los toca aunque
     * el frontend los mande deshabilitados en el formulario.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tasa' => ['required', 'numeric', 'min:0.00000001', 'max:99999999'],
            'fecha' => ['required', 'date', 'before_or_equal:today', $this->fechaUnicaRule()],
        ];
    }

    /**
     * Igual que en StoreTipoCambioRequest, pero el par origen/destino se lee
     * del registro existente (no es editable) y se excluye el propio ID.
     *
     * `whereDate()` en vez de `where('fecha', $value)`: el cast `date` del
     * modelo normaliza cualquier valor asignado a formato completo
     * "Y-m-d H:i:s" al guardar (aunque la columna sea DATE), pero un `where`
     * crudo compara el string tal cual llega del input ("Y-m-d", sin hora) —
     * nunca matchea. `whereDate()` extrae solo la parte de fecha en SQL
     * (portable entre SQLite y Postgres), evitando el desajuste.
     */
    protected function fechaUnicaRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tipoCambio = $this->route('tipo_cambio');

            $existe = TipoCambio::where('user_id', $this->user()->id)
                ->where('moneda_origen', $tipoCambio->moneda_origen)
                ->where('moneda_destino', $tipoCambio->moneda_destino)
                ->whereDate('fecha', $value)
                ->whereKeyNot($tipoCambio)
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
