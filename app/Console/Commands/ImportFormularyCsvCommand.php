<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FormularyCsvImporter;
use Illuminate\Console\Command;

class ImportFormularyCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formulary:import-csv {path? : Path to the formulary CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import reference medicines dictionary from hospital CSV file';

    /**
     * Execute the console command.
     */
    public function handle(FormularyCsvImporter $importer): int
    {
        $pathArgument = $this->argument('path');
        $csvPath = is_string($pathArgument) && $pathArgument !== ''
            ? $pathArgument
            : database_path('seeders/data/formulary_medicines.csv');

        $this->info("Importing reference medicines from: {$csvPath}");

        $result = $importer->importFromPath($csvPath);

        $this->info("Successfully imported/verified {$result['imported']} medicines (skipped {$result['skipped']} lines).");

        return self::SUCCESS;
    }
}
