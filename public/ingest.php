<?php
// The game server POSTs here when a player logs out.
require __DIR__ . '/../lib.php';
if (!hash_equals('Bearer ' . $env('LABS_PUSH_TOKEN'), $_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
    http_response_code(401);
    exit;
}
$rows = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['imported' => import_rows($db, $rows, $now)]);
