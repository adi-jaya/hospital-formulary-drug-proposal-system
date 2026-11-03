<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [[
                'kdDokter' => '378',
                'namaDokter' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
                'isAktif' => true,
                'poli' => [['kdPoli' => 'URO', 'namaPoli' => 'Klinik Spesialis Urologi']],
            ]],
        ], 200),
    ]);
});

it('renders the proposal form with injected doctor list and today default date', function (): void {
    $this->get(route('proposals.create'))
        ->assertOk()
        ->assertSee('Formulir Usulan Obat Formularium')
        ->assertSee('dr. Ahmad Nadhir Sp. U. M. Ked. Klin')
        ->assertSee(now()->toDateString());
});

it('stores proposal header and multiple items atomically, normalizing spaces and dash-only principals', function (): void {
    $payload = [
        'doctor_name' => '  dr. Budi   Santoso Sp.An ',
        'proposal_date' => '2026-11-15',
        'items' => [
            ['medicine_name' => '  Diarvit   100 Mg Tab ', 'principal_name' => '  Novel  '],
            ['medicine_name' => 'Paracetamol   Drop', 'principal_name' => ' - '],
            ['medicine_name' => 'Ketamin Inj', 'principal_name' => ''],
        ],
    ];

    $this->post(route('proposals.store'), $payload)
        ->assertRedirect(route('proposals.create'))
        ->assertSessionHas('submitted_summary');

    $proposal = Proposal::with('items')->sole();

    expect($proposal->doctor_name)->toBe('dr. Budi Santoso Sp.An')
        ->and($proposal->proposal_date->toDateString())->toBe('2026-11-15')
        ->and($proposal->period_year)->toBe(2026)
        ->and($proposal->items)->toHaveCount(3)
        ->and($proposal->items[0]->medicine_name)->toBe('Diarvit 100 Mg Tab')
        ->and($proposal->items[0]->principal_name)->toBe('Novell')
        ->and($proposal->items[1]->principal_name)->toBeNull()
        ->and($proposal->items[2]->principal_name)->toBeNull();
});

it('prevents duplicate double-submit of identical payload within 15 seconds', function (): void {
    $payload = [
        'doctor_name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
        'proposal_date' => '2026-11-03',
        'items' => [
            ['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell'],
        ],
    ];

    $this->post(route('proposals.store'), $payload)->assertRedirect(route('proposals.create'));
    $this->post(route('proposals.store'), $payload)->assertRedirect(route('proposals.create'));

    expect(Proposal::count())->toBe(1)
        ->and(ProposalItem::count())->toBe(1);
});

it('preserves submitted repeater rows in session old input when validation fails', function (): void {
    $response = $this->from(route('proposals.create'))->post(route('proposals.store'), [
        'doctor_name' => '',
        'proposal_date' => '2026-11-03',
        'items' => [
            ['medicine_name' => 'Obat Satu 10 Mg', 'principal_name' => 'Sanbe'],
            ['medicine_name' => 'Obat Dua 20 Mg', 'principal_name' => 'Kalbe'],
        ],
    ]);

    $response->assertRedirect(route('proposals.create'))
        ->assertSessionHasErrors(['doctor_name']);

    $this->followRedirects($response)
        ->assertSee('Obat Satu 10 Mg')
        ->assertSee('Obat Dua 20 Mg');
});
