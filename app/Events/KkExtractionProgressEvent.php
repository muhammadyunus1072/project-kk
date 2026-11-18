<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * KkExtractionProgressEvent
 *
 * Broadcasts in real-time over Pusher WebSockets to notify the React frontend
 * whenever a KK image extraction changes status (processing, done, failed).
 *
 * Broadcast channel: private-user.{userId}
 * Event name:        kk.extraction.progress
 */
class KkExtractionProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int|string $userId,
        public readonly string $batchId,
        public readonly string $submissionId,
        public readonly string $filename,
        public readonly string $status,           // 'processing' | 'done' | 'failed'
        public readonly ?array $extractedData = null, // TranslationResult JSON
        public readonly ?string $errorMessage = null,
        public readonly int $completedCount = 0,
        public readonly int $failedCount = 0,
        public readonly int $totalCount = 0,
        public readonly string $batchStatus = 'processing',
        public readonly ?string $detailId = null
    ) {}

    /**
     * Broadcast to private channel scoped to the authenticated user.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    /**
     * The event's broadcast name for Laravel Echo listener.
     */
    public function broadcastAs(): string
    {
        return 'kk.extraction.progress';
    }

    /**
     * Data payload to be broadcasted to the browser.
     */
    public function broadcastWith(): array
    {
        return [
            'batch_id'       => $this->batchId,
            'submission_id'  => $this->submissionId,
            'detail_id'      => $this->detailId,
            'filename'       => $this->filename,
            'status'         => $this->status,
            'result'         => $this->extractedData,
            'error'          => $this->errorMessage,
            'completed'      => $this->completedCount,
            'failed'         => $this->failedCount,
            'total'          => $this->totalCount,
            'batch_status'   => $this->batchStatus,
            'timestamp'      => now()->toIso8601String(),
        ];
    }
}
