<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\TextNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $rawDoctor = $this->input('doctor_name');
        $rawDate = $this->input('proposal_date');
        $rawNotes = $this->input('notes');
        $rawItems = $this->input('items');

        $normalizedDoctor = is_string($rawDoctor) ? TextNormalizer::collapseWhitespace($rawDoctor) : '';
        $normalizedDate = is_string($rawDate) ? TextNormalizer::collapseWhitespace($rawDate) : '';
        $normalizedNotes = is_string($rawNotes) ? TextNormalizer::collapseWhitespace($rawNotes) : null;
        if ($normalizedNotes === '') {
            $normalizedNotes = null;
        }

        $normalizedItems = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $item) {
                if (is_array($item)) {
                    $medName = isset($item['medicine_name']) && is_string($item['medicine_name'])
                        ? TextNormalizer::collapseWhitespace($item['medicine_name'])
                        : '';
                    $principal = isset($item['principal_name']) && is_string($item['principal_name'])
                        ? TextNormalizer::normalizePrincipal($item['principal_name'])
                        : null;
                    $justification = isset($item['clinical_justification']) && is_string($item['clinical_justification'])
                        ? TextNormalizer::collapseWhitespace($item['clinical_justification'])
                        : null;
                    if ($justification === '') {
                        $justification = null;
                    }

                    $normalizedItems[] = [
                        'medicine_name' => $medName,
                        'principal_name' => $principal,
                        'clinical_justification' => $justification,
                    ];
                }
            }
        }

        $this->merge([
            'doctor_name' => $normalizedDoctor,
            'proposal_date' => $normalizedDate,
            'notes' => $normalizedNotes,
            'items' => $normalizedItems,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'doctor_name' => ['required', 'string', 'max:255'],
            'proposal_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.medicine_name' => ['required', 'string', 'max:255'],
            'items.*.principal_name' => ['nullable', 'string', 'max:255'],
            'items.*.clinical_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_name.required' => 'Nama dokter wajib dipilih atau diisi.',
            'proposal_date.required' => 'Tanggal usulan wajib diisi.',
            'proposal_date.date' => 'Format tanggal usulan tidak valid.',
            'notes.max' => 'Catatan tambahan pengusul maksimal 2.000 karakter.',
            'items.required' => 'Minimal 1 obat harus diusulkan.',
            'items.min' => 'Minimal 1 obat harus diusulkan.',
            'items.max' => 'Maksimal 50 obat per formulir usulan.',
            'items.*.medicine_name.required' => 'Nama obat wajib diisi.',
            'items.*.clinical_justification.max' => 'Alasan klinis usulan maksimal 2.000 karakter.',
        ];
    }
}
