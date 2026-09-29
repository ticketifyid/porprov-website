<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun admin awal
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh database/seeders/AdminUserSeeder.php untuk membuat akun
    | admin pertama. Diakses lewat config(), bukan env() langsung, supaya
    | tetap benar saat config di-cache (config:cache) di shared hosting.
    |
    */

    'admin' => [
        'username' => env('ADMIN_USERNAME'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
