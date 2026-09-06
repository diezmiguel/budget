<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['pending', 'paid', 'overdue'])],
            'paid_at' => ['nullable', 'date'],
            'owner_label' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'scope' => ['required', Rule::in(['apartment', 'other'])],
            'recurrence' => ['required', Rule::in(['none', 'weekly', 'monthly', 'yearly'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.numeric' => 'O valor deve ser um número.',
            'due_date.required' => 'A data de vencimento é obrigatória.',
            'status.in' => 'O estado é inválido.',
            'scope.in' => 'O âmbito é inválido.',
            'recurrence.in' => 'A recorrência é inválida.',
            'category_id.exists' => 'A categoria selecionada não existe.',
            'responsible_user_id.exists' => 'O responsável selecionado não existe.',
        ];
    }
}
