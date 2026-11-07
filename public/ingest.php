<?php
// The game website POSTs a tracked player's hiscores to /api/hiscores/callback/:username when they log out.
require __DIR__ . '/../src/lib.php';
if (!hash_equals($env('LABS_API_KEY'), $_SERVER['HTTP_X_API_KEY'] ?? '')) {
    error_log('Labs callback rejected; headers sent: ' . implode(', ', array_keys(getallheaders())));
    http_response_code(401);
    exit;
}
$stats = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['imported' => import_stats($db, safe_name($_GET['username'] ?? ''), $stats, $now, true)]);
