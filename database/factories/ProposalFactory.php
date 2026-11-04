<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $proposalDate = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'doctor_name' => 'dr. '.fake()->name().' Sp.'.fake()->randomElement(['A', 'PD', 'B', 'OG', 'An', 'JP']),
            'proposal_date' => $proposalDate->format('Y-m-d'),
            'period_year' => (int) $proposalDate->format('Y'),
            'notes' => fake()->optional(0.6)->sentence(),
        ];
    }
}
