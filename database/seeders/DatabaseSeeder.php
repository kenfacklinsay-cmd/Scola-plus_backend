<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // if (! $this->command) {
        //     throw new \RuntimeException('Le seeder initial du directeur doit être exécuté depuis le terminal.');
        // }

        // $credentials = [
        //     'firstname' => trim($this->command->ask('Prénom du directeur')),
        //     'lastname' => trim($this->command->ask('Nom du directeur')),
        //     'email' => mb_strtolower(trim($this->command->ask('Adresse e-mail du directeur'))),
        //     'password' => $this->command->secret('Mot de passe (12 caractères minimum)'),
        //     'password_confirmation' => $this->command->secret('Confirme le mot de passe'),
        // ];
                if (env('DIRECTEUR_EMAIL')) {
            $credentials = [
                'firstname' => env('DIRECTEUR_FIRSTNAME', 'Linsay'),
                'lastname' => env('DIRECTEUR_LASTNAME', 'Admin'),
                'email' => env('DIRECTEUR_EMAIL'),
                'password' => env('DIRECTEUR_PASSWORD'),
                'password_confirmation' => env('DIRECTEUR_PASSWORD'),
            ];
        } else {
            if (! $this->command) {
                throw new \RuntimeException('Le seeder initial du directeur doit être exécuté depuis le terminal.');
            }

            $credentials = [
                'firstname' => trim($this->command->ask('Prénom du directeur')),
                'lastname' => trim($this->command->ask('Nom du directeur')),
                'email' => mb_strtolower(trim($this->command->ask('Adresse e-mail du directeur'))),
                'password' => $this->command->secret('Mot de passe (12 caractères minimum)'),
                'password_confirmation' => $this->command->secret('Confirme le mot de passe'),
            ];
        }

        $validator = Validator::make($credentials, [
            'firstname' => ['required', 'string', 'max:100'],
            'lastname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'same:password_confirmation'],
        ]);

        if ($validator->fails()) {
            $this->command->error($validator->errors()->first());

            throw new \RuntimeException('Les informations du compte directeur sont invalides.');
        }

        User::query()->create([
            'firstname' => $credentials['firstname'],
            'lastname' => $credentials['lastname'],
            'email' => $credentials['email'],
            // 'password' => $credentials['password'],
            'password' => Hash::make($credentials['password']),                
            'role' => 'DIRECTEUR',
            'status' => 'ACTIF',
            'must_change_password' => false,
        ]);

        $this->command->info('Le compte directeur a été créé.');
    }
}
