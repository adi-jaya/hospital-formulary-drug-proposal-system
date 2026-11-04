<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\ProposalItemRecapQuery;
use App\Support\SpreadsheetSanitizer;
use Carbon\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ProposalExportService
{
    /**
     * Generates a styled Excel XLSX file and returns the temporary file path.
     *
     * @param  array<string, mixed>  $filters
     */
    public function generateXlsx(array $filters = []): string
    {
        $options = new Options;
        $options->setColumnWidth(8, 1);   // No
        $options->setColumnWidth(14, 2);  // Tanggal
        $options->setColumnWidth(14, 3);  // Tahun Periode
        $options->setColumnWidth(35, 4);  // Nama Dokter
        $options->setColumnWidth(40, 5);  // Nama Obat
        $options->setColumnWidth(25, 6);  // Nama Principle
        $options->setColumnWidth(45, 7);  // Alasan Klinis
        $options->setColumnWidth(35, 8);  // Catatan Pengusul

        $tempPath = tempnam(sys_get_temp_dir(), 'formulary_export_').'.xlsx';

        $writer = new Writer($options);
        $writer->openToFile($tempPath);

        // Border styles
        $thinGrayBorder = new Border(
            new BorderPart(Border::TOP, Color::rgb(226, 232, 240), Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, Color::rgb(226, 232, 240), Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, Color::rgb(226, 232, 240), Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::rgb(226, 232, 240), Border::WIDTH_THIN, Border::STYLE_SOLID)
        );

        // Header and Cell Styles
        $titleStyle = (new Style)
            ->setFontBold()
            ->setFontSize(13)
            ->setFontColor(Color::rgb(6, 95, 70));

        $metaStyle = (new Style)
            ->setFontItalic()
            ->setFontSize(10)
            ->setFontColor(Color::rgb(100, 116, 139));

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(6, 95, 70))
            ->setBorder($thinGrayBorder);

        $dataStyle = (new Style)
            ->setFontSize(10)
            ->setBorder($thinGrayBorder);

        // 1. Title Rows
        $writer->addRow(Row::fromValues([
            'REKAPITULASI USULAN OBAT FORMULARIUM RS AISYIYAH SITI FATIMAH TULANGAN',
        ], $titleStyle));

        $nowStr = Carbon::now('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB';
        $periodInfo = ! empty($filters['period_year']) ? "Periode Tahun: {$filters['period_year']} • " : '';
        $writer->addRow(Row::fromValues([
            "{$periodInfo}Dicetak pada: {$nowStr}",
        ], $metaStyle));

        // Spacer Row
        $writer->addRow(Row::fromValues(['']));

        // 2. Table Column Headers
        $writer->addRow(Row::fromValues([
            'No',
            'Tanggal',
            'Tahun Periode',
            'Nama Dokter',
            'Nama Obat',
            'Nama Principle',
            'Alasan Klinis',
            'Catatan Pengusul',
        ], $headerStyle));

        // 3. Data Rows
        $query = ProposalItemRecapQuery::build($filters);
        $no = 1;

        $query->chunk(250, function ($items) use ($writer, $dataStyle, &$no): void {
            foreach ($items as $item) {
                $principal = $item->principal_name !== null
                    ? SpreadsheetSanitizer::sanitizeCell($item->principal_name)
                    : '-';
                $justification = $item->clinical_justification !== null
                    ? SpreadsheetSanitizer::sanitizeCell($item->clinical_justification)
                    : '-';
                $notes = $item->notes !== null
                    ? SpreadsheetSanitizer::sanitizeCell($item->notes)
                    : '-';

                $writer->addRow(Row::fromValues([
                    $no++,
                    Carbon::parse($item->proposal_date)->format('d/m/Y'),
                    $item->period_year,
                    SpreadsheetSanitizer::sanitizeCell($item->doctor_name),
                    SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                    $principal,
                    $justification,
                    $notes,
                ], $dataStyle));
            }
        });

        $writer->close();

        return $tempPath;
    }
}
