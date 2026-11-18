<?php

namespace App\Jobs;

use App\Events\KkExtractionProgressEvent;
use App\Models\KkDetail;
use App\Models\KkSubmission;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\MimeType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ExtractKkDataJob
 *
 * Queue job that:
 * 1. Reads a KK image from Supabase S3 storage (or configured disk)
 * 2. Broadcasts real-time 'processing' event via Pusher
 * 3. Sends image to Gemini with structured responseSchema (identical to JS TranslationResult)
 * 4. Parses JSON response into TranslationResult
 * 5. Saves result to kk_submissions.extracted_data
 * 6. Broadcasts 'done' or 'failed' event via Pusher
 * 7. Refreshes parent kk_batches progress
 */
class ExtractKkDataJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 10;

    public function __construct(
        public readonly KkSubmission $submission
    ) {}

    /**
     * Execute the job: extract KK data from image using Gemini AI.
     */
    public function handle(): void
    {
        // Mark submission as processing
        $this->submission->update([
            'status'   => 'processing',
            'attempts' => $this->submission->attempts + 1,
        ]);

        $batch = $this->submission->batch;

        // Broadcast processing event over WebSockets (Pusher)
        $this->broadcastProgress('processing');

        try {
            // 1. Read image or PDF from storage disk (Supabase S3 or local)
            $imageBytes = Storage::disk($this->submission->disk)->get($this->submission->path);

            if (! $imageBytes) {
                throw new \RuntimeException("Failed to read file from disk [{$this->submission->disk}]: {$this->submission->path}");
            }

            $mimeType = $this->getMimeType($this->submission->mimetype);
            $base64Image = base64_encode($imageBytes);
            // 2. Build Gemini structured responseSchema
            $generationConfig = new GenerationConfig(
                responseMimeType: ResponseMimeType::APPLICATION_JSON,
                responseSchema: $this->buildTranslationSchema(),
            );

            // 3. Call Gemini API via GoogleGenAI Facade (supports multimodal images & PDFs natively)
            $modelName = config('gemini.model', 'gemini-2.0-flash');
            $isPdf = str_contains(strtolower($this->submission->mimetype), 'pdf');
            $promptInstruction = $isPdf
                ? 'Analisis dokumen PDF Kartu Keluarga (KK) ini dengan sangat teliti. '
                . 'Ekstrak seluruh nomor KK, alamat lengkap, dan SEMUA anggota keluarga dalam tabel. '
                . 'Terjemahkan nama ke Katakana dan istilah kependudukan ke bahasa Jepang resmi sesuai instruksi.'
                : 'Analisis gambar Kartu Keluarga (KK) ini dengan sangat teliti. '
                . 'Ekstrak seluruh nomor KK, alamat lengkap, dan SEMUA anggota keluarga dalam tabel. '
                . 'Terjemahkan nama ke Katakana dan istilah kependudukan ke bahasa Jepang resmi sesuai instruksi.';

            $response = Gemini::generativeModel(model: $modelName)
                ->withSystemInstruction(Content::parse($this->getSystemInstruction()))
                ->withGenerationConfig($generationConfig)
                ->generateContent([
                    new Blob(
                        mimeType: $mimeType,
                        data: $base64Image,
                    ),
                    $promptInstruction
                ]);

            // 4. Parse JSON response — same shape as JS TranslationResult
            $rawJson = $response->text();
            $data    = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);

            // 5. Persist successful result
            $this->submission->update([
                'status'         => 'done',
                'extracted_data' => $data,
                'error_message'  => null,
            ]);

            // 6. Save structured KK detail record for manual edits and on-demand PDF regeneration
            $kkDetail = KkDetail::updateOrCreate(
                ['submission_id' => $this->submission->id],
                [
                    'batch_id'             => $this->submission->batch_id,
                    'user_id'              => $this->submission->user_id,
                    'family_card_number'   => $data['familyCardNumber'] ?? '',
                    'head_of_family'       => $data['headOfFamily'] ?? '',
                    'head_of_family_japan' => $data['headOfFamily'] ?? '',
                    'address'              => $data['address'] ?? [],
                    'members'              => $data['members'] ?? [],
                    'issued_date'          => $data['issuedDate'] ?? '',
                    'issued_place'         => $data['issuedPlace'] ?? '',
                    'signatory_officer'    => $data['signatoryOfficer'] ?? '',
                    'signatory_nip'        => $data['signatoryNip'] ?? '',
                    'raw_gemini_output'    => $data,
                    'original_data'        => [
                        'family_card_number'   => $data['familyCardNumber'] ?? '',
                        'head_of_family'       => $data['headOfFamily'] ?? '',
                        'address'              => $data['address'] ?? [],
                        'members'              => array_map(fn($m) => [
                            'no'               => $m['no'] ?? null,
                            'fullNameOriginal' => $m['fullNameOriginal'] ?? '',
                            'nik'              => $m['nik'] ?? '',
                            'gender'           => $m['gender'] ?? '',
                            'birthPlace'       => $m['birthPlace'] ?? '',
                            'birthDate'        => $m['birthDate'] ?? '',
                            'religion'         => $m['religion'] ?? '',
                            'education'        => $m['education'] ?? '',
                            'occupation'       => $m['occupation'] ?? '',
                            'maritalStatus'    => $m['maritalStatus'] ?? '',
                            'relationStatus'   => $m['relationStatus'] ?? '',
                            'fatherName'       => $m['fatherName'] ?? '',
                            'motherName'       => $m['motherName'] ?? '',
                        ], $data['members'] ?? []),
                        'issued_date'          => $data['issuedDate'] ?? '',
                        'issued_place'         => $data['issuedPlace'] ?? '',
                        'signatory_officer'    => $data['signatoryOfficer'] ?? '',
                        'signatory_nip'        => $data['signatoryNip'] ?? '',
                    ],
                    'translated_data'      => $data,
                    'is_edited'            => false,
                    'is_submitted'         => false,
                ]
            );

            // Update parent batch progress
            $batch->refreshProgress();

            // Broadcast done event with extracted data and detail ID
            $this->broadcastProgress('done', $data, null, $kkDetail->id);
        } catch (\Throwable $e) {
            Log::error("ExtractKkDataJob failed for submission [{$this->submission->id}]: " . $e->getMessage());

            $this->submission->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Update parent batch progress
            $batch->refreshProgress();

            // Broadcast failed event
            $this->broadcastProgress('failed', null, $e->getMessage());

            throw $e; // Re-throw for queue retry if attempts remain
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $this->submission->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        $batch = $this->submission->batch;
        $batch->refreshProgress();

        $this->broadcastProgress('failed', null, $exception->getMessage());
    }

    /**
     * Helper to broadcast real-time status updates via Pusher.
     */
    private function broadcastProgress(string $status, ?array $data = null, ?string $error = null, ?string $detailId = null): void
    {
        try {
            $batch = $this->submission->batch;
            $batch->loadMissing('submissions');

            broadcast(new KkExtractionProgressEvent(
                userId: $this->submission->user_id,
                batchId: $this->submission->batch_id,
                submissionId: $this->submission->id,
                filename: $this->submission->original_filename,
                status: $status,
                extractedData: $data,
                errorMessage: $error,
                completedCount: $batch->submissions->where('status', 'done')->count(),
                failedCount: $batch->submissions->where('status', 'failed')->count(),
                totalCount: $batch->total_files,
                batchStatus: $batch->status,
                detailId: $detailId
            ));
        } catch (\Throwable $e) {
            // Broadcasting failure should not crash the core extraction job
            Log::warning("Broadcasting failed for submission [{$this->submission->id}]: " . $e->getMessage());
        }
    }

    /**
     * Build the Gemini responseSchema for structured KK extraction.
     * Mirrors the TRANSLATION_SCHEMA in server.ts and types.ts exactly.
     */
    private function buildTranslationSchema(): Schema
    {
        $memberSchema = new Schema(
            type: DataType::OBJECT,
            properties: [
                'no'               => new Schema(type: DataType::INTEGER, description: 'Nomor urut anggota'),
                'fullNameOriginal'  => new Schema(type: DataType::STRING,  description: 'Nama lengkap asli huruf Latin Kapital'),
                'fullNameKatakana'  => new Schema(type: DataType::STRING,  description: 'Transliterasi Katakana resmi standar Jepang, e.g. ブディ・サントソ'),
                'nik'              => new Schema(type: DataType::STRING,  description: 'Nomor Induk Kependudukan (16 digit)'),
                'gender'           => new Schema(type: DataType::STRING,  description: '男 (Laki-laki) atau 女 (Perempuan)'),
                'birthPlace'       => new Schema(type: DataType::STRING,  description: 'Tempat lahir transliterasi Jepang Romaji/Katakana'),
                'birthDate'        => new Schema(type: DataType::STRING,  description: 'Format standar Jepang: YYYY年MM月DD日'),
                'religion'         => new Schema(type: DataType::STRING,  description: 'Agama standar Jepang: イスラム教, キリスト教, カトリック, ヒンドゥー教, 仏教'),
                'education'        => new Schema(type: DataType::STRING,  description: 'Pendidikan WAJIB standar kependudukan Jepang (Kanji/Katakana murni, misal: 高等学校卒業, 大学卒業, 中学校卒業)'),
                'occupation'       => new Schema(type: DataType::STRING,  description: 'Pekerjaan WAJIB standar kependudukan Jepang (Kanji/Katakana murni, misal: 会社員, 公務員, 自営業, 学生, 主婦, 無職)'),
                'maritalStatus'    => new Schema(type: DataType::STRING,  description: 'Status perkawinan: 未婚, 既婚, 離婚, 死別'),
                'relationStatus'   => new Schema(type: DataType::STRING,  description: 'Hubungan keluarga: 本人, 妻, 夫, 子, 長男, 長女, 父母, 義父母, その他の親族'),
                'fatherName'       => new Schema(type: DataType::STRING,  description: 'Nama Ayah WAJIB dalam Katakana murni standar Jepang (contoh: スカルノ). DILARANG huruf Latin.'),
                'motherName'       => new Schema(type: DataType::STRING,  description: 'Nama Ibu WAJIB dalam Katakana murni standar Jepang (contoh: シティ・アミナ). DILARANG huruf Latin.'),
            ],
            required: [
                'no',
                'fullNameOriginal',
                'fullNameKatakana',
                'nik',
                'gender',
                'birthPlace',
                'birthDate',
                'religion',
                'education',
                'occupation',
                'maritalStatus',
                'relationStatus',
                'fatherName',
                'motherName',
            ],
        );

        $addressSchema = new Schema(
            type: DataType::OBJECT,
            properties: [
                'street'      => new Schema(type: DataType::STRING, description: 'Alamat jalan/dusun dengan padanan Jepang (misal: Jl. Mawar No. 12 / マワール通り12番地)'),
                'rtRw'        => new Schema(type: DataType::STRING, description: 'Format RT xxx / RW xxx'),
                'subDistrict' => new Schema(type: DataType::STRING, description: 'Kelurahan/Desa padanan Jepang (村/行政村)'),
                'district'    => new Schema(type: DataType::STRING, description: 'Kecamatan padanan Jepang (郡/区)'),
                'regencyCity' => new Schema(type: DataType::STRING, description: 'Kabupaten/Kota padanan Jepang (県/市)'),
                'province'    => new Schema(type: DataType::STRING, description: 'Provinsi padanan Jepang (州)'),
                'postalCode'  => new Schema(type: DataType::STRING, description: 'Kode Pos (郵便番号)'),
            ],
            required: ['street', 'rtRw', 'subDistrict', 'district', 'regencyCity', 'province', 'postalCode'],
        );

        return new Schema(
            type: DataType::OBJECT,
            properties: [
                'familyCardNumber' => new Schema(type: DataType::STRING, description: 'Nomor Kartu Keluarga (16 digit)'),
                'headOfFamily'     => new Schema(type: DataType::STRING, description: 'Nama Kepala Keluarga (Latin + Katakana jika ada)'),
                'address'          => $addressSchema,
                'members'          => new Schema(
                    type: DataType::ARRAY,
                    description: 'Daftar seluruh anggota keluarga yang tercatat di KK',
                    items: $memberSchema,
                ),
                'issuedDate'       => new Schema(type: DataType::STRING, description: 'Tanggal diterbitkan: YYYY年MM月DD日'),
                'issuedPlace'      => new Schema(type: DataType::STRING, description: 'Tempat/Kota diterbitkan KK'),
                'signatoryOfficer' => new Schema(type: DataType::STRING, description: 'Nama Kepala Dinas Kependudukan dan Catatan Sipil dalam Katakana murni tanpa gelar Latin (contoh: アフマド・シャリフ)'),
                'signatoryNip'     => new Schema(type: DataType::STRING, description: 'Nomor Induk Pegawai (NIP) pejabat penandatangan'),
            ],
            required: ['familyCardNumber', 'headOfFamily', 'address', 'members', 'issuedDate', 'issuedPlace'],
        );
    }

    /**
     * System instruction for Gemini (identical to server.ts SYSTEM_INSTRUCTION).
     */
    private function getSystemInstruction(): string
    {
        return '
Anda adalah Spesialis Dokumen Terjemahan Hukum Jepang-Indonesia dan Penerjemah Resmi untuk administrasi keimigrasian Jepang (出入国在留管理庁) dan Balai Kota Jepang (市役所/区役所).

PERATURAN UTAMA:
Seluruh hasil terjemahan HARUS 100% DALAM BAHASA JEPANG (Kanji & Katakana murni).
DILARANG KERAS menyertakan bahasa Indonesia atau penjelasan bahasa Indonesia dalam tanda kurung di dalam nilai JSON.

Aturan Terjemahan:
1. Nama Orang (fullNameKatakana, headOfFamily, fatherName, motherName, signatoryOfficer):
   - Wajib transliterasi Katakana resmi standar Jepang yang akurat (contoh: "ブディ・サントソ", "スカルノ", "シティ・アミナ", "アフマド・シャリフ").
   - DILARANG KERAS menyertakan huruf Latin pada fatherName, motherName, headOfFamily, dan signatoryOfficer.
   - fullNameOriginal tetap menyimpan nama asli huruf Latin Kapital.
2. Wilayah Administratif:
   - Kabupaten/Kota: Gunakan padanan 市/県 (contoh: "南ジャカルタ市", "スラバヤ市", "バンドン市").
   - Provinsi: Gunakan 州 (contoh: "ジャカルタ首都特別州", "東ジャワ州", "西ジャワ州").
   - Kecamatan: Gunakan 区/郡 (contoh: "チランダック区").
   - Kelurahan/Desa: Gunakan 村/行政村 (contoh: "チランダック・バラット村").
   - Jalan: Gunakan 通り/番地 (contoh: "メラティ・インダ通り42番地").
3. Status Hubungan (relationStatus):
   - Wajib istilah kependudukan Jepang murni: 本人, 妻, 夫, 子, 長男, 長女, 父母, 義父母, その他の親族.
4. Status Perkawinan (maritalStatus):
   - Wajib istilah Jepang murni: 未婚, 既婚, 離婚, 死別.
5. Agama (religion):
   - イスラム教, キリスト教, カトリック, ヒンドゥー教, 仏教, 儒教.
6. Pendidikan (education):
   - Wajib 100% istilah Jepang resmi: 未就学, 小学校卒業, 中学校卒業, 高等学校卒業, 短大, 専門学校, 大学卒業, 大学院修士課程修了, 博士課程修了. DILARANG huruf Latin.
7. Pekerjaan (occupation):
   - Wajib 100% istilah Jepang resmi: 会社員, 公務員, 自営業, 学生, 主婦, 無職, 医師, 教員, アルバイト, 運転手, 警察官, 軍人, 退職者. DILARANG huruf Latin.
8. Format Tanggal (birthDate, issuedDate):
   - Gunakan format kalender Masehi: YYYY年MM月DD日 (contoh: "1985年10月12日").
9. Jenis Kelamin (gender):
   - "男" atau "女".

Selalu kembalikan respon dalam format JSON sesuai schema yang ditentukan.
';
    }

    private function getMimeType(string $mimeType): MimeType
    {
        return match ($mimeType) {
            'application/pdf' => MimeType::APPLICATION_PDF,
            'image/png' => MimeType::IMAGE_PNG,
            'image/jpeg' => MimeType::IMAGE_JPEG,
            'image/webp' => MimeType::IMAGE_WEBP,
            default => MimeType::IMAGE_JPEG,
        };
    }
}
