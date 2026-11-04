<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | Name of the connection used by the Cpanel facade when no connection is
    | selected explicitly with Cpanel::connection().
    |
    */

    'default' => env('CPANEL_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Shared options
    |--------------------------------------------------------------------------
    |
    | Applied to every connection unless the connection overrides them.
    | "verify" may be true, false or a path to a CA bundle; disable it only
    | for servers with self-signed certificates on trusted networks.
    |
    */

    'verify' => env('CPANEL_VERIFY', true),
    'timeout' => (int) env('CPANEL_TIMEOUT', 60),
    'connect_timeout' => (int) env('CPANEL_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | "type" is "whm" for a root or reseller API token (WHM, port 2087) or
    | "cpanel" for a token of a single cPanel account (port 2083). The port
    | is added to "host" automatically when it is missing. Create tokens in
    | WHM (Development > Manage API Tokens) or cPanel (Security > Manage API
    | Tokens) and restrict them to the IP address of the application.
    |
    */

    'connections' => [

        'default' => [
            'type' => env('CPANEL_TYPE', 'whm'),
            'host' => env('CPANEL_HOST'),
            'username' => env('CPANEL_USERNAME', 'root'),
            'token' => env('CPANEL_TOKEN'),
        ],

    ],

];
