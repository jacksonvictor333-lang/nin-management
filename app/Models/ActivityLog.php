<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Rekodi kitendo cha mtumiaji aliyeingia.
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 500),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * Ikoni na rangi kwa kila kundi la vitendo (kwa ukurasa wa Activity Log).
     */
    public function presentation(): array
    {
        $group = strtok($this->action, '.');

        return match ($group) {
            'person' => ['bi-person-vcard', '#15803d', '#dcfce7'],
            'photo' => ['bi-camera', '#1d4ed8', '#dbeafe'],
            'signature' => ['bi-pen', '#9333ea', '#f3e8ff'],
            'user' => ['bi-person-gear', '#6d28d9', '#ede9fe'],
            'auth' => ['bi-box-arrow-in-right', '#0f766e', '#ccfbf1'],
            'api' => ['bi-key', '#b45309', '#fef3c7'],
            'data' => ['bi-magic', '#be185d', '#fce7f3'],
            'report' => ['bi-file-earmark-arrow-down', '#334155', '#f1f5f9'],
            'location' => ['bi-geo-alt', '#15803d', '#dcfce7'],
            'backup' => ['bi-database-down', '#0369a1', '#e0f2fe'],
            default => ['bi-activity', '#334155', '#f1f5f9'],
        };
    }
}
