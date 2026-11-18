<?php

namespace Tests\Feature;

use App\Jobs\ExtractKkDataJob;
use App\Models\KkBatch;
use App\Models\KkSubmission;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAuthAndPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Queue::fake();
    }

    public function test_user_seeder_creates_default_users(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'adi@exata.site',
            'name'  => 'Adi Irawan',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@exata.site',
            'name'  => 'Administrator',
        ]);

        // Verify login with seeded credentials
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'adi@exata.site',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_batch_upload_accepts_pdf_and_image_files(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $imageFile = UploadedFile::fake()->image('scan_kk.jpg', 1000, 700);
        $pdfFile = UploadedFile::fake()->create('dokumen_kk.pdf', 2048, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/kk/batch', [
                'files' => [$imageFile, $pdfFile],
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('total', 2);

        $this->assertDatabaseHas('kk_submissions', [
            'original_filename' => 'dokumen_kk.pdf',
            'mimetype'          => 'application/pdf',
        ]);

        $this->assertDatabaseHas('kk_submissions', [
            'original_filename' => 'scan_kk.jpg',
            'mimetype'          => 'image/jpeg',
        ]);

        Queue::assertPushed(ExtractKkDataJob::class, 2);
    }
}
