<?php

namespace Database\Seeders;

use App\Models\Etudiant;
use App\Models\Prof;
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
        ]);

        // Créer un compte professeur avec ses informations
        $profUser = User::create([
            'name' => 'Professeur',
            'email' => 'prof@example.com',
            'password' => Hash::make('password'),
            'role' => 'prof',
        ]);

        Prof::create([
            'user_id' => $profUser->id,
            'matricule' => 'PROF2024001',
            'specialite' => 'Informatique',
            'grade' => 'Maître de Conférences',
            'avatar' => null,
        ]);

        // Créer un compte étudiant avec ses informations
        $etudiantUser = User::create([
            'name' => 'Étudiant',
            'email' => 'etudiant@example.com',
            'password' => Hash::make('password'),
            'role' => 'etudiant',
        ]);

        Etudiant::create([
            'user_id' => $etudiantUser->id,
            'matricule' => 'ETU2024001',
            'filiere' => 'Informatique',
            'niveau' => 'L3',
            'avatar' => null,
        ]);
    }
}

