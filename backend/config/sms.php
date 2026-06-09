<?php

return [
    'gateway_name' => env('SMS_GATEWAY_NAME', 'SMS.NET.BD'),
    'gateway_driver' => env('SMS_GATEWAY_DRIVER', 'smsnetbd'),
    'api_url' => env('SMS_GATEWAY_API_URL', 'https://api.sms.net.bd/sendsms'),
    'api_key' => env('SMS_GATEWAY_API_KEY', env('SMS_NETBD_API_KEY')),
    'sender_id' => env('SMS_GATEWAY_SENDER_ID'),
    'is_active' => filter_var(env('SMS_GATEWAY_ACTIVE', true), FILTER_VALIDATE_BOOLEAN),
    'message_template' => env(
        'SMS_GATEWAY_MESSAGE_TEMPLATE',
        'SonyaBus ticket confirmed. PNR {PNR}, Seats {SEATS}, Fare BDT {FARE}. Status: {STATUS}'
    ),
];
