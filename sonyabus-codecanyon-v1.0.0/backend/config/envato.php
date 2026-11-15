<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Envato Item ID
    |--------------------------------------------------------------------------
    |
    | The numeric ID of your CodeCanyon product.
    | Used to confirm the purchase code belongs to THIS product.
    |
    | Where to find it:
    |   https://codecanyon.net/item/sonyabus/XXXXXXXX
    |                                         ^^^^^^^^ this number
    |
    */
    'item_id' => env('ENVATO_ITEM_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Envato Author Personal Token
    |--------------------------------------------------------------------------
    |
    | YOUR personal token as the seller/author. Used by the server to call
    | the Envato Market API and verify buyers' purchase codes.
    |
    | Buyers only enter their purchase code — they NEVER see this token.
    |
    | How to generate:
    |   1. Go to https://build.envato.com/create-token/
    |   2. Log in with your Envato AUTHOR account
    |   3. Enable permission: "View the user's sales history and search sales"
    |   4. Click "Create Token" and copy it here
    |
    | Security: Keep this token secret. Never commit it to version control.
    |
    */
    'personal_token' => env('ENVATO_PERSONAL_TOKEN', ''),

];
