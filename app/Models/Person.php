<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

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
            'primary_completion_year' => 'integer',
            'secondary_completion_year' => 'integer',
        ];
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
}
