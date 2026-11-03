<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\FormularyCsvImporter;
use Illuminate\Database\Seeder;

class ReferenceMedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(FormularyCsvImporter $importer): void
    {
        $csvPath = database_path('seeders/data/formulary_medicines.csv');

        if (file_exists($csvPath)) {
            $importer->importFromPath($csvPath);
        }
    }
}
