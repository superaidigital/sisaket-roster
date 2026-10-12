<?php
return [
    'db' => [
        'host' => '127.0.0.1', 'port' => 3306, 'name' => 'sisaket_roster',
        'user' => 'roster_app', 'password' => 'REPLACE_WITH_DATABASE_PASSWORD',
    ],
    'app' => [
        'name' => 'ตารางปฏิบัติงาน รพ.สต. อบจ.ศรีสะเกษ',
        'timezone' => 'Asia/Bangkok',
        'session_name' => 'SSK_ROSTER_SID',
        'cookie_secure' => false, // true when accessed with HTTPS
        'ip_hash_secret' => 'REPLACE_WITH_A_LONG_RANDOM_SECRET',
    ],
];
