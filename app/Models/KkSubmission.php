<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * KkSubmission — represents a single KK image file attachment and its translation result.
 *
 * @property string $id
 * @property string $batch_id
 * @property int $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mimetype
 * @property int $filesize
 * @property string $checksum
 * @property string $status  pending|processing|done|failed
 * @property array|null $extracted_data
 * @property string|null $error_message
 * @property int $attempts
 */
class KkSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'batch_id',
        'user_id',
        'disk',
        'path',
        'original_filename',
        'mimetype',
        'filesize',
        'checksum',
        'status',
        'extracted_data',
        'error_message',
        'attempts',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'filesize'       => 'integer',
        'attempts'       => 'integer',
    ];

    /**
     * Parent batch this submission belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(KkBatch::class, 'batch_id');
    }

    /**
     * User who owns this submission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Detail rendered data and manual correction record for this submission.
     */
    public function detail(): HasOne
    {
        return $this->hasOne(KkDetail::class, 'submission_id');
    }

    /**
     * Get image file binary content from configured disk (S3 / local).
     */
    public function getFileContents(): ?string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    /**
     * Delete stored file from disk.
     */
    public function deleteFile(): bool
    {
        if (Storage::disk($this->disk)->exists($this->path)) {
            return Storage::disk($this->disk)->delete($this->path);
        }
        return true;
    }
}
