<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'catatan:user {email : Account email} {--name= : Display name (new accounts)} {--admin : Make this account an admin}';

    protected $description = 'Create an account, or reset the password of an existing one (lock-out recovery).';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->secret('Password (min 8 characters)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(8)]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->exists ? $user->name : ($this->option('name') ?: 'Me');
        $user->password = $password;
        if ($this->option('admin')) {
            $user->is_admin = true;
        }
        $user->save();

        $this->info($user->wasRecentlyCreated ? "Account created: {$email}" : "Password updated: {$email}");

        return self::SUCCESS;
    }
}
