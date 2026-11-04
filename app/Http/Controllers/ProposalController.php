<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalRequest;
use App\Models\Proposal;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProposalController extends Controller
{
    /**
     * Show the formulary proposal creation form.
     */
    public function create(): View
    {
        return view('proposals.create');
    }

    /**
     * Store submitted proposal and items atomically with idempotency protection.
     */
    public function store(StoreProposalRequest $request): RedirectResponse
    {
        /** @var array{doctor_name: string, proposal_date: string, notes: ?string, items: list<array{medicine_name: string, principal_name: ?string, clinical_justification: ?string}>} $validated */
        $validated = $request->validated();

        $carbonDate = Carbon::parse($validated['proposal_date']);

        $normalizedPayload = [
            'doctor_name' => $validated['doctor_name'],
            'proposal_date' => $carbonDate->toDateString(),
            'notes' => $validated['notes'] ?? null,
            'items' => array_map(fn (array $item): array => [
                'medicine_name' => $item['medicine_name'],
                'principal_name' => $item['principal_name'] ?? null,
                'clinical_justification' => $item['clinical_justification'] ?? null,
            ], $validated['items']),
        ];

        $fingerprint = hash('sha256', json_encode($normalizedPayload, JSON_THROW_ON_ERROR));
        $acquired = Cache::add("formulary:idempotency:{$fingerprint}", true, now()->addSeconds(15));

        if ($acquired) {
            DB::transaction(function () use ($normalizedPayload, $carbonDate): void {
                $proposal = Proposal::create([
                    'doctor_name' => $normalizedPayload['doctor_name'],
                    'proposal_date' => $normalizedPayload['proposal_date'],
                    'period_year' => (int) $carbonDate->format('Y'),
                    'notes' => $normalizedPayload['notes'],
                ]);

                $proposal->items()->createMany($normalizedPayload['items']);
            });
        }

        return redirect()->route('proposals.create')->with('submitted_summary', [
            'doctor_name' => $validated['doctor_name'],
            'item_count' => count($validated['items']),
            'proposal_date' => $validated['proposal_date'],
        ]);
    }
}
