<?php

namespace Database\Factories;

use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Etudiant>
 */
class EtudiantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Etudiant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filieres = [
            'Informatique',
            'Mathématiques',
            'Physique',
            'Chimie',
            'Biologie',
            'Économie',
            'Gestion',
            'Droit',
            'Littérature',
            'Histoire',
            'Géographie',
            'Langues',
            'Génie Civil',
            'Génie Électrique',
            'Génie Mécanique',
        ];

        $niveaux = [
            'L1',
            'L2',
            'L3',
            'M1',
            'M2',
        ];

        return [
            'matricule' => 'ETU' . fake()->unique()->numberBetween(2020, 2025) . str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'filiere' => fake()->randomElement($filieres),
            'niveau' => fake()->randomElement($niveaux),
            'avatar' => null,
        ];
    }

    /**
     * Configure the factory to create a user with etudiant role.
     * This ensures that when we create an Etudiant, a User with role 'etudiant' is also created.
     */
    public function configure(): static
    {
        return $this->state(function (array $attributes) {
            // Create user before creating etudiant to satisfy foreign key constraint
            if (!isset($attributes['user_id'])) {
                $user = User::factory()->etudiant()->create();
                $attributes['user_id'] = $user->id;
            }
            return $attributes;
        });
    }
}

