<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ReferenceMedicine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns paired principal_name from reference_medicines and newly submitted proposal_items without duplicates or status badges', function (): void {
    ReferenceMedicine::create([
        'medicine_name' => 'Diarvit 100 Mg Tab',
        'principal_name' => 'Novell',
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $proposal->items()->createMany([
        ['medicine_name' => 'diarvit 100 mg tab', 'principal_name' => 'Novell'],
        ['medicine_name' => 'Precedex 200 Mcg Inj', 'principal_name' => 'Pfizer'],
    ]);

    $medResponse = $this->getJson(route('suggestions.medicines', ['q' => 'diarvit']))->assertOk();
    expect($medResponse->json('data'))->toHaveCount(1)
        ->and($medResponse->json('data.0'))->toBe([
            'medicine_name' => 'Diarvit 100 Mg Tab',
            'principal_name' => 'Novell',
        ])
        ->and($medResponse->json('data.0'))->not->toHaveKey('status');

    $newMedResponse = $this->getJson(route('suggestions.medicines', ['q' => 'precedex']))->assertOk();
    expect($newMedResponse->json('data.0'))->toBe([
        'medicine_name' => 'Precedex 200 Mcg Inj',
        'principal_name' => 'Pfizer',
    ]);
});

it('returns distinct non-empty principals matching query with prefix priority', function (): void {
    ReferenceMedicine::create(['medicine_name' => 'Obat A', 'principal_name' => 'Sanbe']);
    ReferenceMedicine::create(['medicine_name' => 'Obat B', 'principal_name' => null]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $proposal->items()->createMany([
        ['medicine_name' => 'Obat C', 'principal_name' => 'sanbe'],
        ['medicine_name' => 'Obat D', 'principal_name' => 'Kalbe Farma'],
        ['medicine_name' => 'Obat E', 'principal_name' => null],
    ]);

    $response = $this->getJson(route('suggestions.principals', ['q' => 'sa']))->assertOk();

    expect($response->json('data'))->toBe(['Sanbe']);
});
