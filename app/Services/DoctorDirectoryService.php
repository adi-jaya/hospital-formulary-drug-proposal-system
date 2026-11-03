<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Proposal;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DoctorDirectoryService
{
    private const CACHE_KEY = 'formulary.doctors.api_list';

    private const SNAPSHOT_FILE = 'doctors_snapshot.json';

    /**
     * Lists active doctors from API/snapshot merged with proposals.
     *
     * @return list<array{name: string, clinic: ?string}>
     */
    public function listDoctors(): array
    {
        $baseDoctors = $this->resolveBaseDoctors();

        /** @var array<string, array{name: string, clinic: ?string}> $merged */
        $merged = [];

        foreach ($baseDoctors as $doc) {
            $name = TextNormalizer::collapseWhitespace($doc['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'name' => $name,
                    'clinic' => isset($doc['clinic']) && $doc['clinic'] !== ''
                        ? TextNormalizer::collapseWhitespace((string) $doc['clinic'])
                        : null,
                ];
            }
        }

        $proposalDoctors = Proposal::query()
            ->select('doctor_name')
            ->distinct()
            ->pluck('doctor_name');

        foreach ($proposalDoctors as $doctorName) {
            $name = TextNormalizer::collapseWhitespace((string) $doctorName);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'name' => $name,
                    'clinic' => null,
                ];
            }
        }

        $result = array_values($merged);
        usort($result, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Resolves active doctors from cache, API, or local snapshot.
     *
     * @return list<array{name: string, clinic: ?string}>
     */
    private function resolveBaseDoctors(): array
    {
        /** @var list<array{name: string, clinic: ?string}>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $apiUrl = (string) config('formulary.doctor_api_url');
        $timeout = (int) config('formulary.doctor_api_timeout', 10);
        $ttlHours = (int) config('formulary.doctor_cache_ttl_hours', 12);

        $apiDoctors = null;

        if ($apiUrl !== '') {
            try {
                $response = Http::timeout($timeout)
                    ->retry(2, 200, throw: false)
                    ->acceptJson()
                    ->get($apiUrl);

                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json) && data_get($json, 'success') === true && is_array(data_get($json, 'data'))) {
                        $activeDoctors = [];
                        /** @var array<string, mixed> $row */
                        foreach (data_get($json, 'data') as $row) {
                            if (! empty($row['isAktif']) && ! empty($row['namaDokter'])) {
                                $name = TextNormalizer::collapseWhitespace((string) $row['namaDokter']);
                                $clinic = isset($row['poli'][0]['namaPoli'])
                                    ? TextNormalizer::collapseWhitespace((string) $row['poli'][0]['namaPoli'])
                                    : null;

                                $activeDoctors[] = [
                                    'name' => $name,
                                    'clinic' => $clinic !== '' ? $clinic : null,
                                ];
                            }
                        }

                        if (! empty($activeDoctors)) {
                            Storage::disk('local')->put(
                                self::SNAPSHOT_FILE,
                                json_encode($activeDoctors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                            );
                            Cache::put(self::CACHE_KEY, $activeDoctors, now()->addHours($ttlHours));
                            $apiDoctors = $activeDoctors;
                        }
                    }
                }
            } catch (Throwable) {
                $apiDoctors = null;
            }
        }

        if ($apiDoctors !== null) {
            return $apiDoctors;
        }

        // Fallback to snapshot
        if (Storage::disk('local')->exists(self::SNAPSHOT_FILE)) {
            try {
                $content = (string) Storage::disk('local')->get(self::SNAPSHOT_FILE);
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    /** @var list<array{name: string, clinic: ?string}> $decoded */
                    Cache::put(self::CACHE_KEY, $decoded, now()->addMinutes(15));

                    return $decoded;
                }
            } catch (Throwable) {
                // Ignore corrupt snapshot
            }
        }

        return [];
    }
}
