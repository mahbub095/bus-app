<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Envato Item ID
    |--------------------------------------------------------------------------
    |
    | The numeric ID of your CodeCanyon product. Used during license
    | verification to ensure the purchase code belongs to this item.
    |
    | Find it in your CodeCanyon item URL:
    |   https://codecanyon.net/item/sonyabus/XXXXXXXX
    |                                              ^^^^^^^^ this is the ID
    |
    */

    'item_id' => env('ENVATO_ITEM_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Envato Personal Token (Author)
    |--------------------------------------------------------------------------
    |
    | The Envato author's personal token used to verify purchase codes
    | via the Envato Market API. It is NEVER sent to the browser or
    | exposed to end users — it lives exclusively on the server.
    |
    | Generate at: https://build.envato.com/create-token/
    | Required permission: "View the user's sales history and search sales"
    |                      (for the /author/sale endpoint)
    |
    | Alternative permission (fallback endpoint):
    |   "View your purchases of items" (Verify purchases)
    */

    'personal_token' => env('ENVATO_PERSONAL_TOKEN', ''),

];
