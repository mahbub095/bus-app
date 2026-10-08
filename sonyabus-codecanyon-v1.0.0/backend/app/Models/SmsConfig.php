<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsConfig extends Model
{
    protected $fillable = [
        'gateway_name',
        'gateway_driver',
        'api_url',
        'api_key',
        'sender_id',
        'is_active',
        'message_template',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
