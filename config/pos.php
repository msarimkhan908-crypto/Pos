<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 code used when displaying money across the POS.
    |
    */

    'currency' => env('POS_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Store name
    |--------------------------------------------------------------------------
    |
    | Printed at the top of receipts. Full receipt customization comes later.
    |
    */

    'store_name' => env('POS_STORE_NAME', env('APP_NAME', 'POS')),

    /*
    |--------------------------------------------------------------------------
    | Print on checkout
    |--------------------------------------------------------------------------
    |
    | When an order is placed the POS prints the customer bill and the kitchen
    | order ticket (KOT) in one print job, each on its own page. Set to false
    | to print only when the cashier presses a print button.
    |
    */

    'auto_print' => (bool) env('POS_AUTO_PRINT', true),

];
