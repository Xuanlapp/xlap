<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramConversation extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'chat_id',
        'state',
        'context',
        'last_message',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
