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

];
