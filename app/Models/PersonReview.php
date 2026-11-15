<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hatua moja kwenye historia ya idhini ya rekodi (nani, lini, nini, sababu).
 */
class PersonReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['person_id', 'user_id', 'action', 'note', 'checklist'];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('approved');
    }
}
