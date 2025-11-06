<?php

namespace Database\Seeders;

use App\Models\Etudiant;
use App\Models\Prof;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer les comptes initiaux (admin, prof, étudiant)
        // $this->call([
        //     InitialUsersSeeder::class,
        // ]);

        // // Créer des utilisateurs avec leurs relations complètes via les factories
        // // Créer 5 professeurs
        // Prof::factory(5)->create();

        // Créer 20 étudiants
        Etudiant::factory(20)->create();

        // // Créer 2 admins supplémentaires
        // User::factory(2)->admin()->create();
    }
}
