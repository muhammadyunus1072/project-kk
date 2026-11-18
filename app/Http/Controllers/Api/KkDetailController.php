<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KkDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * KkDetailController
 *
 * Handles listing, manual creation, typo correction, and PDF regeneration
 * for Kartu Keluarga records.
 */
class KkDetailController extends Controller
{
    /**
     * GET /api/kk/details
     *
     * List all KK details for the authenticated user, ordered by is_submitted ASC, created_at DESC.
     * Unverified items (is_submitted = false) appear first.
     */
    public function index(Request $request): JsonResponse
    {
        $details = KkDetail::where('user_id', $request->user()->id)
            ->orderBy('is_submitted', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'details' => $details,
            'total'   => $details->count(),
            'pending' => $details->where('is_submitted', false)->count(),
            'done'    => $details->where('is_submitted', true)->count(),
        ]);
    }

    /**
     * POST /api/kk/details
     *
     * Option 2: Store manual input data from the original Kartu Keluarga form.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'family_card_number'   => 'required|string|max:30',
            'head_of_family'       => 'required|string|max:255',
            'head_of_family_japan' => 'nullable|string|max:255',
            'address'              => 'required|array',
            'address.street'       => 'nullable|string',
            'address.rtRw'         => 'nullable|string',
            'address.subDistrict'  => 'nullable|string',
            'address.district'     => 'nullable|string',
            'address.regencyCity'  => 'nullable|string',
            'address.province'     => 'nullable|string',
            'address.postalCode'   => 'nullable|string',
            'members'              => 'required|array|min:1',
            'members.*.no'         => 'sometimes|integer',
            'members.*.fullNameOriginal' => 'nullable|string',
            'members.*.fullNameKatakana' => 'nullable|string',
            'members.*.nik'        => 'nullable|string',
            'members.*.gender'     => 'nullable|string',
            'members.*.birthPlace' => 'nullable|string',
            'members.*.birthDate'  => 'nullable|string',
            'members.*.religion'   => 'nullable|string',
            'members.*.education'  => 'nullable|string',
            'members.*.occupation' => 'nullable|string',
            'members.*.maritalStatus' => 'nullable|string',
            'members.*.relationStatus' => 'nullable|string',
            'members.*.fatherName' => 'nullable|string',
            'members.*.motherName' => 'nullable|string',
            'members.*.passportNumber' => 'nullable|string',
            'members.*.stayPermitNumber' => 'nullable|string',
            'members.*.nationality' => 'nullable|string',
            'issued_date'          => 'nullable|string|max:100',
            'issued_place'         => 'nullable|string|max:100',
            'signatory_officer'    => 'nullable|string|max:255',
            'signatory_nip'        => 'nullable|string|max:100',
            'is_submitted'         => 'nullable|boolean',
        ]);

        $detail = KkDetail::create([
            'user_id'              => $request->user()->id,
            'submission_id'        => null,
            'batch_id'             => null,
            'family_card_number'   => $validated['family_card_number'],
            'head_of_family'       => $validated['head_of_family'],
            'head_of_family_japan' => $validated['head_of_family_japan'] ?? $validated['head_of_family'],
            'address'              => $validated['address'],
            'members'              => $validated['members'],
            'issued_date'          => $validated['issued_date'] ?? '',
            'issued_place'         => $validated['issued_place'] ?? '',
            'signatory_officer'    => $validated['signatory_officer'] ?? '',
            'signatory_nip'        => $validated['signatory_nip'] ?? '',
            'raw_gemini_output'    => null,
            'is_edited'            => false,
            'is_submitted'         => $validated['is_submitted'] ?? false,
        ]);

        return response()->json([
            'message' => 'Data KK manual berhasil disimpan ke antrean.',
            'detail'  => $detail,
        ], 201);
    }

    /**
     * GET /api/kk/details/{detail}
     *
     * View all rendered data of a KK, including raw AI output and edit history.
     */
    public function show(Request $request, KkDetail $detail): JsonResponse
    {
        abort_unless($detail->user_id === $request->user()->id, 403, 'Unauthorized.');

        return response()->json([
            'detail' => $detail,
        ]);
    }

    /**
     * PUT /api/kk/details/{detail}
     *
     * Manually edit and correct any typos from AI extraction or manual inputs.
     */
    public function update(Request $request, KkDetail $detail): JsonResponse
    {
        abort_unless($detail->user_id === $request->user()->id, 403, 'Unauthorized.');

        $validated = $request->validate([
            'family_card_number'   => 'sometimes|string|max:30',
            'head_of_family'       => 'sometimes|string|max:255',
            'head_of_family_japan' => 'nullable|string|max:255',
            'address'              => 'sometimes|array',
            'address.street'       => 'nullable|string',
            'address.rtRw'         => 'nullable|string',
            'address.subDistrict'  => 'nullable|string',
            'address.district'     => 'nullable|string',
            'address.regencyCity'  => 'nullable|string',
            'address.province'     => 'nullable|string',
            'address.postalCode'   => 'nullable|string',
            'members'              => 'sometimes|array',
            'members.*.no'         => 'sometimes|integer',
            'members.*.fullNameOriginal' => 'nullable|string',
            'members.*.fullNameKatakana' => 'nullable|string',
            'members.*.nik'        => 'nullable|string',
            'members.*.gender'     => 'nullable|string',
            'members.*.birthPlace' => 'nullable|string',
            'members.*.birthDate'  => 'nullable|string',
            'members.*.religion'   => 'nullable|string',
            'members.*.education'  => 'nullable|string',
            'members.*.occupation' => 'nullable|string',
            'members.*.maritalStatus' => 'nullable|string',
            'members.*.relationStatus' => 'nullable|string',
            'members.*.fatherName' => 'nullable|string',
            'members.*.motherName' => 'nullable|string',
            'members.*.passportNumber' => 'nullable|string',
            'members.*.stayPermitNumber' => 'nullable|string',
            'members.*.nationality' => 'nullable|string',
            'issued_date'          => 'nullable|string|max:100',
            'issued_place'         => 'nullable|string|max:100',
            'signatory_officer'    => 'nullable|string|max:255',
            'signatory_nip'        => 'nullable|string|max:100',
            'is_submitted'         => 'nullable|boolean',
        ]);

        $validated['is_edited'] = true;
        $validated['last_edited_at'] = now();

        $detail->update($validated);

        // Synchronize the parent submission's extracted_data if attached
        $detail->submission?->update([
            'extracted_data' => $detail->toTranslationResult(),
        ]);

        return response()->json([
            'message' => 'Data KK berhasil diperbarui & disimpan.',
            'detail'  => $detail->fresh(),
        ]);
    }

    /**
     * GET /api/kk/details/{detail}/pdf
     *
     * Regenerate the official A4 Japanese 戸籍謄本 PDF on-demand
     * reflecting the latest manual corrections, and mark as checked & submitted.
     */
    public function regeneratePdf(Request $request, KkDetail $detail): Response
    {
        abort_unless($detail->user_id === $request->user()->id, 403, 'Unauthorized.');

        // Mark as checked manual & successfully rendered to PDF
        $detail->update([
            'is_submitted' => true,
        ]);

        $data = $detail->toTranslationResult();

        $pdf = Pdf::loadView('pdf.koseki-tohon', compact('data'))
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isRemoteEnabled'      => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
            ]);

        $cleanNoKk = preg_replace('/[^0-9]/', '', $detail->family_card_number) ?: 'KK';
        $fileName = "Koseki_Tohon_{$cleanNoKk}" . ($detail->is_edited ? '_corrected' : '') . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * POST /api/kk/details/{detail}/mark-submitted
     *
     * Mark a KK detail as verified and submitted (after successful client-side JS PDF export).
     */
    public function markSubmitted(Request $request, KkDetail $detail): JsonResponse
    {
        abort_unless($detail->user_id === $request->user()->id, 403, 'Unauthorized.');

        $detail->update([
            'is_submitted'   => true,
            'last_edited_at' => now(),
        ]);

        return response()->json([
            'message' => 'Dokumen berhasil diverifikasi & ditandai selesai.',
            'detail'  => $detail->fresh(),
        ]);
    }

    /**
     * POST /api/kk/details/{detail}/toggle-submitted
     *
     * Manually toggle the checked/submitted status of a document.
     */
    public function toggleSubmitted(Request $request, KkDetail $detail): JsonResponse
    {
        abort_unless($detail->user_id === $request->user()->id, 403, 'Unauthorized.');

        $detail->update([
            'is_submitted' => !$detail->is_submitted,
        ]);

        return response()->json([
            'message' => 'Status verifikasi dokumen berhasil diperbarui.',
            'detail'  => $detail->fresh(),
        ]);
    }

    /**
     * DELETE /api/kk/details/{detail}
     *
     * Delete a KK detail from the user's queue.
     */
    public function destroy(Request $request, KkDetail $detail): JsonResponse
    {
        abort_unless($detail->user_id === $request->user()->id, 403, 'Unauthorized.');

        $detail->delete();

        return response()->json([
            'message' => 'Dokumen KK berhasil dihapus dari antrean.',
        ]);
    }
}
