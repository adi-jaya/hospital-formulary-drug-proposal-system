<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\ProposalItemRecapQuery;
use App\Services\ProposalExportService;
use App\Support\SpreadsheetSanitizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalExportController extends Controller
{
    /**
     * Stream token-protected sanitized live CSV feed.
     */
    public function csv(Request $request): StreamedResponse
    {
        $configuredToken = config('formulary.feed_token');
        $requestToken = (string) $request->query('token', '');

        if (! is_string($configuredToken) || $configuredToken === '' || ! hash_equals($configuredToken, $requestToken)) {
            abort(403, 'Akses live CSV feed ditolak: token tidak valid.');
        }

        $filters = [
            'period_year' => $request->query('period_year'),
            'doctor' => $request->query('doctor'),
            'principal' => $request->query('principal'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
        ];

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="proposals_'.now()->format('Ymd_His').'.csv"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->stream(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'No',
                'Tanggal',
                'Tahun Periode',
                'Nama Dokter',
                'Nama Obat',
                'Nama Principle',
                'Waktu Input',
            ]);

            $query = ProposalItemRecapQuery::build($filters);
            $no = 1;

            $query->chunk(250, function ($items) use ($handle, &$no): void {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $no++,
                        Carbon::parse($item->proposal_date)->format('Y-m-d'),
                        $item->period_year,
                        SpreadsheetSanitizer::sanitizeCell($item->doctor_name),
                        SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                        SpreadsheetSanitizer::sanitizeCell($item->principal_name),
                        $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Download styled Excel XLSX report.
     */
    public function xlsx(Request $request, ProposalExportService $exportService): StreamedResponse
    {
        $filters = [
            'period_year' => $request->query('period_year'),
            'doctor' => $request->query('doctor'),
            'principal' => $request->query('principal'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
        ];

        $tempPath = $exportService->generateXlsx($filters);
        $filename = 'rekap_formularium_rs_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($tempPath): void {
            $stream = fopen($tempPath, 'rb');
            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }
            @unlink($tempPath);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
