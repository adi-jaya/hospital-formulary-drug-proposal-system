<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\ProposalItem;
use Illuminate\Database\Eloquent\Builder;

class ProposalItemRecapQuery
{
    /**
     * Builds standard recap query joining proposal_items and proposals.
     * Compatible with both SQLite and MariaDB.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ProposalItem>
     */
    public static function build(array $filters = []): Builder
    {
        $query = ProposalItem::query()
            ->join('proposals', 'proposals.id', '=', 'proposal_items.proposal_id')
            ->select([
                'proposal_items.id',
                'proposal_items.proposal_id',
                'proposal_items.medicine_name',
                'proposal_items.principal_name',
                'proposal_items.created_at',
                'proposals.doctor_name',
                'proposals.proposal_date',
                'proposals.period_year',
            ]);

        if (! empty($filters['period_year'])) {
            $query->where('proposals.period_year', (int) $filters['period_year']);
        }

        if (! empty($filters['doctor'])) {
            $query->where('proposals.doctor_name', (string) $filters['doctor']);
        }

        if (! empty($filters['principal'])) {
            $query->where('proposal_items.principal_name', (string) $filters['principal']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('proposals.proposal_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('proposals.proposal_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $term = addcslashes(trim((string) $filters['search']), '%_');
            $query->where(function (Builder $q) use ($term): void {
                $q->where('proposal_items.medicine_name', 'like', "%{$term}%")
                    ->orWhere('proposals.doctor_name', 'like', "%{$term}%")
                    ->orWhere('proposal_items.principal_name', 'like', "%{$term}%");
            });
        }

        return $query
            ->orderByDesc('proposals.proposal_date')
            ->orderByDesc('proposals.id')
            ->orderBy('proposal_items.id');
    }
}
