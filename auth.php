<?php
const CLANS = ['crandor' => ['name' => 'Crandor', 'icon' => '/assets/clans/crandor.png']];
class AuthorizationException extends RuntimeException {}

function auth_origin(string $key): string {
    $url = getenv($key);
    if (!$url) throw new RuntimeException("$key is not configured");
    $p = parse_url($url);
    if (!$p || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || !empty($p['path']) || !isset($p['scheme'], $p['host'])) throw new RuntimeException("Invalid $key origin");
    if ($p['scheme'] !== 'https' && !($p['scheme'] === 'http' && in_array($p['host'], ['127.0.0.1', 'localhost'], true))) throw new RuntimeException("$key must use HTTPS");
    return $url;
}
function auth_session(bool $create = false): void {
    if (session_status() === PHP_SESSION_ACTIVE || (!$create && !isset($_COOKIE['labs_session']))) return;
    ini_set('session.use_strict_mode', '1');
    session_name('labs_session');
    $directory = getenv('LABS_SESSION_PATH');
    if (!$directory || !is_dir($directory)) throw new RuntimeException('LABS_SESSION_PATH is not configured');
    session_save_path($directory);
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => str_starts_with(auth_origin('LABS_SITE_ORIGIN'), 'https://'), 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) throw new RuntimeException('Could not start the Labs session');
}
function auth_user(): ?array {
    auth_session();
    if (!isset($_SESSION['user'])) return null;
    if ($_SESSION['user']['expires'] <= time()) {unset($_SESSION['user']); return null;}
    return $_SESSION['user'];
}
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) throw new RuntimeException('CSRF token requested without a session');
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function require_csrf(array $post): void {
    if (!hash_equals(csrf_token(), parameter($post, 'csrf'))) throw new AuthorizationException('The form expired. Reload this page and try again.');
}
function clan_map(PDO $db): array {
    $clans = [];
    foreach (fetch_rows($db, 'SELECT name, clan FROM character_clans WHERE clan IS NOT NULL') as $row) {
        if (!isset(CLANS[$row['clan']])) throw new RuntimeException('Unknown stored clan');
        $clans[$row['name']] = $row['clan'];
    }
    return $clans;
}
function site_context(PDO $db): array {
    $user = auth_user();
    return ['user' => $user, 'csrf' => $user ? csrf_token() : null, 'clans' => clan_map($db)];
}
function sync_characters(PDO $db, string $discord, array $names): void {
    if (!preg_match('/^[0-9]{1,20}$/D', $discord) || !array_is_list($names) || count($names) > 200) throw new RuntimeException('Invalid Market identity response');
    $canonical = [];
    foreach ($names as $name) {
        if (!is_string($name)) throw new RuntimeException('Invalid Market character response');
        $name = safe_name($name);
        if (isset($canonical[$name])) throw new RuntimeException('Duplicate Market character');
        $canonical[$name] = true;
    }
    transaction($db, 'synchronizing linked characters', function () use ($db, $discord, $canonical) {
        foreach (fetch_rows($db, 'SELECT name FROM character_clans WHERE discord_id = ? FOR UPDATE', [$discord]) as $row) {
            if (!isset($canonical[$row['name']])) $db->prepare('DELETE FROM character_clans WHERE name = ? AND discord_id = ?')->execute([$row['name'], $discord]);
        }
        $save = $db->prepare('INSERT INTO character_clans (name, discord_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE clan = IF(discord_id = VALUES(discord_id), clan, NULL), discord_id = VALUES(discord_id)');
        foreach ($canonical as $name => $unused) $save->execute([$name, $discord]);
    });
}
function save_clans(PDO $db, string $discord, array $input): void {
    transaction($db, 'saving clans', function () use ($db, $discord, $input) {
        $owned = array_column(fetch_rows($db, 'SELECT name FROM character_clans WHERE discord_id = ? ORDER BY name FOR UPDATE', [$discord]), 'name');
        $names = array_map('strval', array_keys($input)); sort($names, SORT_STRING); sort($owned, SORT_STRING);
        if ($names !== $owned) throw new AuthorizationException('You can only change your linked characters.');
        foreach ($input as $clan) if (!is_string($clan) || ($clan !== '' && !isset(CLANS[$clan]))) throw new InvalidArgumentException('Unknown clan');
        $save = $db->prepare('UPDATE character_clans SET clan = ? WHERE name = ? AND discord_id = ?');
        foreach ($input as $name => $clan) $save->execute([$clan === '' ? null : $clan, $name, $discord]);
    });
}
function auth_routes(PDO $db, string $path): bool {
    if (!in_array($path, ['/login', '/login/callback', '/logout', '/settings'], true)) return false;
    header('Cache-Control: private, no-store');
    header('Referrer-Policy: no-referrer');
    $method = $_SERVER['REQUEST_METHOD'];
    $allowed = $path === '/settings' ? ['GET', 'POST'] : ($path === '/logout' ? ['POST'] : ['GET']);
    if (!in_array($method, $allowed, true)) {header('Allow: ' . implode(', ', $allowed)); http_response_code(405); echo document(['view' => 'error'], empty_message('Method not allowed.')); return true;}
    if ($path === '/login') {
        auth_session(true);
        $state = bin2hex(random_bytes(32)); $verifier = bin2hex(random_bytes(32));
        $_SESSION['login'] = ['state' => $state, 'verifier' => $verifier, 'at' => time()];
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        header('Location: ' . auth_origin('LABS_MARKET_ORIGIN') . '/auth/labs?' . http_build_query(['state' => $state, 'code_challenge' => $challenge]), true, 302);
        return true;
    }
    if ($path === '/login/callback') {
        auth_session();
        $pending = $_SESSION['login'] ?? null; unset($_SESSION['login']);
        if (!$pending || time() - $pending['at'] > 300 || !hash_equals($pending['state'], parameter($_GET, 'state')) || !preg_match('/^[a-f0-9]{64}$/D', parameter($_GET, 'code'))) throw new AuthorizationException('Invalid or expired sign-in. Please start again.');
        $body = file_get_contents(auth_origin('LABS_MARKET_ORIGIN') . '/api/labs/token', false, stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n", 'content' => http_build_query(['code' => $_GET['code'], 'code_verifier' => $pending['verifier']]), 'ignore_errors' => true, 'timeout' => 15]]));
        if ($body === false || !preg_match('~^HTTP/\S+ 200 ~', $http_response_header[0])) throw new RuntimeException('Market sign-in exchange failed');
        $identity = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (!isset($identity['discord_id'], $identity['usernames']) || !is_string($identity['discord_id']) || !is_array($identity['usernames'])) throw new RuntimeException('Incomplete Market identity response');
        sync_characters($db, $identity['discord_id'], $identity['usernames']);
        if (!session_regenerate_id(true)) throw new RuntimeException('Could not renew the Labs session');
        $_SESSION['user'] = ['id' => $identity['discord_id'], 'expires' => time() + 7200];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: /settings', true, 303); return true;
    }
    $user = auth_user();
    if (!$user) {header('Location: /login', true, 303); return true;}
    if ($method === 'POST') {
        require_csrf($_POST);
        if ($path === '/logout') {
            $_SESSION = []; session_destroy();
            $cookie = session_get_cookie_params(); unset($cookie['lifetime']); $cookie['expires'] = time() - 3600;
            setcookie('labs_session', '', $cookie);
            header('Location: /', true, 303); return true;
        }
        if (!isset($_POST['clans']) || !is_array($_POST['clans'])) throw new InvalidArgumentException('Invalid clan form');
        save_clans($db, $user['id'], $_POST['clans']);
        header('Location: /settings?saved=1', true, 303); return true;
    }
    $s = page_state('/settings', $_GET, (int) (microtime(true) * 1000)) + site_context($db) + ['saved' => parameter($_GET, 'saved') === '1'];
    $profiles = fetch_rows($db, 'SELECT name, clan FROM character_clans WHERE discord_id = ? ORDER BY name', [$user['id']]);
    echo document($s, settings_view($s, $profiles)); return true;
}
