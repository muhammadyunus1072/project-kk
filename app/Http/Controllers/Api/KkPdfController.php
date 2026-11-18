<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KkSubmission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * KkPdfController
 *
 * Generates Japanese 戸籍謄本 PDF using DomPDF.
 */
class KkPdfController extends Controller
{
    /**
     * GET /api/kk/submissions/{submission}/pdf
     *
     * Download PDF from an existing submission record.
     */
    public function download(Request $request, KkSubmission $submission): Response
    {
        // Enforce user ownership
        abort_unless($submission->user_id === $request->user()->id, 403, 'Unauthorized.');

        $data = $submission->extracted_data;
        if (! $data) {
            abort(404, 'Data terjemahan belum tersedia.');
        }

        $pdf = Pdf::loadView('pdf.koseki-tohon', ['data' => $data])
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isRemoteEnabled'      => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
            ]);

        $fileName = 'Koseki_Tohon_' . ($data['familyCardNumber'] ?? $submission->id) . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * POST /api/kk/render-pdf
     *
     * Render PDF directly from any TranslationResult JSON payload.
     * Useful for downloading edited data from the React editor sheet.
     */
    public function renderCustomPdf(Request $request): Response
    {
        $request->validate([
            'data' => 'required|array',
        ]);

        $data = $request->input('data');

        $pdf = Pdf::loadView('pdf.koseki-tohon', ['data' => $data])
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isRemoteEnabled'      => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
            ]);

        $fileName = 'Koseki_Tohon_' . ($data['familyCardNumber'] ?? 'document') . '.pdf';

        return $pdf->download($fileName);
    }
}
