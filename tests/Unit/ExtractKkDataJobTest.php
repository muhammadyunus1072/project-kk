<?php

namespace Tests\Unit;

use App\Jobs\ExtractKkDataJob;
use App\Models\KkBatch;
use App\Models\KkSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractKkDataJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_instantiated_with_submission(): void
    {
        $user = User::factory()->create();

        $batch = KkBatch::create([
            'user_id'     => $user->id,
            'total_files' => 1,
            'status'      => 'pending',
        ]);

        $submission = KkSubmission::create([
            'batch_id'          => $batch->id,
            'user_id'           => $user->id,
            'disk'              => 's3',
            'path'              => 'kk-uploads/test/sample.jpg',
            'original_filename' => 'sample.jpg',
            'mimetype'          => 'image/jpeg',
            'filesize'          => 2048,
            'checksum'          => hash('sha256', 'dummy'),
            'status'            => 'pending',
        ]);

        $job = new ExtractKkDataJob($submission);

        $this->assertEquals($submission->id, $job->submission->id);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }
}
