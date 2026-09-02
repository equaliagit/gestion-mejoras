<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\Visibility;
use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las reglas del formulario de nueva propuesta.
 *
 * En Laravel la validación no vive en el controlador sino en una clase como
 * esta, que se declara como parámetro del método. Si algo no cuadra, el
 * usuario vuelve al formulario con sus datos y los avisos puestos, sin que el
 * controlador llegue a ejecutarse. Es el equivalente a las anotaciones de
 * Bean Validation, pero con las reglas juntas y a la vista.
 */
class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Proposal::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:140'],
            'problem' => ['required', 'string', 'min:20', 'max:5000'],
            'proposal' => ['required', 'string', 'min:20', 'max:5000'],
            'expected_benefit' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'visibility' => ['required', Rule::enum(Visibility::class)],
            // Opcional a propósito: el formulario ya pide tres párrafos, y
            // quien no tenga claro dónde encaja su idea no debería atascarse aquí.
            'impacts' => ['nullable', 'array'],
            'impacts.*' => ['integer', Rule::exists('impacts', 'id')->where('is_active', true)],
            'accion' => ['required', Rule::in(['borrador', 'enviar'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'area_id.required' => 'Elige el área a la que afecta.',
            'title.required' => 'Ponle un título.',
            'title.max' => 'El título se queda en 140 caracteres como mucho.',
            'problem.required' => 'Cuéntanos qué problema hay hoy.',
            'problem.min' => 'Cuéntalo con un poco más de detalle, por favor.',
            'proposal.required' => 'Explica qué propones.',
            'proposal.min' => 'Explícalo con un poco más de detalle, por favor.',
            'expected_benefit.required' => 'Dinos qué esperas conseguir.',
            'expected_benefit.min' => 'Un poco más de detalle nos ayuda a valorarla.',
            'priority.required' => 'Marca qué prioridad le das.',
            'visibility.required' => 'Elige quién puede verla.',
        ];
    }

    /** Los datos que se pueden volcar directamente en la propuesta. */
    public function datosDePropuesta(): array
    {
        return $this->safe()->only([
            'area_id', 'title', 'problem', 'proposal', 'expected_benefit', 'priority', 'visibility',
        ]);
    }

    public function quiereEnviar(): bool
    {
        return $this->input('accion') === 'enviar';
    }
}
