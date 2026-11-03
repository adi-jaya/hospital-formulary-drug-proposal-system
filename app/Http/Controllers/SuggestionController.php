<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AutocompleteService;
use App\Services\DoctorDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    /**
     * Return autocomplete doctor suggestions.
     */
    public function doctors(Request $request, DoctorDirectoryService $service): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $data = $service->listDoctors($query);

        return response()->json(['data' => $data]);
    }

    /**
     * Return autocomplete medicine suggestions.
     */
    public function medicines(Request $request, AutocompleteService $service): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $data = $service->suggestMedicines($query);

        return response()->json(['data' => $data]);
    }

    /**
     * Return autocomplete principal suggestions.
     */
    public function principals(Request $request, AutocompleteService $service): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $data = $service->suggestPrincipals($query);

        return response()->json(['data' => $data]);
    }
}
