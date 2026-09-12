<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'spent_on' => ['required', 'date'],
            'scope' => ['required', Rule::in(['apartment', 'other'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Receipt photo captured from the phone camera (optional).
            'receipt' => ['nullable', 'image', 'mimes:jpeg,jpg,png,heic,heif,webp', 'max:8192'],
            // Flag sent from the edit form to detach an existing receipt.
            'remove_receipt' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'A descrição é obrigatória.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.numeric' => 'O valor deve ser um número.',
            'spent_on.required' => 'A data é obrigatória.',
            'scope.in' => 'O âmbito é inválido.',
            'category_id.exists' => 'A categoria selecionada não existe.',
            'receipt.image' => 'O recibo deve ser uma imagem.',
            'receipt.mimes' => 'Formato de imagem não suportado.',
            'receipt.max' => 'A imagem do recibo é demasiado grande (máx. 8 MB).',
        ];
    }
}
