<?php

namespace Database\Factories;

use App\Models\Prof;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prof>
 */
class ProfFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Prof::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grades = [
            'Professeur',
            'Maître de Conférences',
            'Maître Assistant',
            'Professeur Agrégé',
            'Chargé de Cours',
        ];

        $specialites = [
            'Mathématiques',
            'Informatique',
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
        ];

        return [
            'matricule' => 'PROF' . fake()->unique()->numberBetween(2020001, 2025999),
            'specialite' => fake()->randomElement($specialites),
            'grade' => fake()->randomElement($grades),
            'avatar' => null,
        ];
    }

    /**
     * Configure the factory to create a user with prof role.
     * This ensures that when we create a Prof, a User with role 'prof' is also created.
     */
    public function configure(): static
    {
        return $this->state(function (array $attributes) {
            // Create user before creating prof to satisfy foreign key constraint
            if (!isset($attributes['user_id'])) {
                $user = User::factory()->prof()->create();
                $attributes['user_id'] = $user->id;
            }
            return $attributes;
        });
    }
}

