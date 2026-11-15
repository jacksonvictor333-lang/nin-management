<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NinVerification extends Model
{
    protected $fillable = [
        'nin',
        'session_token_hash',
        'questions',
        'questions_answered',
        'correct_answers',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'questions' => 'array',
        'questions_answered' => 'integer',
        'correct_answers' => 'integer',
        'expires_at' => 'datetime',
    ];
}
