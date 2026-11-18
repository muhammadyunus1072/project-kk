<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractKkDataJob;
use App\Models\KkBatch;
use App\Models\KkSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * KkBatchController
 *
 * Authenticated REST API for multi-file KK batch upload, job status, and cleanup.
 *
 * Endpoints:
 *   POST   /api/kk/batch         - Upload multiple KK files (multipart/form-data)
 *   GET    /api/kk/batch/{batch} - Get batch progress & all submissions
 *   DELETE /api/kk/batch/{batch} - Delete batch & attachments from S3
 */
class KkBatchController extends Controller
{
    /**
     * POST /api/kk/batch
     *
     * Accept 1–20 KK image files, store each to Supabase S3 with checksum,
     * record metadata in kk_submissions, and dispatch background extraction jobs.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files'   => 'required|array|min:1|max:20',
            'files.*' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:20480', // 20MB max per file (supports PDF & images)
        ]);

        $user = $request->user();
        $disk = 'supabase';

        // Create parent batch belonging to the authenticated user
        $batch = KkBatch::create([
            'user_id'     => $user->id,
            'total_files' => count($request->file('files')),
            'status'      => 'pending',
        ]);

        $jobIds = [];

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $mimeType     = $file->getMimeType() ?: 'image/jpeg';
            $fileSize     = $file->getSize();
            $checksum     = hash_file('sha256', $file->getRealPath());

            // Store file to Supabase S3 (or local)
            $storagePath = $file->store("kk-uploads/{$batch->id}", $disk);

            // Create submission record with attachment metadata
            $submission = KkSubmission::create([
                'batch_id'          => $batch->id,
                'user_id'           => $user->id,
                'disk'              => $disk,
                'path'              => $storagePath,
                'original_filename' => $originalName,
                'mimetype'          => $mimeType,
                'filesize'          => $fileSize,
                'checksum'          => $checksum,
                'status'            => 'pending',
            ]);

            // Dispatch extraction job to queue
            ExtractKkDataJob::dispatch($submission);

            $jobIds[] = $submission->id;
        }

        $batch->update(['status' => 'processing']);

        return response()->json([
            'batch_id' => $batch->id,
            'total'    => $batch->total_files,
            'job_ids'  => $jobIds,
        ], 202);
    }

    /**
     * GET /api/kk/batch/{batch}
     *
     * Get batch status and per-file extraction results.
     */
    public function show(Request $request, KkBatch $batch): JsonResponse
    {
        // Enforce user ownership
        abort_unless($batch->user_id === $request->user()->id, 403, 'Unauthorized.');

        $batch->load(['submissions.detail']);

        return response()->json([
            'batch_id'  => $batch->id,
            'status'    => $batch->status,
            'total'     => $batch->total_files,
            'completed' => $batch->completed,
            'failed'    => $batch->failed,
            'jobs'      => $batch->submissions->map(fn(KkSubmission $s) => [
                'id'        => $s->id,
                'detail_id' => $s->detail?->id,
                'is_edited' => (bool) $s->detail?->is_edited,
                'filename'  => $s->original_filename,
                'status'    => $s->status,
                'filesize'  => $s->filesize,
                'checksum'  => $s->checksum,
                'result'    => $s->detail ? $s->detail->toTranslationResult() : $s->extracted_data,
                'error'     => $s->error_message,
            ]),
        ]);
    }

    /**
     * DELETE /api/kk/batch/{batch}
     *
     * Delete files from S3 and cascade delete records.
     */
    public function destroy(Request $request, KkBatch $batch): JsonResponse
    {
        // Enforce user ownership
        abort_unless($batch->user_id === $request->user()->id, 403, 'Unauthorized.');

        $batch->load('submissions');

        foreach ($batch->submissions as $submission) {
            $submission->deleteFile();
        }

        $batchDir = "kk-uploads/{$batch->id}";
        $disk = $batch->submissions->first()?->disk ?? 's3';
        if (Storage::disk($disk)->directoryExists($batchDir)) {
            Storage::disk($disk)->deleteDirectory($batchDir);
        }

        $batchId = $batch->id;
        $batch->delete();

        return response()->json([
            'deleted'  => true,
            'batch_id' => $batchId,
        ]);
    }
}
