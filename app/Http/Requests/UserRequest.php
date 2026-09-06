<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admins may manage users through the API.
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role' => ['required', Rule::in(Role::values())],
            'password' => [
                $isCreating ? 'required' : 'nullable',
                'string', 'min:8',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'O e-mail é inválido.',
            'email.unique' => 'Já existe um utilizador com este e-mail.',
            'role.in' => 'O perfil é inválido.',
            'password.required' => 'A palavra-passe é obrigatória.',
            'password.min' => 'A palavra-passe deve ter pelo menos 8 caracteres.',
        ];
    }
}
