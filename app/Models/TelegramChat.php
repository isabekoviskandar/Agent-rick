<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramChat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'telegram_chat_id',
        'conversation_id',
        'username',
        'first_name',
    ];
}
