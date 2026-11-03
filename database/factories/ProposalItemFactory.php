<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalItem>
 */
class ProposalItemFactory extends Factory
{
    protected $model = ProposalItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'medicine_name' => fake()->randomElement(['Paracetamol 500 Mg Tab', 'Amoxicillin 500 Mg Cap', 'Cefixime 100 Mg Cap', 'Diarvit 100 Mg Tab']),
            'principal_name' => fake()->optional(0.7)->randomElement(['Novell', 'Kalbe Farma', 'Sanbe', 'Dexa Medica', 'Kimia Farma']),
        ];
    }
}
