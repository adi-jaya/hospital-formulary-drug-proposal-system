<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('rejects live csv requests when feed token is missing, empty in config, or invalid', function (): void {
    config(['formulary.feed_token' => '']);
    $this->get(route('exports.proposals.csv', ['token' => 'anything']))->assertForbidden();

    config(['formulary.feed_token' => 'valid-secret-token']);
    $this->get(route('exports.proposals.csv'))->assertForbidden();
    $this->get(route('exports.proposals.csv', ['token' => 'wrong-token']))->assertForbidden();
});

it('streams sanitized utf-8 csv for valid token and respects optional period_year filter', function (): void {
    config(['formulary.feed_token' => 'valid-secret-token']);

    $p2025 = Proposal::create([
        'doctor_name' => 'dr. Lama',
        'proposal_date' => '2025-11-01',
        'period_year' => 2025,
    ]);
    $p2025->items()->create(['medicine_name' => 'Obat 2025', 'principal_name' => 'Sanbe']);

    $p2026 = Proposal::create([
        'doctor_name' => 'dr. Delfia Sp.A',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $p2026->items()->create(['medicine_name' => '=CMD("calc")', 'principal_name' => null]);

    $response = $this->get(route('exports.proposals.csv', [
        'token' => 'valid-secret-token',
        'period_year' => 2026,
    ]))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $csvContent = $response->streamedContent();
    expect($csvContent)
        ->toContain('No,Tanggal,"Tahun Periode","Nama Dokter","Nama Obat","Nama Principle","Waktu Input"')
        ->toContain("'=CMD(\"\"calc\"\")")
        ->not->toContain('Obat 2025');
});

it('exports a valid styled xlsx file matching active filters', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Diarvit 100 Mg Tab',
        'principal_name' => 'Novell',
    ]);

    $response = $this->get(route('exports.proposals.xlsx', ['period_year' => 2026]))->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmpFile = tempnam(sys_get_temp_dir(), 'formulary_test_').'.xlsx';
    file_put_contents($tmpFile, $response->streamedContent());

    $reader = new Reader;
    $reader->open($tmpFile);

    $allText = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->toArray() as $cellValue) {
                $allText[] = (string) $cellValue;
            }
        }
    }
    $reader->close();
    @unlink($tmpFile);

    expect($allText)->toContain('REKAPITULASI USULAN OBAT FORMULARIUM RS AISYIYAH SITI FATIMAH TULANGAN')
        ->and($allText)->toContain('dr. Ahmad Nadhir Sp. U. M. Ked. Klin')
        ->and($allText)->toContain('Diarvit 100 Mg Tab')
        ->and($allText)->toContain('Novell');
});

it('exports null principal_name as dash without escaping it to apostrophe dash in xlsx', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Paracetamol Drop',
        'principal_name' => null,
    ]);

    $response = $this->get(route('exports.proposals.xlsx'))->assertOk();

    $tmpFile = tempnam(sys_get_temp_dir(), 'formulary_null_test_').'.xlsx';
    file_put_contents($tmpFile, $response->streamedContent());

    $reader = new Reader;
    $reader->open($tmpFile);

    $allCells = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->toArray() as $cellValue) {
                $allCells[] = (string) $cellValue;
            }
        }
    }
    $reader->close();
    @unlink($tmpFile);

    expect($allCells)->toContain('-')
        ->and($allCells)->not->toContain("'-");
});
