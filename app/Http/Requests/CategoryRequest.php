<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'type' => ['required', Rule::in(['expense', 'bill', 'both'])],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.unique' => 'Já existe uma categoria com este nome.',
            'type.in' => 'O tipo é inválido.',
            'color.regex' => 'A cor deve estar no formato hexadecimal (ex.: #6366f1).',
        ];
    }
}
