<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:create
        {--name= : Nome completo do utilizador}
        {--email= : Endereço de e-mail (único)}
        {--password= : Palavra-passe (mínimo 8 caracteres)}
        {--role= : Perfil do utilizador (admin ou manager)}';

    /**
     * The console command description.
     */
    protected $description = 'Cria manualmente uma conta de utilizador (Admin ou Gestor).';

    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Nome completo',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'E-mail',
            required: true,
        );

        $role = $this->option('role') ?: select(
            label: 'Perfil',
            options: [
                Role::Admin->value => Role::Admin->label(),
                Role::Manager->value => Role::Manager->label(),
            ],
            default: Role::Manager->value,
        );

        $password = $this->option('password') ?: promptPassword(
            label: 'Palavra-passe',
            required: true,
        );

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password' => $password,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'role' => ['required', 'in:'.implode(',', Role::values())],
                'password' => ['required', 'string', 'min:8'],
            ],
            [
                'email.unique' => 'Já existe um utilizador com este e-mail.',
                'role.in' => 'O perfil deve ser "admin" ou "manager".',
                'password.min' => 'A palavra-passe deve ter pelo menos 8 caracteres.',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => Hash::make($password),
        ]);

        $this->components->info(sprintf(
            'Utilizador criado: %s <%s> [%s]',
            $user->name,
            $user->email,
            $user->role->label(),
        ));

        return self::SUCCESS;
    }
}
