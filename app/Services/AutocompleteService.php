<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProposalItem;
use App\Models\ReferenceMedicine;
use App\Support\TextNormalizer;

class AutocompleteService
{
    /**
     * Suggest medicines merging reference catalog and historical proposal items.
     *
     * @return list<array{medicine_name: string, principal_name: ?string}>
     */
    public function suggestMedicines(string $query = '', int $limit = 15): array
    {
        $cleanQuery = TextNormalizer::collapseWhitespace($query);
        $escaped = addcslashes($cleanQuery, '%_');

        $refQuery = ReferenceMedicine::query()->select(['medicine_name', 'principal_name']);
        if ($escaped !== '') {
            $refQuery->where('medicine_name', 'like', "%{$escaped}%");
        }
        $referenceMedicines = $refQuery->limit(50)->get();

        $itemQuery = ProposalItem::query()->select(['medicine_name', 'principal_name'])->latest('id');
        if ($escaped !== '') {
            $itemQuery->where('medicine_name', 'like', "%{$escaped}%");
        }
        $proposalItems = $itemQuery->limit(100)->get();

        /** @var array<string, array{medicine_name: string, principal_name: ?string}> $map */
        $map = [];

        foreach ($referenceMedicines as $ref) {
            $name = TextNormalizer::collapseWhitespace($ref->medicine_name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            $map[$key] = [
                'medicine_name' => $name,
                'principal_name' => TextNormalizer::normalizePrincipal($ref->principal_name),
            ];
        }

        foreach ($proposalItems as $item) {
            $name = TextNormalizer::collapseWhitespace($item->medicine_name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            $itemPrincipal = TextNormalizer::normalizePrincipal($item->principal_name);

            if (isset($map[$key])) {
                if ($map[$key]['principal_name'] === null && $itemPrincipal !== null) {
                    $map[$key]['principal_name'] = $itemPrincipal;
                }
            } else {
                $map[$key] = [
                    'medicine_name' => $name,
                    'principal_name' => $itemPrincipal,
                ];
            }
        }

        $results = array_values($map);
        $qLower = mb_strtolower($cleanQuery);

        usort($results, function (array $a, array $b) use ($qLower): int {
            if ($qLower !== '') {
                $aPrefix = str_starts_with(mb_strtolower($a['medicine_name']), $qLower);
                $bPrefix = str_starts_with(mb_strtolower($b['medicine_name']), $qLower);

                if ($aPrefix !== $bPrefix) {
                    return $aPrefix ? -1 : 1;
                }
            }

            return strcasecmp($a['medicine_name'], $b['medicine_name']);
        });

        return array_slice($results, 0, $limit);
    }

    /**
     * Suggest distinct principals matching query.
     *
     * @return list<string>
     */
    public function suggestPrincipals(string $query = '', int $limit = 15): array
    {
        $cleanQuery = TextNormalizer::collapseWhitespace($query);
        $escaped = addcslashes($cleanQuery, '%_');

        $refQuery = ReferenceMedicine::query()
            ->select('principal_name')
            ->distinct()
            ->whereNotNull('principal_name')
            ->where('principal_name', '!=', '');
        if ($escaped !== '') {
            $refQuery->where('principal_name', 'like', "%{$escaped}%");
        }
        $refPrincipals = $refQuery->limit(50)->pluck('principal_name')->all();

        $itemQuery = ProposalItem::query()
            ->select('principal_name')
            ->distinct()
            ->whereNotNull('principal_name')
            ->where('principal_name', '!=', '');
        if ($escaped !== '') {
            $itemQuery->where('principal_name', 'like', "%{$escaped}%");
        }
        $itemPrincipals = $itemQuery->limit(100)->pluck('principal_name')->all();

        /** @var array<string, string> $principalsMap */
        $principalsMap = [];

        foreach (array_merge($refPrincipals, $itemPrincipals) as $rawPrincipal) {
            $normalized = TextNormalizer::normalizePrincipal($rawPrincipal);
            if ($normalized === null || $normalized === '') {
                continue;
            }
            $key = mb_strtolower($normalized);
            if (! isset($principalsMap[$key])) {
                $principalsMap[$key] = $normalized;
            }
        }

        $results = array_values($principalsMap);
        $qLower = mb_strtolower($cleanQuery);

        usort($results, function (string $a, string $b) use ($qLower): int {
            if ($qLower !== '') {
                $aPrefix = str_starts_with(mb_strtolower($a), $qLower);
                $bPrefix = str_starts_with(mb_strtolower($b), $qLower);

                if ($aPrefix !== $bPrefix) {
                    return $aPrefix ? -1 : 1;
                }
            }

            return strcasecmp($a, $b);
        });

        return array_slice($results, 0, $limit);
    }
}
