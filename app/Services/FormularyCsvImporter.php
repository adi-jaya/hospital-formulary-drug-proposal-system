<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReferenceMedicine;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FormularyCsvImporter
{
    /**
     * Imports hospital reference medicines from CSV.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importFromPath(string $csvPath): array
    {
        if (! file_exists($csvPath) || ! is_readable($csvPath)) {
            throw new InvalidArgumentException("CSV file not found or not readable: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open CSV file: {$csvPath}");
        }

        /** @var array<string, array{medicine_name: string, principal_name: ?string}> $deduped */
        $deduped = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || ! isset($row[1])) {
                $skipped++;

                continue;
            }

            $rawMedicine = TextNormalizer::collapseWhitespace($row[1]);
            $upperMedicine = strtoupper($rawMedicine);

            // Skip header lines or title rows
            if (
                $rawMedicine === '' ||
                $upperMedicine === 'NAMA OBAT' ||
                str_contains($upperMedicine, 'REKAP USULAN') ||
                str_contains($upperMedicine, 'TAHUN 20')
            ) {
                $skipped++;

                continue;
            }

            $rawPrincipal = $row[3] ?? null;
            $principalName = TextNormalizer::normalizePrincipal($rawPrincipal);
            $key = mb_strtolower($rawMedicine);

            if (isset($deduped[$key])) {
                $skipped++;
                if ($deduped[$key]['principal_name'] === null && $principalName !== null) {
                    $deduped[$key]['principal_name'] = $principalName;
                }
            } else {
                $deduped[$key] = [
                    'medicine_name' => $rawMedicine,
                    'principal_name' => $principalName,
                ];
            }
        }

        fclose($handle);

        DB::transaction(function () use ($deduped): void {
            foreach ($deduped as $item) {
                $existing = ReferenceMedicine::query()
                    ->whereRaw('LOWER(medicine_name) = ?', [mb_strtolower($item['medicine_name'])])
                    ->first();

                if ($existing) {
                    if ($existing->principal_name === null && $item['principal_name'] !== null) {
                        $existing->update(['principal_name' => $item['principal_name']]);
                    }
                } else {
                    ReferenceMedicine::create([
                        'medicine_name' => $item['medicine_name'],
                        'principal_name' => $item['principal_name'],
                    ]);
                }
            }
        });

        return [
            'imported' => count($deduped),
            'skipped' => $skipped,
        ];
    }
}
