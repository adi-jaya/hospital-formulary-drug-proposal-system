<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays flattened proposal items and filters by period_year, doctor, principal, date range, and search keyword', function (): void {
    config(['formulary.feed_token' => 'secret-feed-token-2026']);

    $p2025 = Proposal::create([
        'doctor_name' => 'dr. Lama Sp.PD',
        'proposal_date' => '2025-11-10',
        'period_year' => 2025,
    ]);
    $p2025->items()->create(['medicine_name' => 'Obat Arsip 2025', 'principal_name' => 'Kalbe']);

    $p2026a = Proposal::create([
        'doctor_name' => 'dr. Delfia Rahmat Gozali Sp. A',
        'proposal_date' => '2026-11-02',
        'period_year' => 2026,
    ]);
    $p2026a->items()->create(['medicine_name' => 'Anazinc Sirup', 'principal_name' => 'Guardian']);

    $p2026b = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
        'notes' => 'Catatan dokter anestesi terkait premedikasi',
    ]);
    $p2026b->items()->create([
        'medicine_name' => 'Norevell 250 Ml',
        'principal_name' => 'Novell',
        'clinical_justification' => 'Keunggulan stabilitas infus',
    ]);

    $this->get(route('proposals.index'))
        ->assertOk()
        ->assertSee('Alasan Klinis')
        ->assertSee('Catatan Pengusul')
        ->assertSee('Obat Arsip 2025')
        ->assertSee('Anazinc Sirup')
        ->assertSee('Norevell 250 Ml')
        ->assertSee('Keunggulan stabilitas infus')
        ->assertSee('Catatan dokter anestesi terkait premedikasi')
        ->assertSee('=IMPORTDATA(', false)
        ->assertSee('secret-feed-token-2026', false);

    $this->get(route('proposals.index', ['period_year' => 2026]))
        ->assertOk()
        ->assertSee('Anazinc Sirup')
        ->assertSee('Norevell 250 Ml')
        ->assertDontSee('Obat Arsip 2025');

    $this->get(route('proposals.index', ['doctor' => 'dr. Budi Sp.An']))
        ->assertOk()
        ->assertSee('Norevell 250 Ml')
        ->assertDontSee('Anazinc Sirup');

    $this->get(route('proposals.index', ['principal' => 'Guardian']))
        ->assertOk()
        ->assertSee('Anazinc Sirup')
        ->assertDontSee('Norevell 250 Ml');

    $this->get(route('proposals.index', ['search' => 'Norevell']))
        ->assertOk()
        ->assertSee('Norevell 250 Ml')
        ->assertDontSee('Anazinc Sirup');
});

it('renders custom select components instead of native select tags for doctor and principal filters', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Delfia Rahmat Gozali Sp. A',
        'proposal_date' => '2026-11-02',
        'period_year' => 2026,
    ]);
    $proposal->items()->create(['medicine_name' => 'Anazinc Sirup', 'principal_name' => 'Guardian']);

    $response = $this->get(route('proposals.index', [
        'doctor' => 'dr. Delfia Rahmat Gozali Sp. A',
        'principal' => 'Guardian',
    ]))->assertOk();

    // Verify native select elements are NOT used
    $response->assertDontSee('<select', false);

    // Verify customSelect Alpine components are initialized
    $response->assertSee('x-data="customSelect(', false);
    $response->assertSee("name: 'doctor'", false);
    $response->assertSee("name: 'principal'", false);
    $response->assertSee("value: 'dr. Delfia Rahmat Gozali Sp. A'", false);
    $response->assertSee("value: 'Guardian'", false);
});
