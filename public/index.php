<?php
require __DIR__ . '/../src/views.php';
ini_set('zend.exception_ignore_args', '1');
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    $status = $e instanceof LoginException ? 502 : ($e instanceof AuthorizationException ? 403 : ($e instanceof OutOfBoundsException ? 404 : ($e instanceof InvalidArgumentException ? 400 : 500)));
    http_response_code($status);
    echo document(['view' => 'error'], '<div class="page-heading"><h1>' . ($status === 500 ? 'Could not load this page' : e($e->getMessage())) . '</h1></div>' . ($status === 500 ? empty_message('The error has been logged. Please try again.') : ''));
});
require __DIR__ . '/../src/lib.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (auth_routes($db, $path)) exit;
if ($path === '/search' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = safe_name(parameter($_POST, 'name'));
    if (fetch_rows($db, 'SELECT id FROM players WHERE name = ?', [$name]) || track_player($db, $name, $now) !== null) {
        header('Location: /player/' . rawurlencode($name), true, 303);
        exit;
    }
    http_response_code(404);
    echo document(['view' => 'home'], '<div class="page-heading"><h1>Find player</h1></div>' . search_form($name, str_replace('_', ' ', $name) . ' is not on the hiscores.'));
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'])) {
    header('Allow: GET, HEAD' . ($path === '/search' ? ', POST' : ''));
    http_response_code(405);
    echo document(['view' => 'error'], empty_message('Method not allowed.'));
    exit;
}
$s = page_state($_SERVER['REQUEST_URI'], $_GET, $now) + site_context($db);
if (rawurldecode($path) !== $s['canonical']) {
    $query = http_build_query($_GET);
    header('Location: ' . $s['canonical'] . ($query === '' ? '' : '?' . $query), true, 301);
    exit;
}
$query = ['q' => match ($s['view']) {'players' => 'online', 'clans' => $s['clan'] === null ? 'clan-hiscores' : 'clan', default => $s['view']}, 'name' => $s['name'], 'type' => (string) $s['type'], 'period' => $s['period'], 'clan' => (string) $s['clan']];
if ($s['view'] === 'hiscores') $query += array_intersect_key($_GET, ['board' => true] + HISCORE_FILTERS);
$d = get_data($db, $query, $now);
if ($d === null) http_response_code(404);
echo document($s, page_body($s, $d));
