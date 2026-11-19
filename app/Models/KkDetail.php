<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KkDetail — represents all rendered fields of a Kartu Keluarga document.
 *
 * Allows manual corrections to fix AI OCR typos and powers on-demand
 * DomPDF regeneration.
 *
 * @property int $id
 * @property int|null $submission_id
 * @property int|null $batch_id
 * @property int $user_id
 * @property string $family_card_number
 * @property string $head_of_family
 * @property string|null $head_of_family_japan
 * @property array $address
 * @property array $members
 * @property string|null $issued_date
 * @property string|null $issued_place
 * @property string|null $signatory_officer
 * @property string|null $signatory_nip
 * @property array|null $raw_gemini_output
 * @property bool $is_edited
 * @property bool $is_submitted
 * @property \Illuminate\Support\Carbon|null $last_edited_at
 */
class KkDetail extends Model
{
    protected $fillable = [
        'submission_id',
        'batch_id',
        'user_id',
        'original_filename',
        'family_card_number',
        'head_of_family',
        'head_of_family_japan',
        'address',
        'members',
        'issued_date',
        'issued_place',
        'signatory_officer',
        'signatory_nip',
        'raw_gemini_output',
        'original_data',
        'translated_data',
        'is_edited',
        'is_submitted',
        'last_edited_at',
    ];

    protected $appends = [
        'original_filename',
    ];

    public function getOriginalFilenameAttribute(?string $value = null): ?string
    {
        return $value ?: $this->submission?->original_filename;
    }

    protected $casts = [
        'address'           => 'array',
        'members'           => 'array',
        'raw_gemini_output' => 'array',
        'original_data'     => 'array',
        'translated_data'   => 'array',
        'is_edited'         => 'boolean',
        'is_submitted'      => 'boolean',
        'last_edited_at'    => 'datetime',
    ];

    /**
     * Parent file submission.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(KkSubmission::class, 'submission_id');
    }

    /**
     * Batch this detail belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(KkBatch::class, 'batch_id');
    }

    /**
     * User who owns this record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Format the data into the exact TranslationResult structure expected by DomPDF Blade template.
     */
    public function toTranslationResult(): array
    {
        return [
            'familyCardNumber' => $this->family_card_number,
            'headOfFamily'     => $this->head_of_family_japan ?: $this->head_of_family,
            'address'          => $this->address ?? [],
            'members'          => $this->members ?? [],
            'issuedDate'       => $this->issued_date ?? '',
            'issuedPlace'      => $this->issued_place ?? '',
            'signatoryOfficer' => $this->signatory_officer ?? '',
            'signatoryNip'     => $this->signatory_nip ?? '',
        ];
    }
}
