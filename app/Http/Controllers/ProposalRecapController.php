<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Queries\ProposalItemRecapQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProposalRecapController extends Controller
{
    /**
     * Display proposal recapitulation with multi-dimensional filters and summary metrics.
     */
    public function index(Request $request): View
    {
        $filters = [
            'period_year' => $request->query('period_year'),
            'doctor' => $request->query('doctor'),
            'principal' => $request->query('principal'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
        ];

        $query = ProposalItemRecapQuery::build($filters);

        // Calculate summary metrics
        $totalItems = (clone $query)->count();
        $totalDoctors = (clone $query)->distinct('proposals.doctor_name')->count('proposals.doctor_name');
        $totalProposals = (clone $query)->distinct('proposals.id')->count('proposals.id');

        // Dropdown filter options
        $availableYears = Proposal::query()
            ->distinct()
            ->orderByDesc('period_year')
            ->pluck('period_year');

        $availableDoctors = Proposal::query()
            ->distinct()
            ->orderBy('doctor_name')
            ->pluck('doctor_name');

        $availablePrincipals = ProposalItem::query()
            ->whereNotNull('principal_name')
            ->where('principal_name', '!=', '')
            ->distinct()
            ->orderBy('principal_name')
            ->pluck('principal_name');

        $items = $query->paginate(25)->withQueryString();

        $feedToken = (string) config('formulary.feed_token');
        $csvFeedQuery = array_filter([
            'token' => $feedToken,
            'period_year' => $filters['period_year'] ?? null,
        ]);
        $liveCsvUrl = url('/exports/proposals.csv?'.http_build_query($csvFeedQuery));
        $importDataFormula = '=IMPORTDATA("'.$liveCsvUrl.'")';

        return view('proposals.index', compact(
            'items',
            'filters',
            'totalItems',
            'totalDoctors',
            'totalProposals',
            'availableYears',
            'availableDoctors',
            'availablePrincipals',
            'liveCsvUrl',
            'importDataFormula',
            'feedToken'
        ));
    }
}
