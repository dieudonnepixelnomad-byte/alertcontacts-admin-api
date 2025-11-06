<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Administrator Email Addresses
    |--------------------------------------------------------------------------
    |
    | This value is a comma-separated list of email addresses that are allowed
    | to access the Filament admin panel. This provides an extra layer of
    | security to ensure only authorized users can log in.
    |
    */
    'emails' => array_filter(array_map('trim', explode(',', env('ADMIN_EMAILS', '')))),
];
