<?php
$env = fn(string $key) => getenv($key) ?: throw new RuntimeException("$key is not set");
$db = new PDO($env('LABS_DB_DSN'), $env('LABS_DB_USER'), getenv('LABS_DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
const PERIODS = ['24h' => 86400000, '7d' => 604800000, '30d' => 2592000000];
