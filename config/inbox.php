<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Capture switch
    |--------------------------------------------------------------------------
    |
    | Null follows the application environment: mail is captured only when
    | APP_ENV is local. Any other environment rejects the inbox mailer and
    | leaves the inbox routes unregistered. INBOX_ENABLED=false turns capture
    | off even in local development.
    |
    */

    'enabled' => env('INBOX_ENABLED'),

    'path' => env('INBOX_PATH', '_inbox'),

    'storage' => env('INBOX_STORAGE'),

];
