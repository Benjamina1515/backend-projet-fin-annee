<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer un compte admin
        User::create([
            'name' => 'Administrateur',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'avatar' => null,
        ]);

        // Créer un compte professeur
        User::create([
            'name' => 'Professeur',
            'email' => 'prof@example.com',
            'password' => Hash::make('password'),
            'role' => 'prof',
            'avatar' => null,
        ]);

        // Créer un compte étudiant
        User::create([
            'name' => 'Étudiant',
            'email' => 'etudiant@example.com',
            'password' => Hash::make('password'),
            'role' => 'etudiant',
            'avatar' => null,
        ]);
    }
}

