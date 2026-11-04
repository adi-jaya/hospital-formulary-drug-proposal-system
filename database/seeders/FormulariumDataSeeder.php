<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Proposal;
use App\Models\ReferenceMedicine;
use App\Services\DoctorDirectoryService;
use App\Services\FormularyCsvImporter;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormulariumDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(
        DoctorDirectoryService $doctorService,
        FormularyCsvImporter $importer
    ): void {
        $csvPath = database_path('seeders/data/formulary_medicines.csv');
        if (file_exists('/Users/adijaya/Downloads/REKAP FORMULARIUM RS - daftar obat.csv')) {
            $csvPath = '/Users/adijaya/Downloads/REKAP FORMULARIUM RS - daftar obat.csv';
        }

        // 1. Ensure reference medicines table is populated
        if (ReferenceMedicine::count() === 0) {
            $this->command?->info("Importing reference medicines from {$csvPath}...");
            $importer->importFromPath($csvPath);
        }
        $totalRef = ReferenceMedicine::count();
        $this->command?->info("Reference medicines ready ({$totalRef} rows).");

        // 2. Fetch active doctors from Portal Sifast / directory
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
                'dr. Aira Oklatihana Putri Sp. OG',
                'dr. Angela Bety Ratnasari Sp. JP. FIHA',
                'dr. Arief Fidianto Sp. M M. Ked. Klin',
                'dr. Gagang Guntaran K. M. Ked. Klin. Sp. B',
                'dr. H. Abdulloh Najich SpP. MM. FISR',
                'dr. Lesap Heru Farolan Sp.B',
                'dr. Budi Santoso Sp.An',
                'dr. Dian Paramita Sp.KK',
                'dr. Hendra Setiawan Sp.Rad',
                'dr. Siti Rahmawati Sp.THT-KL',
            ];
        }

        $this->command?->info('Found '.count($doctorNames).' doctors.');

        // 3. Select exactly 50 distinct medicine items
        $medicines = ReferenceMedicine::query()
            ->orderBy('id')
            ->limit(50)
            ->get(['medicine_name', 'principal_name'])
            ->toArray();

        if (count($medicines) < 50) {
            $importer->importFromPath($csvPath);
            $medicines = ReferenceMedicine::query()
                ->orderBy('id')
                ->limit(50)
                ->get(['medicine_name', 'principal_name'])
                ->toArray();
        }

        // 4. Realistic clinical justifications & proposal notes
        $justifications = [
            'Efikasi superior untuk terapi lini pertama pasien rawat inap dengan komorbiditas.',
            'Bioavailabilitas lebih stabil dibanding formularium sebelumnya dan minim efek samping mual.',
            'Pilihan terapi lini kedua untuk kasus resistensi antimikroba empiris.',
            'Onset kerja cepat, sangat dibutuhkan untuk penanganan kegawatdaruratan di IGD.',
            'Formulasi sirup disukai pasien anak dan mempermudah penyesuaian dosis pediatrik.',
            'Kompatibilitas infus stabil untuk terapi pemeliharaan jangka menengah di ICU.',
            'Alternatif terapi dengan batas keamanan lebih luas bagi pasien usia lanjut / geriatri.',
            'Efektivitas klinis setara obat inovator dengan tolerabilitas lambung yang lebih prima.',
            'Kombinasi dosis tetap mempermudah kepatuhan konsumsi obat pasien kronis rawat jalan.',
            'Profil keamanan lebih dianjurkan untuk pasien obstetri dan ginekologi trimester II-III.',
            'Kadar terapeutik darah tercapai lebih cepat dengan risiko interaksi obat yang minimal.',
            'Sangat dibutuhkan sebagai terapi profilaksis bedah dan pencegahan infeksi nosokomial.',
            null,
        ];

        $proposalNotes = [
            'Mohon diprioritaskan konfirmasi ketersediaan pasokan dari distributor resmi.',
            'Diusulkan sebagai substitusi merk sebelumnya yang sering mengalami kekosongan dari pabrikan.',
            'Ketersediaan distributor PBF lokal terkonfirmasi cepat dan stabil (same-day delivery).',
            'Harga e-katalog sangat kompetitif dengan mutu dan efikasi setara inovator.',
            'Rekomendasi tindak lanjut hasil audit medik dan utilisasi obat triwulan sebelumnya.',
            null,
        ];

        // 5. Partition 50 items across 14 proposals (all dates within November 2026)
        // Distribution: 4*7 + 3*6 + 4 = 28 + 18 + 4 = exactly 50 items
        $distribution = [4, 3, 4, 3, 4, 3, 4, 3, 4, 3, 4, 3, 4, 4];

        // Dates strictly within November 2026
        $novemberDates = [
            '2026-11-02',
            '2026-11-03',
            '2026-11-05',
            '2026-11-06',
            '2026-11-09',
            '2026-11-10',
            '2026-11-12',
            '2026-11-13',
            '2026-11-16',
            '2026-11-18',
            '2026-11-20',
            '2026-11-23',
            '2026-11-25',
            '2026-11-27',
        ];

        // 6. Seed Proposals & ProposalItems atomically
        DB::transaction(function () use (
            $distribution,
            $medicines,
            $doctorNames,
            $novemberDates,
            $justifications,
            $proposalNotes
        ): void {
            // Clean existing proposals to allow clean idempotent seeding
            DB::statement('DELETE FROM proposal_items');
            DB::statement('DELETE FROM proposals');

            $medicineIndex = 0;

            foreach ($distribution as $pIdx => $itemCount) {
                $doctorName = $doctorNames[$pIdx % count($doctorNames)];
                $proposalDate = Carbon::parse($novemberDates[$pIdx % count($novemberDates)]);
                $notes = $proposalNotes[$pIdx % count($proposalNotes)];

                $proposal = Proposal::create([
                    'doctor_name' => $doctorName,
                    'proposal_date' => $proposalDate->toDateString(),
                    'period_year' => (int) $proposalDate->format('Y'),
                    'notes' => $notes,
                ]);

                for ($i = 0; $i < $itemCount; $i++) {
                    if (! isset($medicines[$medicineIndex])) {
                        break;
                    }

                    $med = $medicines[$medicineIndex];
                    $justification = $justifications[$medicineIndex % count($justifications)];

                    $proposal->items()->create([
                        'medicine_name' => $med['medicine_name'],
                        'principal_name' => $med['principal_name'] ?? null,
                        'clinical_justification' => $justification,
                    ]);

                    $medicineIndex++;
                }
            }
        });

        $totalProposals = Proposal::count();
        $totalItems = DB::table('proposal_items')->count();

        $this->command?->info("Seeding completed successfully! Created {$totalProposals} proposals with {$totalItems} proposal items (all within November 2026).");
    }
}
