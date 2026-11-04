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

it('renders the proposal form with today default date', function (): void {
    $this->get(route('proposals.create'))
        ->assertOk()
        ->assertSee('Formulir Usulan Obat Formularium')
        ->assertSee('Nama Dokter')
        ->assertSee(now()->toDateString());
});

it('stores proposal header and multiple items atomically, normalizing spaces and dash-only principals', function (): void {
    $payload = [
        'doctor_name' => '  dr. Budi   Santoso Sp.An ',
        'proposal_date' => '2026-11-15',
        'notes' => '  Catatan   khusus ketersediaan distributor  ',
        'items' => [
            [
                'medicine_name' => '  Diarvit   100 Mg Tab ',
                'principal_name' => '  Novel  ',
                'clinical_justification' => '  Efikasi   tinggi untuk kasus akut  ',
            ],
            [
                'medicine_name' => 'Paracetamol   Drop',
                'principal_name' => ' - ',
                'clinical_justification' => '   ',
            ],
            [
                'medicine_name' => 'Ketamin Inj',
                'principal_name' => '',
                'clinical_justification' => null,
            ],
        ],
    ];

    $this->post(route('proposals.store'), $payload)
        ->assertRedirect(route('proposals.create'))
        ->assertSessionHas('submitted_summary');

    $proposal = Proposal::with('items')->sole();

    expect($proposal->doctor_name)->toBe('dr. Budi Santoso Sp.An')
        ->and($proposal->proposal_date->toDateString())->toBe('2026-11-15')
        ->and($proposal->period_year)->toBe(2026)
        ->and($proposal->notes)->toBe('Catatan khusus ketersediaan distributor')
        ->and($proposal->items)->toHaveCount(3)
        ->and($proposal->items[0]->medicine_name)->toBe('Diarvit 100 Mg Tab')
        ->and($proposal->items[0]->principal_name)->toBe('Novell')
        ->and($proposal->items[0]->clinical_justification)->toBe('Efikasi tinggi untuk kasus akut')
        ->and($proposal->items[1]->principal_name)->toBeNull()
        ->and($proposal->items[1]->clinical_justification)->toBeNull()
        ->and($proposal->items[2]->principal_name)->toBeNull()
        ->and($proposal->items[2]->clinical_justification)->toBeNull();
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
        'notes' => 'Catatan penting yang harus tetap ada',
        'items' => [
            [
                'medicine_name' => 'Obat Satu 10 Mg',
                'principal_name' => 'Sanbe',
                'clinical_justification' => 'Alasan klinis obat satu',
            ],
            [
                'medicine_name' => 'Obat Dua 20 Mg',
                'principal_name' => 'Kalbe',
                'clinical_justification' => 'Alasan klinis obat dua',
            ],
        ],
    ]);

    $response->assertRedirect(route('proposals.create'))
        ->assertSessionHasErrors(['doctor_name']);

    $this->followRedirects($response)
        ->assertSee('Obat Satu 10 Mg')
        ->assertSee('Obat Dua 20 Mg')
        ->assertSee('Catatan penting yang harus tetap ada')
        ->assertSee('Alasan klinis obat satu')
        ->assertSee('Alasan klinis obat dua');
});

it('validates maximum 2000 characters on notes and clinical justification', function (): void {
    $longText = str_repeat('a', 2001);

    $this->post(route('proposals.store'), [
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'notes' => $longText,
        'items' => [
            [
                'medicine_name' => 'Obat 10 Mg',
                'principal_name' => 'Sanbe',
                'clinical_justification' => $longText,
            ],
        ],
    ])->assertSessionHasErrors(['notes', 'items.0.clinical_justification']);
});
