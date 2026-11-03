<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Services\DoctorDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Storage::fake('local');
});

it('fetches active doctors from portalsifast api, caches them, and writes a local snapshot', function (): void {
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'kdDokter' => '378.24.02.2022',
                    'namaDokter' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
                    'isAktif' => true,
                    'poli' => [['kdPoli' => 'URO', 'namaPoli' => 'Klinik Spesialis Urologi']],
                ],
                [
                    'kdDokter' => '999',
                    'namaDokter' => 'dr. Nonaktif',
                    'isAktif' => false,
                    'poli' => [],
                ],
            ],
        ], 200),
    ]);

    $doctors = app(DoctorDirectoryService::class)->listDoctors();

    expect($doctors)->toHaveCount(1)
        ->and($doctors[0])->toBe([
            'name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
            'clinic' => 'Klinik Spesialis Urologi',
        ])
        ->and(Storage::disk('local')->exists('doctors_snapshot.json'))->toBeTrue();
});

it('falls back to local snapshot without overwriting it when api returns 500 or invalid 200 html/empty payload', function (): void {
    Storage::disk('local')->put('doctors_snapshot.json', json_encode([
        ['name' => 'dr. Delfia Rahmat Gozali Sp. A', 'clinic' => 'Klinik Spesialis Anak'],
    ], JSON_THROW_ON_ERROR));

    Http::fake([
        '*' => Http::sequence()
            ->push('<html>Maintenance</html>', 200)
            ->push(['success' => true, 'data' => []], 200)
            ->push([], 500),
    ]);

    $doctors = app(DoctorDirectoryService::class)->listDoctors();

    expect($doctors)->toHaveCount(1)
        ->and($doctors[0]['name'])->toBe('dr. Delfia Rahmat Gozali Sp. A');

    $snapshotAfter = json_decode((string) Storage::disk('local')->get('doctors_snapshot.json'), true);
    expect($snapshotAfter)->toHaveCount(1);
});

it('merges manual non-polyclinic doctors from proposals without case-insensitive duplicates', function (): void {
    Storage::disk('local')->put('doctors_snapshot.json', json_encode([
        ['name' => 'dr. Delfia Rahmat Gozali Sp. A', 'clinic' => 'Klinik Spesialis Anak'],
    ], JSON_THROW_ON_ERROR));
    Http::fake(['*' => Http::response([], 500)]);

    Proposal::create([
        'doctor_name' => 'dr. delfia rahmat gozali sp. a',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    Proposal::create([
        'doctor_name' => 'dr. Budi Santoso Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);

    $doctors = app(DoctorDirectoryService::class)->listDoctors();

    expect($doctors)->toHaveCount(2)
        ->and(array_column($doctors, 'name'))->toBe([
            'dr. Budi Santoso Sp.An',
            'dr. Delfia Rahmat Gozali Sp. A',
        ]);
});

it('filters doctors matching query by name or clinic', function (): void {
    Storage::disk('local')->put('doctors_snapshot.json', json_encode([
        ['name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin', 'clinic' => 'Klinik Spesialis Urologi'],
        ['name' => 'dr. Delfia Rahmat Gozali Sp. A', 'clinic' => 'Klinik Spesialis Anak'],
    ], JSON_THROW_ON_ERROR));
    Http::fake(['*' => Http::response([], 500)]);

    $byName = app(DoctorDirectoryService::class)->listDoctors('nadhir');
    expect($byName)->toHaveCount(1)
        ->and($byName[0]['name'])->toBe('dr. Ahmad Nadhir Sp. U. M. Ked. Klin');

    $byClinic = app(DoctorDirectoryService::class)->listDoctors('anak');
    expect($byClinic)->toHaveCount(1)
        ->and($byClinic[0]['name'])->toBe('dr. Delfia Rahmat Gozali Sp. A');
});

it('provides json suggestions for doctors via endpoint only when query is provided', function (): void {
    Storage::disk('local')->put('doctors_snapshot.json', json_encode([
        ['name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin', 'clinic' => 'Klinik Spesialis Urologi'],
        ['name' => 'dr. Delfia Rahmat Gozali Sp. A', 'clinic' => 'Klinik Spesialis Anak'],
    ], JSON_THROW_ON_ERROR));
    Http::fake(['*' => Http::response([], 500)]);

    // When query is empty, do not return all doctors by default
    $emptyResponse = $this->getJson(route('suggestions.doctors'))->assertOk();
    expect($emptyResponse->json('data'))->toBe([]);

    $blankResponse = $this->getJson(route('suggestions.doctors', ['q' => '   ']))->assertOk();
    expect($blankResponse->json('data'))->toBe([]);

    // When query matches, return filtered doctors
    $response = $this->getJson(route('suggestions.doctors', ['q' => 'ahmad']))->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('dr. Ahmad Nadhir Sp. U. M. Ked. Klin');
});
