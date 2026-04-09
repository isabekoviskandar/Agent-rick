<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramDossier extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'idiot_score',
        'known_facts',
        'behavioral_notes',
        'vulnerability_notes',
    ];

    protected function casts(): array
    {
        return [
            'known_facts' => 'array',
            'behavioral_notes' => 'array',
            'vulnerability_notes' => 'array',
        ];
    }
}
