<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TranslationController
 *
 * Implements the fast Katakana name transliterator and stateless translation
 * functions ported from the reference server.ts.
 */
class TranslationController extends Controller
{
    /**
     * POST /api/katakana-name
     *
     * Port of server.ts /api/katakana-name endpoint.
     * Converts Indonesian person names directly to standard Japanese Katakana.
     */
    public function katakanaName(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $name = trim($validated['name']);
        if (empty($name)) {
            return response()->json([
                'success' => false,
                'error'   => 'Nama wajib disertakan.',
            ], 422);
        }

        try {
            $model = config('gemini.model', 'gemini-2.0-flash');
            $prompt = "Ubah nama orang Indonesia \"{$name}\" ke dalam penulisan Katakana resmi Jepang standar kedutaan/imigrasi. Berikan HANYA teks Katakana murni tanpa penjelasan atau tanda kutip.";

            $response = Gemini::generativeModel(model: $model)->generateContent($prompt);

            $katakana = trim($response->text());

            return response()->json([
                'success'  => true,
                'katakana' => $katakana,
            ]);
        } catch (\Throwable $e) {
            Log::error('Katakana translation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'   => 'Gagal mengubah nama ke Katakana: ' . $e->getMessage(),
            ], 500);
        }
    }
}
