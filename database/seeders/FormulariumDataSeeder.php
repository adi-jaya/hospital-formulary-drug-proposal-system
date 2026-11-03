<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Proposal;
use App\Models\ReferenceMedicine;
use App\Services\DoctorDirectoryService;
use App\Services\FormularyCsvImporter;
use App\Support\SpreadsheetSanitizer;
use App\Support\TextNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use SplFileObject;

class FormulariumDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(
        DoctorDirectoryService $doctorService,
        FormularyCsvImporter $importer
    ): void {
        $csvPath = '/Users/adijaya/Downloads/REKAP FORMULARIUM RS - daftar obat.csv';

        if (! file_exists($csvPath)) {
            $csvPath = database_path('seeders/data/formulary_medicines.csv');
        }

        $this->command?->info("Importing reference medicines from {$csvPath}...");

        // 1. Populate reference medicines table
        $importer->importFromPath($csvPath);
        $totalRef = ReferenceMedicine::count();
        $this->command?->info("Reference medicines ready ({$totalRef} rows).");

        // 2. Fetch doctors from API
        $this->command?->info('Fetching active doctors from Portal Sifast API...');
        $doctors = $doctorService->listDoctors();
        $doctorNames = array_column($doctors, 'name');

        if (empty($doctorNames)) {
            $this->command?->warn('No doctors fetched from API, using fallback list.');
            $doctorNames = [
                'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
                'dr. Delfia Rahmat Gozali Sp. A',
                'Dr. dr. M. Fathi Ilmawan Sp.PD. FINASIM',
                'dr. Faishol Hamdani Sp. N. FINA',
            ];
        }

        $this->command?->info('Found '.count($doctorNames).' doctors.');

        // 3. Parse CSV rows
        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $assignedGroups = [];
        $unassignedItems = [];

        foreach ($file as $row) {
            if (! is_array($row) || count($row) < 4) {
                continue;
            }

            $rawMedicine = trim((string) ($row[1] ?? ''));
            $rawPrincipal = trim((string) ($row[3] ?? ''));
            $rawUser = trim((string) ($row[6] ?? ''));

            if ($rawMedicine === '' || $rawMedicine === 'NAMA OBAT' || str_contains($rawMedicine, 'REKAP')) {
                continue;
            }

            $medicineName = SpreadsheetSanitizer::sanitizeCell(TextNormalizer::collapseWhitespace($rawMedicine));
            $principalName = TextNormalizer::normalizePrincipal($rawPrincipal);
            if ($principalName !== null) {
                $principalName = SpreadsheetSanitizer::sanitizeCell($principalName);
            }

            $item = [
                'medicine_name' => $medicineName,
                'principal_name' => $principalName,
            ];

            if ($rawUser !== '') {
                $userLines = preg_split('/\r\n|\n|\r/', $rawUser) ?: [];
                foreach ($userLines as $userLine) {
                    $u = trim($userLine);
                    if ($u === '') {
                        continue;
                    }

                    $resolvedDoctor = $this->resolveDoctorName($u, $doctorNames);
                    $assignedGroups[$resolvedDoctor][] = $item;
                }
            } else {
                $unassignedItems[] = $item;
            }
        }

        // 4. Distribute unassigned items evenly across all active doctors
        $doctorCount = count($doctorNames);
        $distributedProposals = $assignedGroups;

        foreach ($doctorNames as $docName) {
            if (! isset($distributedProposals[$docName])) {
                $distributedProposals[$docName] = [];
            }
        }

        foreach ($unassignedItems as $idx => $item) {
            $assignedDoc = $doctorNames[$idx % $doctorCount];
            $distributedProposals[$assignedDoc][] = $item;
        }

        // 5. Seed Proposals & ProposalItems inside a transaction
        DB::transaction(function () use ($distributedProposals): void {
            // Clean existing proposals to allow idempotent seeding
            DB::statement('DELETE FROM proposal_items');
            DB::statement('DELETE FROM proposals');

            $baseDate = Carbon::parse('2026-11-01');
            $dayOffset = 0;

            foreach ($distributedProposals as $doctorName => $items) {
                if (empty($items)) {
                    continue;
                }

                $proposalDate = $baseDate->copy()->addDays($dayOffset % 14);
                $dayOffset++;

                $proposal = Proposal::create([
                    'doctor_name' => $doctorName,
                    'proposal_date' => $proposalDate->toDateString(),
                    'period_year' => (int) $proposalDate->format('Y'),
                ]);

                foreach ($items as $item) {
                    $proposal->items()->create([
                        'medicine_name' => $item['medicine_name'],
                        'principal_name' => $item['principal_name'],
                    ]);
                }
            }
        });

        $totalProposals = Proposal::count();
        $totalItems = DB::table('proposal_items')->count();

        $this->command?->info("Seeding completed successfully! Created {$totalProposals} proposals with {$totalItems} proposal items.");
    }

    /**
     * Resolve user string to official doctor name.
     *
     * @param  list<string>  $doctorNames
     */
    private function resolveDoctorName(string $userString, array $doctorNames): string
    {
        $u = trim($userString);

        if (strcasecmp($u, 'dr. fatih') === 0) {
            return 'Dr. dr. M. Fathi Ilmawan Sp.PD. FINASIM';
        }

        $clean = preg_replace('/^dr\.?\s*/i', '', $u) ?? '';

        foreach ($doctorNames as $name) {
            if (stripos($name, $clean) !== false) {
                return $name;
            }
        }

        return $u;
    }
}
