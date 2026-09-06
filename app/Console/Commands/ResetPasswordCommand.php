<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

class ResetPasswordCommand extends Command
{
    protected $signature = 'user:reset-password
        {--email= : E-mail do utilizador}
        {--password= : Nova palavra-passe (mínimo 8 caracteres)}';

    protected $description = 'Redefine a palavra-passe de um utilizador existente.';

    public function handle(): int
    {
        $email = $this->option('email') ?: text(label: 'E-mail do utilizador', required: true);

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->components->error('Não foi encontrado nenhum utilizador com este e-mail.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: promptPassword(label: 'Nova palavra-passe', required: true);

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', 'min:8']],
            ['password.min' => 'A palavra-passe deve ter pelo menos 8 caracteres.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user->update(['password' => Hash::make($password)]);

        // Invalidate existing API tokens for safety.
        $user->tokens()->delete();

        $this->components->info("Palavra-passe redefinida para {$user->email}.");

        return self::SUCCESS;
    }
}
