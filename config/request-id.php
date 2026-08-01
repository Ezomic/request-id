<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Header name
    |--------------------------------------------------------------------------
    |
    | The header the id is read from on the way in and written to on the way
    | out. Changing this changes the contract with every other app, so it is
    | here for testing rather than for tuning.
    |
    */

    'header' => env('REQUEST_ID_HEADER', 'X-Request-Id'),

    /*
    |--------------------------------------------------------------------------
    | Log context key
    |--------------------------------------------------------------------------
    */

    'log_context_key' => env('REQUEST_ID_LOG_KEY', 'request_id'),

    /*
    |--------------------------------------------------------------------------
    | Echo the id back on the response
    |--------------------------------------------------------------------------
    |
    | Makes the id visible in the browser network tab, which is how you get
    | from "this page broke" to the matching entry in flare.
    |
    */

    'respond_with_header' => env('REQUEST_ID_RESPOND', true),

    /*
    |--------------------------------------------------------------------------
    | Outbound forwarding
    |--------------------------------------------------------------------------
    |
    | Attaches the current id to outgoing HTTP calls so a request can be traced
    | across apps. Restricted to an allowlist of our own hosts: forwarding an
    | internal correlation id to Stripe, Garmin or IBKR is pointless at best.
    |
    | Patterns are matched against the host with Str::is(), so "*.example.com"
    | matches one level of subdomain, as it does everywhere else in Laravel.
    |
    */

    'forward' => [
        'enabled' => env('REQUEST_ID_FORWARD', true),

        'hosts' => [
            'thijssensoftware.nl',
            '*.thijssensoftware.nl',
            '*.arbo.thijssensoftware.nl',
            '*.test',
            'localhost',
            '127.0.0.1',
        ],
    ],

];
