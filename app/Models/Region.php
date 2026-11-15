<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'name_en',
        'slug',
        'latitude',
        'longitude',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'region_id');
    }
}
