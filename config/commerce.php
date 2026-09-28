<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default commission rate
    |--------------------------------------------------------------------------
    |
    | TDD §7.4: commissions are computed per order_group at "the seller's
    | tier-based commission rate." No seller-tiering module exists yet, so
    | every order_group is charged this single platform-wide rate until
    | one does (App\Services\CommissionService).
    |
    */
    'default_commission_rate' => env('COMMERCE_DEFAULT_COMMISSION_RATE', '0.10'),

    /*
    |--------------------------------------------------------------------------
    | Checkout session TTL
    |--------------------------------------------------------------------------
    |
    | TDD §3.4 module 18: "Ephemeral, expires after 30 minutes of
    | inactivity."
    |
    */
    'checkout_session_ttl_minutes' => 30,

];
