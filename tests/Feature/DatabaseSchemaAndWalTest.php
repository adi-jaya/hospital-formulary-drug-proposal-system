<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ReferenceMedicine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('configures sqlite connection defaults for wal, busy_timeout 5000, and foreign keys', function (): void {
    expect(config('database.connections.sqlite.busy_timeout'))->toBe(5000)
        ->and(config('database.connections.sqlite.journal_mode'))->toBe('WAL')
        ->and(config('database.connections.sqlite.synchronous'))->toBe('NORMAL')
        ->and(config('database.connections.sqlite.foreign_key_constraints'))->toBeTrue()
        ->and(config('app.timezone'))->toBe('Asia/Jakarta');
});

it('persists proposals with items and cascades on delete', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);

    $proposal->items()->createMany([
        ['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell'],
        ['medicine_name' => 'Paracetamol Drop', 'principal_name' => null],
    ]);

    ReferenceMedicine::create([
        'medicine_name' => 'Diarvit 100 Mg Tab',
        'principal_name' => 'Novell',
    ]);

    expect($proposal->items)->toHaveCount(2)
        ->and($proposal->items->first()->proposal->is($proposal))->toBeTrue();

    $proposal->delete();

    expect(ProposalItem::count())->toBe(0)
        ->and(ReferenceMedicine::count())->toBe(1);
});
