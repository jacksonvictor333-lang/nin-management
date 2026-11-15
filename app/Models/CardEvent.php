<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hatua moja ya kadi: imechapishwa, iko tayari, imekabidhiwa, imepotea/kuharibika.
 */
class CardEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['person_id', 'user_id', 'card_version', 'event', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
