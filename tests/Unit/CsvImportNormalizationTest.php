<?php

declare(strict_types=1);

use App\Models\ReferenceMedicine;
use App\Services\FormularyCsvImporter;
use App\Support\SpreadsheetSanitizer;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('normalizes whitespace, dash-only principals, and known csv typo aliases', function (): void {
    expect(TextNormalizer::collapseWhitespace("  Diarvit   100 Mg \t Tab "))->toBe('Diarvit 100 Mg Tab')
        ->and(TextNormalizer::normalizePrincipal(' - '))->toBeNull()
        ->and(TextNormalizer::normalizePrincipal('   '))->toBeNull()
        ->and(TextNormalizer::normalizePrincipal('Novel'))->toBe('Novell')
        ->and(TextNormalizer::normalizePrincipal('generik novell'))->toBe('Novell Generik');
});

it('escapes spreadsheet formula injection prefixes without corrupting empty or normal values', function (): void {
    expect(SpreadsheetSanitizer::sanitizeCell('=HYPERLINK("http://evil")'))->toBe("'=HYPERLINK(\"http://evil\")")
        ->and(SpreadsheetSanitizer::sanitizeCell('+628123'))->toBe("'+628123")
        ->and(SpreadsheetSanitizer::sanitizeCell('-100'))->toBe("'-100")
        ->and(SpreadsheetSanitizer::sanitizeCell('@sum(1,2)'))->toBe("'@sum(1,2)")
        ->and(SpreadsheetSanitizer::sanitizeCell(null))->toBe('')
        ->and(SpreadsheetSanitizer::sanitizeCell('Diarvit 100 Mg Tab'))->toBe('Diarvit 100 Mg Tab');
});

it('imports the hospital formulary csv idempotently including multiline quoted rows and comma decimals', function (): void {
    $path = database_path('seeders/data/formulary_medicines.csv');

    $firstRun = app(FormularyCsvImporter::class)->importFromPath($path);
    $secondRun = app(FormularyCsvImporter::class)->importFromPath($path);

    expect($firstRun['imported'])->toBeGreaterThanOrEqual(205)
        ->and(ReferenceMedicine::count())->toBe($firstRun['imported'])
        ->and(ReferenceMedicine::count())->toBe($secondRun['imported'])
        ->and(ReferenceMedicine::where('medicine_name', 'Nuzolam 0,25 Mg')->value('principal_name'))->toBe('Nulab')
        ->and(ReferenceMedicine::where('medicine_name', 'Puspacal')->value('principal_name'))->toBe('Dipa')
        ->and(ReferenceMedicine::where('medicine_name', 'L-Vit D3 Drop')->value('principal_name'))->toBe('Lapi')
        ->and(ReferenceMedicine::where('medicine_name', 'Pyridostigmin 60 Mg Tab')->value('principal_name'))->toBe('Novell')
        ->and(ReferenceMedicine::where('medicine_name', 'Paracetamol Drop')->value('principal_name'))->toBeNull();
});
