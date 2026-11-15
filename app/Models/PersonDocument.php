<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nyaraka ya mwombaji (cheti cha kuzaliwa, barua ya Serikali ya Mtaa n.k.).
 * Faili linahifadhiwa kwenye disk ya "local" (si la umma); linapakuliwa kupitia mfumo tu.
 */
class PersonDocument extends Model
{
    protected $fillable = ['person_id', 'type', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('approved');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return Person::DOCUMENT_TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function sizeForHumans(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1) . ' MB' : max(1, round($this->size / 1024)) . ' KB';
    }
}
