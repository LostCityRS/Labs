<?php
header('Content-Type: application/json');
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    $bad = $e instanceof InvalidArgumentException;
    http_response_code($bad ? 400 : 500);
    echo json_encode(['error' => $bad ? $e->getMessage() : 'Server error']);
});
require __DIR__ . '/../lib.php';
$result = get_data($db, $_GET, $now);
if ($result === null) http_response_code(404);
echo json_encode($result, flags: JSON_THROW_ON_ERROR);
