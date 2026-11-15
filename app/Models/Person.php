<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    // ---------- Hatua za idhini ----------
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const IN_REVIEW = 'in_review';
    public const APPROVED = 'approved';
    public const RETURNED = 'returned';
    public const REJECTED = 'rejected';

    /** [jina, rangi ya ui-pill, ikoni] */
    public const STAGES = [
        self::DRAFT => ['Draft', '', 'bi-pencil'],
        self::SUBMITTED => ['Submitted', 'info', 'bi-send'],
        self::IN_REVIEW => ['In review', 'violet', 'bi-search'],
        self::APPROVED => ['Approved', 'ok', 'bi-patch-check'],
        self::RETURNED => ['Returned', 'warn', 'bi-arrow-return-left'],
        self::REJECTED => ['Rejected', 'bad', 'bi-x-octagon'],
    ];

    /** Mabadiliko ya sehemu hizi kwenye rekodi iliyoidhinishwa yanahitaji idhini tena. */
    public const SENSITIVE = ['first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'profile_picture'];

    /** Orodha ya ukaguzi ya anayeidhinisha. */
    public const CHECKLIST = [
        'photo' => 'Photo is clear and the face is visible',
        'signature' => 'Signature is present',
        'duplicates' => 'Not a duplicate of an existing record',
        'consistency' => 'Date of birth matches education and parent details',
        'location' => 'Village, ward, district and region are correct',
        'documents' => 'Supporting documents are attached and match the details',
    ];

    /** Aina za nyaraka za mwombaji. */
    public const DOCUMENT_TYPES = [
        'birth_certificate' => 'Birth certificate',
        'local_government_letter' => 'Local government letter',
        'parent_id' => "Parent's ID",
        'passport' => 'Passport',
        'other' => 'Other document',
    ];

    // ---------- Mzunguko wa kadi ----------
    public const CARD_PRINTED = 'printed';
    public const CARD_READY = 'ready';
    public const CARD_COLLECTED = 'collected';

    /** [jina, rangi ya ui-pill, ikoni]; null = bado haijachapishwa */
    public const CARD_STAGES = [
        'to_print' => ['To print', '', 'bi-printer'],
        self::CARD_PRINTED => ['Printed', 'info', 'bi-printer-fill'],
        self::CARD_READY => ['Ready for collection', 'warn', 'bi-box-seam'],
        self::CARD_COLLECTED => ['Collected', 'ok', 'bi-person-check'],
    ];

    public const CARD_LOSS_REASONS = ['lost' => 'Lost', 'damaged' => 'Damaged', 'stolen' => 'Stolen'];

    /** Sababu za kusitisha NIN. */
    public const DEACTIVATION_REASONS = [
        'deceased' => 'Deceased',
        'fraud' => 'Fraudulent or false registration',
        'duplicate' => 'Duplicate identity',
        'court_order' => 'Revoked by court order',
        'citizenship' => 'Citizenship renounced or revoked',
        'other' => 'Other',
    ];
    /**
     * Rejista (orodha, utafutaji, ripoti, API, QR) inaonyesha rekodi zilizoidhinishwa tu.
     * Tumia Person::anyStage() kwa foleni ya idhini na "My submissions".
     */
    protected static function booted(): void
    {
        static::addGlobalScope('approved', function (Builder $query) {
            $query->where($query->getModel()->qualifyColumn('stage'), self::APPROVED);
        });

        // Namba ya ombi ya kudumu: APP-<mwaka>-<id>, kwa kuangalia hali ya ombi.
        static::created(function (Person $person) {
            if (! $person->reference) {
                $person->reference = sprintf('APP-%s-%06d', ($person->created_at ?? now())->format('Y'), $person->id);
                $person->saveQuietly();
            }
        });
    }

    public static function anyStage(): Builder
    {
        return static::withoutGlobalScope('approved');
    }

    /** Profaili ya rekodi inafunguka kwa hatua yoyote; ruhusa zinakaguliwa kwenye controller. */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::anyStage()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [

        // Personal Information
        'nin',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'nationality',

        // Birth Information
        'birth_region',
        'birth_district',
        'birth_region_id',
        'birth_district_id',

        // Residential Address Information
        'region',
        'district',
        'ward',
        'village',
        'region_id',
        'district_id',
        'ward_id',
        'village_id',

        // Contact Information
        'phone',
        'email',

        // Profile and Status
        'profile_picture',
        'signature',
        'status',
        'archive_reason',
        'stage',
        'created_by',
        'submitted_by',
        'submitted_at',
        'reviewer_id',
        'reviewed_at',
        'review_note',
        'reference',
        'card_status',
        'card_version',
        'card_printed_at',
        'card_ready_at',
        'card_collected_at',
        'card_collected_by',
        'deactivation_reason',
        'deactivation_note',
        'date_of_death',
        'deactivated_at',
        'deactivated_by',

        // Father Information
        'father_name',
        'father_phone',

        // Mother Information
        'mother_name',
        'mother_phone',

        // Guardian Information
        'guardian_name',
        'guardian_phone',

        // Education Information
        'education_level',
        'primary_school',
        'primary_completion_year',
        'secondary_school',
        'secondary_completion_year',
        'higher_institution',
        'course',
        'qualification',
    ];

    /**
     * Data type casting
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'deleted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'card_printed_at' => 'datetime',
            'card_ready_at' => 'datetime',
            'card_collected_at' => 'datetime',
            'card_version' => 'integer',
            'date_of_death' => 'date',
            'deactivated_at' => 'datetime',
            'primary_completion_year' => 'integer',
            'secondary_completion_year' => 'integer',
        ];
    }

    /**
     * NIN mpya ya kipekee: YYYYMMDD (tarehe ya kuzaliwa) + tarakimu 12 za nasibu.
     * Inakagua pia rekodi zilizohifadhiwa (archived).
     */
    public static function generateNin(\DateTimeInterface|string $dateOfBirth): string
    {
        $prefix = \Illuminate\Support\Carbon::parse($dateOfBirth)->format('Ymd');

        do {
            $nin = $prefix;
            for ($i = 0; $i < 12; $i++) {
                $nin .= random_int(0, 9);
            }
        } while (static::anyStage()->withTrashed()->where('nin', $nin)->exists());

        return $nin;
    }

    /**
     * Get person's full name
     */
    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }
    /**
     * Location relationships (IDs ndizo chanzo cha kuaminika).
     */
    public function birthRegionModel(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'birth_region_id');
    }

    public function birthDistrictModel(): BelongsTo
    {
        return $this->belongsTo(District::class, 'birth_district_id');
    }

    public function regionModel(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function districtModel(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function wardModel(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function villageModel(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id');
    }

    // ---------- Idhini: uhusiano ----------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PersonReview::class)->orderBy('id');
    }

    // ---------- Idhini: hali na ruhusa ----------

    public function isApproved(): bool
    {
        return ($this->stage ?? self::APPROVED) === self::APPROVED;
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage][0] ?? ucfirst((string) $this->stage);
    }

    public function stageTone(): string
    {
        return self::STAGES[$this->stage][1] ?? '';
    }

    public function stageIcon(): string
    {
        return self::STAGES[$this->stage][2] ?? 'bi-circle';
    }

    /** Aliyesajili au aliyewasilisha hawezi kuidhinisha kazi yake mwenyewe. */
    public function isOwnWork(User $user): bool
    {
        if (config('services.nin.allow_self_approval')) {
            return false;
        }

        return in_array($user->id, array_filter([$this->created_by, $this->submitted_by]), true);
    }

    public function canBeEditedBy(User $user): bool
    {
        return $user->canRegister() && in_array($this->stage, [self::DRAFT, self::RETURNED, self::APPROVED], true);
    }

    public function canBeSubmittedBy(User $user): bool
    {
        return $user->canRegister() && in_array($this->stage, [self::DRAFT, self::RETURNED], true);
    }

    public function canBeReviewedBy(User $user): bool
    {
        if (! $user->canApprove() || $this->isOwnWork($user)) {
            return false;
        }

        return $this->stage === self::SUBMITTED
            || ($this->stage === self::IN_REVIEW && $this->reviewer_id === $user->id);
    }

    public function canBeReleasedBy(User $user): bool
    {
        return $this->stage === self::IN_REVIEW
            && ($this->reviewer_id === $user->id || $user->role === 'Administrator');
    }

    // ---------- Nyaraka, kadi, kusitishwa ----------

    public function documents(): HasMany
    {
        return $this->hasMany(PersonDocument::class)->latest('id');
    }

    public function cardEvents(): HasMany
    {
        return $this->hasMany(CardEvent::class)->orderBy('id');
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function isDeactivated(): bool
    {
        return $this->deactivation_reason !== null;
    }

    public function deactivationLabel(): ?string
    {
        return $this->deactivation_reason ? (self::DEACTIVATION_REASONS[$this->deactivation_reason] ?? ucfirst($this->deactivation_reason)) : null;
    }

    public function cardStageKey(): string
    {
        return $this->card_status ?: 'to_print';
    }

    public function cardStageLabel(): string
    {
        return self::CARD_STAGES[$this->cardStageKey()][0];
    }
}
