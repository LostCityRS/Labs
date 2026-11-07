<?php
class AuthorizationException extends RuntimeException {}
class LoginException extends RuntimeException {}

function auth_value(string $key): string {
    return getenv($key) ?: throw new RuntimeException("$key is not configured");
}
function auth_json(string $service, string $url, array $http = []): array {
    $body = @file_get_contents($url, false, stream_context_create(['http' => $http + ['ignore_errors' => true, 'timeout' => 15, 'follow_location' => 0]]));
    $status = isset($http_response_header[0]) && preg_match('~^HTTP/\\S+ (\\d{3}) ~', $http_response_header[0], $match) ? (int) $match[1] : 0;
    if ($body === false || $status !== 200) throw new LoginException("$service failed (HTTP $status). Please start sign-in again.");
    try {$data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);}
    catch (JsonException $e) {throw new LoginException("$service returned invalid JSON.");}
    if (!is_array($data)) throw new LoginException("$service returned an invalid response.");
    return $data;
}
function auth_base64(string $value): string {return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');}
function linked_characters(array $identity): array {
    if (!isset($identity['id'], $identity['email']) || !is_string($identity['id']) || !preg_match('/^[0-9]{1,20}$/D', $identity['id']) || !is_string($identity['email']) || !filter_var($identity['email'], FILTER_VALIDATE_EMAIL) || ($identity['verified'] ?? null) !== true) throw new AuthorizationException('Discord must provide a verified email address to find your Lost City characters.');
    $at = time();
    $claims = ['iss' => auth_origin('LABS_SITE_ORIGIN'), 'sub' => $identity['id'], 'iat' => $at, 'nbf' => $at, 'exp' => $at + 60, 'jti' => bin2hex(random_bytes(16))];
    $jwt = auth_base64(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])) . '.' . auth_base64(json_encode($claims));
    $jwt .= '.' . auth_base64(hash_hmac('sha256', $jwt, auth_value('LABS_USERNAME_JWT_SECRET'), true));
    $result = auth_json('Lost City character lookup', auth_value('LABS_USERNAME_API_URL') . '?' . http_build_query(['email' => $identity['email']]), ['header' => "Accept: application/json\r\nAuthorization: Bearer $jwt\r\n"]);
    if (!isset($result['usernames']) || !is_array($result['usernames']) || !array_is_list($result['usernames'])) throw new LoginException('Lost City returned an invalid character list.');
    return $result['usernames'];
}

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
    if (!preg_match('/^[0-9]{1,20}$/D', $discord) || !array_is_list($names) || count($names) > 200) throw new RuntimeException('Invalid Discord identity response');
    $canonical = [];
    foreach ($names as $name) {
        if (!is_string($name)) throw new RuntimeException('Invalid Lost City character response');
        $name = safe_name($name);
        if (isset($canonical[$name])) throw new RuntimeException('Duplicate Lost City character');
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
        $state = bin2hex(random_bytes(32));
        $_SESSION['login'] = ['state' => $state, 'at' => time()];
        header('Location: ' . auth_origin('LABS_DISCORD_ORIGIN') . '/oauth2/authorize?' . http_build_query(['client_id' => auth_value('LABS_DISCORD_CLIENT_ID'), 'redirect_uri' => auth_origin('LABS_SITE_ORIGIN') . '/login/callback', 'response_type' => 'code', 'scope' => 'identify email', 'prompt' => 'none', 'state' => $state]), true, 302);
        return true;
    }
    if ($path === '/login/callback') {
        auth_session();
        $pending = $_SESSION['login'] ?? null; unset($_SESSION['login']);
        if (!$pending || time() - $pending['at'] > 300 || !hash_equals($pending['state'], parameter($_GET, 'state'))) throw new AuthorizationException('Invalid or expired sign-in. Please start again.');
        if (parameter($_GET, 'error') !== '') throw new AuthorizationException('Discord sign-in was cancelled.');
        $code = parameter($_GET, 'code');
        if ($code === '' || strlen($code) > 512) throw new AuthorizationException('Discord did not return a valid authorization code.');
        $discord = auth_origin('LABS_DISCORD_ORIGIN');
        $token = auth_json('Discord token exchange', $discord . '/api/oauth2/token', ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n", 'content' => http_build_query(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => auth_value('LABS_DISCORD_CLIENT_ID'), 'client_secret' => auth_value('LABS_DISCORD_CLIENT_SECRET'), 'redirect_uri' => auth_origin('LABS_SITE_ORIGIN') . '/login/callback'])]);
        if (!isset($token['access_token']) || !is_string($token['access_token']) || !preg_match('/^[A-Za-z0-9._-]+$/D', $token['access_token'])) throw new LoginException('Discord did not return a valid access token.');
        $identity = auth_json('Discord identity lookup', $discord . '/api/v10/users/@me', ['header' => "Accept: application/json\r\nAuthorization: Bearer {$token['access_token']}\r\n"]);
        $names = linked_characters($identity);
        sync_characters($db, $identity['id'], $names);
        if (!session_regenerate_id(true)) throw new RuntimeException('Could not renew the Labs session');
        $_SESSION['user'] = ['id' => $identity['id'], 'expires' => time() + 7200];
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
    $profiles = fetch_rows($db, 'SELECT cc.name, cc.clan, (SELECT MAX(x.at) FROM players p JOIN xp x ON x.player_id = p.id WHERE p.name = cc.name) AS updated_at FROM character_clans cc WHERE cc.discord_id = ? ORDER BY updated_at IS NULL, updated_at DESC, cc.name', [$user['id']]);
    echo document($s, settings_view($s, $profiles)); return true;
}
