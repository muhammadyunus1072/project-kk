<?php

namespace Tests\Feature;

use App\Models\KkBatch;
use App\Models\KkDetail;
use App\Models\KkSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KkDetailApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected KkBatch $batch;
    protected KkSubmission $submission;
    protected KkDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'adi@example.com',
            'name'  => 'Adi Irawan',
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->batch = KkBatch::create([
            'user_id'     => $this->user->id,
            'total_files' => 1,
            'status'      => 'done',
        ]);

        $this->submission = KkSubmission::create([
            'batch_id'          => $this->batch->id,
            'user_id'           => $this->user->id,
            'disk'              => 's3',
            'path'              => 'kk-uploads/test/sample.jpg',
            'original_filename' => 'sample.jpg',
            'mimetype'          => 'image/jpeg',
            'filesize'          => 1024,
            'checksum'          => hash('sha256', 'test'),
            'status'            => 'done',
        ]);

        $this->detail = KkDetail::create([
            'submission_id'        => $this->submission->id,
            'batch_id'             => $this->batch->id,
            'user_id'              => $this->user->id,
            'family_card_number'   => '3174052309120005',
            'head_of_family'       => 'BUDI SANTOSO',
            'head_of_family_japan' => 'ブディ・サントソ',
            'address'              => [
                'street'      => 'メラティ・インダ通り42番地',
                'rtRw'        => '004 / 007',
                'subDistrict' => 'チランダック・バラット村',
                'district'    => 'チランダック区',
                'regencyCity' => '南ジャカルタ市',
                'province'    => 'ジャカルタ首都特別州',
                'postalCode'  => '12430',
            ],
            'members'              => [
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
            'issued_date'          => '2021年05月18日',
            'issued_place'         => '南ジャカルタ市',
            'signatory_officer'    => 'アフマド・ファウジ',
            'signatory_nip'        => '19680512 199403 1 002',
            'raw_gemini_output'    => ['sample' => 'raw'],
            'is_edited'            => false,
            'is_submitted'         => false,
        ]);
    }

    public function test_unauthenticated_cannot_access_kk_detail(): void
    {
        $response = $this->getJson("/api/kk/details/{$this->detail->id}");
        $response->assertStatus(401);
    }

    public function test_user_can_view_their_kk_detail(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/kk/details/{$this->detail->id}");

        $response->assertStatus(200)
            ->assertJsonPath('detail.id', $this->detail->id)
            ->assertJsonPath('detail.family_card_number', '3174052309120005')
            ->assertJsonPath('detail.is_edited', false)
            ->assertJsonPath('detail.raw_gemini_output.sample', 'raw');
    }

    public function test_user_can_edit_kk_detail_to_fix_typos(): void
    {
        // Fix typo: Correct head of family name & Katakana
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/kk/details/{$this->detail->id}", [
                'head_of_family'       => 'BUDI SANTOSO S.E.',
                'head_of_family_japan' => 'ブディ・サントソ（修正済）',
                'family_card_number'   => '3174052309129999',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('detail.head_of_family', 'BUDI SANTOSO S.E.')
            ->assertJsonPath('detail.head_of_family_japan', 'ブディ・サントソ（修正済）')
            ->assertJsonPath('detail.is_edited', true);

        $this->assertDatabaseHas('kk_details', [
            'id'                   => $this->detail->id,
            'head_of_family'       => 'BUDI SANTOSO S.E.',
            'head_of_family_japan' => 'ブディ・サントソ（修正済）',
            'is_edited'            => true,
        ]);

        // Check that parent submission extracted_data was updated as well
        $this->submission->refresh();
        $this->assertEquals('3174052309129999', $this->submission->extracted_data['familyCardNumber']);
    }

    public function test_user_can_regenerate_pdf_with_corrected_data(): void
    {
        // Update first to mark as edited
        $this->detail->update([
            'head_of_family' => 'AGUS KURNIAWAN',
            'is_edited'      => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get("/api/kk/details/{$this->detail->id}/pdf");

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());

        // Verify it was marked as submitted
        $this->assertTrue($this->detail->fresh()->is_submitted);
    }

    public function test_user_can_list_details_ordered_by_is_submitted_asc(): void
    {
        // Mark first detail as submitted
        $this->detail->update(['is_submitted' => true]);

        // Create a second unsubmitted detail (created later)
        $unsubmitted = KkDetail::create([
            'user_id'            => $this->user->id,
            'family_card_number' => '3174052309120002',
            'head_of_family'     => 'UNSUBMITTED HEAD',
            'address'            => ['street' => 'Jalan Unsubmitted'],
            'members'            => [['no' => 1, 'fullNameOriginal' => 'MEMBER 1']],
            'is_submitted'       => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/kk/details');

        $response->assertStatus(200)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('pending', 1)
            ->assertJsonPath('done', 1);

        // First item in the list must be the unsubmitted one because of is_submitted ASC
        $this->assertEquals($unsubmitted->id, $response->json('details.0.id'));
        $this->assertFalse($response->json('details.0.is_submitted'));

        // Second item is the submitted one
        $this->assertEquals($this->detail->id, $response->json('details.1.id'));
        $this->assertTrue($response->json('details.1.is_submitted'));
    }

    public function test_user_can_store_manual_kk_detail_without_file_submission(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/kk/details', [
                'family_card_number'   => '3201011203940001',
                'head_of_family'       => 'AHMAD DAHLAN',
                'head_of_family_japan' => 'アフマド・ダーラン',
                'address'              => [
                    'street'      => 'Jl. Sudirman No. 10',
                    'rtRw'        => '01/02',
                    'subDistrict' => 'Menteng',
                    'district'    => 'Menteng',
                    'regencyCity' => 'Jakarta Pusat',
                    'province'    => 'DKI Jakarta',
                    'postalCode'  => '10310',
                ],
                'members'              => [
                    [
                        'no'               => 1,
                        'fullNameOriginal' => 'AHMAD DAHLAN',
                        'fullNameKatakana' => 'アフマド・ダーラン',
                        'nik'              => '3201011203940001',
                        'gender'           => '男',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('detail.family_card_number', '3201011203940001')
            ->assertJsonPath('detail.is_submitted', false)
            ->assertJsonPath('detail.submission_id', null);

        $this->assertDatabaseHas('kk_details', [
            'family_card_number' => '3201011203940001',
            'is_submitted'       => false,
            'submission_id'      => null,
        ]);
    }

    public function test_user_can_mark_detail_as_submitted(): void
    {
        $this->assertFalse($this->detail->is_submitted);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/kk/details/{$this->detail->id}/mark-submitted");

        $response->assertStatus(200)
            ->assertJsonPath('detail.is_submitted', true);

        $this->assertTrue($this->detail->fresh()->is_submitted);
    }

    public function test_user_can_toggle_submitted_status(): void
    {
        $this->assertFalse($this->detail->is_submitted);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/kk/details/{$this->detail->id}/toggle-submitted");

        $response->assertStatus(200)
            ->assertJsonPath('detail.is_submitted', true);

        $this->assertTrue($this->detail->fresh()->is_submitted);
    }

    public function test_user_can_delete_detail(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/kk/details/{$this->detail->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('kk_details', ['id' => $this->detail->id]);
    }

    public function test_unauthorized_user_cannot_edit_other_user_detail(): void
    {
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $otherToken = $otherUser->createToken('other-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->putJson("/api/kk/details/{$this->detail->id}", [
                'head_of_family' => 'HACKED',
            ]);

        $response->assertStatus(403);
    }

    public function test_update_persists_original_and_translated_data(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/kk/details/{$this->detail->id}", [
                'head_of_family'       => 'BUDI SANTOSO UPDATED',
                'head_of_family_japan' => 'ブディ・サントソ・シン',
                'original_data'        => [
                    'head_of_family' => 'BUDI SANTOSO UPDATED',
                ],
                'translated_data'      => [
                    'headOfFamily' => 'ブディ・サントソ・シン',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('detail.head_of_family', 'BUDI SANTOSO UPDATED')
            ->assertJsonPath('detail.head_of_family_japan', 'ブディ・サントソ・シン')
            ->assertJsonPath('detail.original_data.head_of_family', 'BUDI SANTOSO UPDATED')
            ->assertJsonPath('detail.translated_data.headOfFamily', 'ブディ・サントソ・シン');

        $fresh = $this->detail->fresh();
        $this->assertEquals('BUDI SANTOSO UPDATED', $fresh->original_data['head_of_family']);
        $this->assertEquals('ブディ・サントソ・シン', $fresh->translated_data['headOfFamily']);
    }

    public function test_user_can_stream_original_file(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('kk-uploads/test/sample.jpg', 'fake-image-content');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get("/api/kk/details/{$this->detail->id}/original-file");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_unauthorized_user_cannot_access_original_file(): void
    {
        $otherUser = User::factory()->create(['email' => 'other_user@example.com']);
        $otherToken = $otherUser->createToken('other-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->get("/api/kk/details/{$this->detail->id}/original-file");

        $response->assertStatus(403);
    }

    public function test_original_file_returns_404_for_manual_entry(): void
    {
        $manualDetail = KkDetail::create([
            'user_id'            => $this->user->id,
            'submission_id'      => null,
            'family_card_number' => '1234567890123456',
            'head_of_family'     => 'Manual User',
            'address'            => [],
            'members'            => [],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get("/api/kk/details/{$manualDetail->id}/original-file");

        $response->assertStatus(404);
    }
}
