<?php
/**
 * EV Catalog configuration.
 *
 * 1. Copy this file to config.php (same folder).
 * 2. Fill in the MySQL details you created in cPanel > MySQL Databases.
 *
 * config.php takes priority. If it does not exist, the app reads .env instead
 * (see .env.example). Use whichever you prefer — you only need one.
 */
return [
    'app' => [
        'name'      => 'EV Catalog',
        'url'       => 'https://example.com',   // your public URL, no trailing slash
        'base_path' => '',                      // '' for domain root, '/ev' if installed in public_html/ev
        'debug'     => false,                   // NEVER true on a live site
        'timezone'  => 'UTC',
    ],

    'db' => [
        'host'    => 'localhost',               // cPanel MySQL is almost always "localhost"
        'port'    => 3306,
        'name'    => 'cpaneluser_evcatalog',    // cPanel prefixes names with your account user
        'user'    => 'cpaneluser_evuser',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'evcatalog_session',
        'lifetime' => 7200,                     // idle timeout in seconds
    ],

    'uploads' => [
        'max_bytes' => 5 * 1024 * 1024,         // 5 MB per image
    ],
];
