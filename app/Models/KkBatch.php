<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KkBatch — represents a single batch upload session.
 * One batch can contain 1–20 KK image files.
 *
 * @property int $id
 * @property int $user_id
 * @property int $total_files
 * @property int $completed
 * @property int $failed
 * @property string $status  pending|processing|done|partial_failed
 */
class KkBatch extends Model
{
    protected $fillable = [
        'user_id',
        'total_files',
        'completed',
        'failed',
        'status',
    ];

    /**
     * User who uploaded this batch.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All KkSubmission records belonging to this batch.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(KkSubmission::class, 'batch_id');
    }

    /**
     * Recalculate batch progress from its submissions and persist.
     * Called by ExtractKkDataJob after each job completes or fails.
     */
    public function refreshProgress(): void
    {
        $this->loadMissing('submissions');

        $completed = $this->submissions->where('status', 'done')->count();
        $failed    = $this->submissions->where('status', 'failed')->count();
        $total     = $this->total_files;

        $status = match (true) {
            ($completed + $failed) >= $total && $failed > 0 => 'partial_failed',
            ($completed + $failed) >= $total                => 'done',
            default                                         => 'processing',
        };

        $this->update(compact('completed', 'failed', 'status'));
    }
}
