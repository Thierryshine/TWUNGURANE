<?php

/**
 * TWUNGURANE - Configuration des services externes
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Service Python (Analyses Financières)
    |--------------------------------------------------------------------------
    */
    'python' => [
        'url' => env('PYTHON_SERVICE_URL', 'http://python:8000'),
        'token' => env('PYTHON_SERVICE_TOKEN'),
        'timeout' => 30,
        'retry' => [
            'times' => 3,
            'sleep' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Money - Lumicash (Lumitel)
    |--------------------------------------------------------------------------
    */
    'lumicash' => [
        'url' => env('LUMICASH_API_URL', 'https://api.lumicash.bi'),
        'key' => env('LUMICASH_API_KEY'),
        'secret' => env('LUMICASH_API_SECRET'),
        'merchant_id' => env('LUMICASH_MERCHANT_ID'),
        'sandbox' => env('LUMICASH_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Money - EcoCash (Econet Leo)
    |--------------------------------------------------------------------------
    */
    'ecocash' => [
        'url' => env('ECOCASH_API_URL', 'https://api.ecocash.bi'),
        'key' => env('ECOCASH_API_KEY'),
        'secret' => env('ECOCASH_API_SECRET'),
        'merchant_id' => env('ECOCASH_MERCHANT_ID'),
        'sandbox' => env('ECOCASH_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Money - M-Pesa (Vodacom)
    |--------------------------------------------------------------------------
    */
    'mpesa' => [
        'url' => env('MPESA_API_URL', 'https://api.vodacom.bi'),
        'key' => env('MPESA_API_KEY'),
        'secret' => env('MPESA_API_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'sandbox' => env('MPESA_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS (pour OTP)
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'providers' => [
            'twilio' => [
                'sid' => env('TWILIO_SID'),
                'token' => env('TWILIO_TOKEN'),
                'from' => env('TWILIO_FROM'),
            ],
        ],
    ],

];
