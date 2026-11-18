<?php

namespace Tests\Feature;

use App\Jobs\ExtractKkDataJob;
use App\Models\KkBatch;
use App\Models\KkSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KkBatchApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Queue::fake();

        $this->user = User::factory()->create([
            'email' => 'adi@example.com',
            'name'  => 'Adi Irawan',
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->postJson('/api/kk/batch', []);
        $response->assertStatus(401);
    }

    public function test_user_can_upload_batch_of_kk_images(): void
    {
        $file1 = UploadedFile::fake()->image('kk1.jpg', 800, 600);
        $file2 = UploadedFile::fake()->image('kk2.png', 800, 600);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/kk/batch', [
                'files' => [$file1, $file2],
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['batch_id', 'total', 'job_ids']);

        $batchId = $response->json('batch_id');

        $this->assertDatabaseHas('kk_batches', [
            'id'          => $batchId,
            'user_id'     => $this->user->id,
            'total_files' => 2,
            'status'      => 'processing',
        ]);

        $this->assertDatabaseCount('kk_submissions', 2);

        // Check that checksum, filesize, mimetype, disk are saved
        $submission = KkSubmission::first();
        $this->assertNotNull($submission->checksum);
        $this->assertNotNull($submission->filesize);
        $this->assertContains($submission->disk, ['s3', 'supabase', 'local']);

        Queue::assertPushed(ExtractKkDataJob::class, 2);
    }

    public function test_user_can_get_batch_status(): void
    {
        $batch = KkBatch::create([
            'user_id'     => $this->user->id,
            'total_files' => 1,
            'status'      => 'processing',
        ]);

        $submission = KkSubmission::create([
            'batch_id'          => $batch->id,
            'user_id'           => $this->user->id,
            'disk'              => 's3',
            'path'              => 'kk-uploads/test/sample.jpg',
            'original_filename' => 'sample.jpg',
            'mimetype'          => 'image/jpeg',
            'filesize'          => 1024,
            'checksum'          => hash('sha256', 'test'),
            'status'            => 'done',
            'extracted_data'    => [
                'familyCardNumber' => '3174052309120005',
                'headOfFamily'     => 'ブディ・サントソ',
                'address' => [
                    'street'      => 'メラティ・インダ通り42番地',
                    'rtRw'        => '004 / 007',
                    'subDistrict' => 'チランダック・バラット村',
                    'district'    => 'チランダック区',
                    'regencyCity' => '南ジャカルタ市',
                    'province'    => 'ジャカルタ首都特別州',
                    'postalCode'  => '12430',
                ],
                'members' => [],
                'issuedDate'       => '2021年05月18日',
                'issuedPlace'      => '南ジャカルタ市',
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/kk/batch/{$batch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('batch_id', $batch->id)
            ->assertJsonPath('jobs.0.filename', 'sample.jpg')
            ->assertJsonPath('jobs.0.result.familyCardNumber', '3174052309120005');
    }

    public function test_dompdf_renders_japanese_koseki_tohon(): void
    {
        $payload = [
            'data' => [
                'familyCardNumber' => '3174052309120005',
                'headOfFamily'     => 'ブディ・サントソ',
                'address' => [
                    'street'      => 'メラティ・インダ通り42番地',
                    'rtRw'        => '004 / 007',
                    'subDistrict' => 'チランダック・バラット村',
                    'district'    => 'チランダック区',
                    'regencyCity' => '南ジャカルタ市',
                    'province'    => 'ジャカルタ首都特別州',
                    'postalCode'  => '12430',
                ],
                'members' => [
                    [
                        'no'               => 1,
                        'fullNameOriginal'  => 'BUDI SANTOSO',
                        'fullNameKatakana'  => 'ブディ・サントソ',
                        'nik'              => '3174051508820001',
                        'gender'           => '男',
                        'birthPlace'       => 'ジャカルタ',
                        'birthDate'        => '1982年08月15日',
                        'religion'         => 'イスラム教',
                        'education'        => '大学卒業',
                        'occupation'       => '会社員',
                        'maritalStatus'    => '既婚',
                        'relationStatus'   => '本人',
                        'fatherName'       => 'スカルノ',
                        'motherName'       => 'シティ・アミナ',
                        'passportNumber'   => '-',
                        'stayPermitNumber' => '-',
                        'nationality'      => 'インドネシア',
                    ],
                ],
                'issuedDate'       => '2021年05月18日',
                'issuedPlace'      => '南ジャカルタ市',
                'signatoryOfficer' => 'アフマド・ファウジ',
                'signatoryNip'     => '19680512 199403 1 002',
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/kk/render-pdf', $payload);

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }
}
