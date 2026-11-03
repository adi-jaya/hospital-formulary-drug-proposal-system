<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReferenceMedicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceMedicine>
 */
class ReferenceMedicineFactory extends Factory
{
    protected $model = ReferenceMedicine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medicine_name' => fake()->unique()->words(3, true).' '.fake()->randomElement(['Tab', 'Cap', 'Syrup', 'Inj', 'Drop']),
            'principal_name' => fake()->optional(0.7)->randomElement(['Novell', 'Kalbe Farma', 'Sanbe', 'Dexa Medica', 'Kimia Farma']),
        ];
    }
}
