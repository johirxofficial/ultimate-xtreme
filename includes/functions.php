<?php
/**
 * Shared helpers: URLs, HTML, payload signing, upstream API, playlist output.
 *
 * Nothing here starts a session on its own. Public streaming requests must
 * never take a PHP session lock.
 */

const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

// =========================================================================
// Session + basic helpers
// =========================================================================

function app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_name('uxsession');
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function qget(string $key, string $default = ''): string
{
    return (isset($_GET[$key]) && is_string($_GET[$key])) ? $_GET[$key] : $default;
}

function pget(string $key, string $default = ''): string
{
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? $_POST[$key] : $default;
}

// =========================================================================
// URLs
// =========================================================================

function app_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir    = rtrim(dirname($script), '/');

    // Admin lives one level below the application root.
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }

    if ($dir === '.' || $dir === '\\' || $dir === '/') {
        $dir = '';
    }

    return $base = $scheme . '://' . $host . $dir;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');

    return app_base_url() . ($path === '' ? '' : '/' . $path);
}

function stream_base(string $serverName): string
{
    return app_base_url() . '/stream/' . rawurlencode($serverName) . '/';
}

function playlist_proxy_base(string $serverName): string
{
    return app_base_url() . '/pl/' . rawurlencode($serverName) . '/';
}

function playlist_base(string $serverName): string
{
    return app_base_url() . '/live/' . rawurlencode($serverName) . '/';
}

function playlist_download_url(string $serverName): string
{
    return app_base_url() . '/playlist/' . rawurlencode($serverName) . '.m3u';
}

function redirect(string $path): void
{
    $url = preg_match('~^https?://~i', $path) ? $path : url($path);
    header('Location: ' . $url, true, 302);
    exit;
}

// =========================================================================
// Flash + JSON
// =========================================================================

function flash(string $type, string $message): void
{
    app_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type: string, message: string}> */
function flash_take(): array
{
    app_session();
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($items) ? $items : [];
}

function json_out($data): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// Payload signing (segment + nested playlist URLs)
// =========================================================================

function base64_url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64_url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad  = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }

    $decoded = base64_decode($data, true);

    return $decoded === false ? '' : $decoded;
}

function app_secret(): string
{
    return (string) setting('app_secret', '');
}

function payload_for(string $url, string $ext = ''): string
{
    $sig     = substr(hash_hmac('sha256', $url, app_secret()), 0, 16);
    $payload = base64_url_encode($sig . '|' . $url);
    $ext     = preg_replace('/[^a-z0-9]/i', '', $ext);

    return $ext !== '' ? $payload . '.' . $ext : $payload;
}

function payload_decode(string $payload): ?string
{
    $payload = preg_replace('/\.[a-z0-9]{1,6}$/i', '', $payload);
    $raw     = base64_url_decode((string) $payload);

    if ($raw === '' || strpos($raw, '|') === false) {
        return null;
    }

    [$sig, $url] = explode('|', $raw, 2);

    if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $expected = substr(hash_hmac('sha256', $url, app_secret()), 0, 16);

    return hash_equals($expected, (string) $sig) ? $url : null;
}

// =========================================================================
// Upstream request helpers
// =========================================================================

function client_ua(array $server): string
{
    $ua = trim((string) ($server['user_agent'] ?? ''));

    return $ua !== '' ? $ua : DEFAULT_USER_AGENT;
}

function cookie_file(array $server): string
{
    $key = md5((string) ($server['name'] ?? '') . '|' . (string) ($server['username'] ?? ''));

    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ux_cookie_' . $key . '.txt';
}

function build_target_url(array $server, string $channel): string
{
    $host = rtrim(trim((string) ($server['host'] ?? '')), '/');

    return $host
        . '/live/' . rawurlencode((string) ($server['username'] ?? ''))
        . '/' . rawurlencode((string) ($server['password'] ?? ''))
        . '/' . rawurlencode($channel) . '.m3u8';
}

function host_is_valid(string $host): bool
{
    return $host !== ''
        && filter_var($host, FILTER_VALIDATE_URL) !== false
        && preg_match('~^https?://~i', $host) === 1;
}

function username_is_valid(string $username): bool
{
    return preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $username) === 1;
}

/**
 * @return array{body: string|false, code: int, url: string, error: string}
 */
function fetch_url(string $url, array $server, int $timeout = 20): array
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => client_ua($server),
        CURLOPT_COOKIEJAR      => cookie_file($server),
        CURLOPT_COOKIEFILE     => cookie_file($server),
        CURLOPT_ENCODING       => '',
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Accept: */*',
            'Connection: keep-alive',
        ],
    ]);

    $body  = curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = (string) curl_error($ch);

    curl_close($ch);

    return ['body' => $body, 'code' => $code, 'url' => $final, 'error' => $error];
}

/**
 * Call an Xtreme Codes player_api action.
 *
 * @return array{ok: bool, data: mixed, error: string}
 */
function api_get(array $server, array $params = [], int $timeout = 25): array
{
    $host = rtrim(trim((string) ($server['host'] ?? '')), '/');

    if (!host_is_valid($host)) {
        return ['ok' => false, 'data' => null, 'error' => 'Invalid provider host URL.'];
    }

    $query = array_merge([
        'username' => (string) ($server['username'] ?? ''),
        'password' => (string) ($server['password'] ?? ''),
    ], $params);

    $res = fetch_url($host . '/player_api.php?' . http_build_query($query), $server, $timeout);

    if ($res['body'] === false) {
        return ['ok' => false, 'data' => null, 'error' => 'Connection failed: ' . ($res['error'] ?: 'unknown error')];
    }
    if ($res['code'] !== 200) {
        return ['ok' => false, 'data' => null, 'error' => 'Provider returned HTTP ' . $res['code'] . '.'];
    }

    $json = json_decode((string) $res['body'], true);

    if (!is_array($json)) {
        return ['ok' => false, 'data' => null, 'error' => 'Provider did not return valid JSON.'];
    }

    return ['ok' => true, 'data' => $json, 'error' => ''];
}

/**
 * Quick account check used by the dashboard "Test" button.
 *
 * @return array{ok: bool, message: string}
 */
function test_connection(array $server): array
{
    $res = api_get($server, [], 15);

    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['error']];
    }

    $info = $res['data']['user_info'] ?? null;
    if (!is_array($info)) {
        return ['ok' => false, 'message' => 'Unexpected API response (no user_info).'];
    }

    $auth   = (int) ($info['auth'] ?? 0);
    $status = (string) ($info['status'] ?? 'Unknown');
    $exp    = $info['exp_date'] ?? null;
    $expTxt = '';

    if (is_numeric($exp) && (int) $exp > 0) {
        $expTxt = ' · expires ' . date('d M Y', (int) $exp);
    }

    if ($auth === 1) {
        $cons = (string) ($info['active_cons'] ?? '0');
        $max  = (string) ($info['max_connections'] ?? '?');

        return [
            'ok'      => true,
            'message' => 'Connected · ' . $status . $expTxt . ' · ' . $cons . '/' . $max . ' connections',
        ];
    }

    return ['ok' => false, 'message' => 'Authentication failed (' . $status . ').'];
}

// =========================================================================
// Playlist fetching + rewriting
// =========================================================================

function uri_ext(string $url, string $default = 'ts'): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $ext  = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

    return in_array($ext, ['ts', 'm4s', 'mp4', 'aac', 'mp3', 'key', 'bin'], true) ? $ext : $default;
}

function content_type_for_ext(string $ext): string
{
    switch (strtolower($ext)) {
        case 'ts':
            return 'video/mp2t';
        case 'm4s':
            return 'video/iso.segment';
        case 'mp4':
            return 'video/mp4';
        case 'aac':
            return 'audio/aac';
        case 'mp3':
            return 'audio/mpeg';
        case 'm3u8':
        case 'm3u':
            return 'application/vnd.apple.mpegurl';
        default:
            return 'application/octet-stream';
    }
}

function absolute_url(string $uri, string $effectiveUrl): string
{
    $uri = trim($uri);

    if (preg_match('~^https?://~i', $uri)) {
        return $uri;
    }

    $parts  = parse_url($effectiveUrl);
    $scheme = $parts['scheme'] ?? 'http';
    $root   = $scheme . '://' . ($parts['host'] ?? '');

    if (isset($parts['port'])) {
        $root .= ':' . $parts['port'];
    }

    if (strpos($uri, '//') === 0) {
        return $scheme . ':' . $uri;
    }

    if (strpos($uri, '/') === 0) {
        return $root . $uri;
    }

    $dir = preg_replace('~/[^/]*$~', '/', $effectiveUrl);

    return $dir . $uri;
}

function rewrite_playlist(string $content, array $server, string $effectiveUrl): string
{
    $mode  = ($server['mode'] ?? 'proxy') === 'rewrite' ? 'rewrite' : 'proxy';
    $name  = (string) $server['name'];
    $lines = preg_split("/\r\n|\n|\r/", trim($content));

    if (!$lines) {
        return "#EXTM3U\n";
    }

    $out = [];
    if (stripos(ltrim($lines[0]), '#EXTM3U') !== 0) {
        $out[] = '#EXTM3U';
    }

    $nextIsPlaylist = false;

    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '') {
            continue;
        }

        if ($line[0] === '#') {
            $tag = '';
            if (preg_match('/^#([A-Z0-9-]+)/i', $line, $m)) {
                $tag = strtoupper($m[1]);
            }

            if (stripos($line, 'URI="') !== false) {
                $line = preg_replace_callback(
                    '/URI="([^"]+)"/i',
                    static function (array $m) use ($effectiveUrl, $mode, $name, $tag): string {
                        $abs = absolute_url($m[1], $effectiveUrl);

                        if ($mode === 'rewrite') {
                            return 'URI="' . $abs . '"';
                        }

                        $isPlaylist = in_array($tag, [
                            'EXT-X-MEDIA',
                            'EXT-X-I-FRAME-STREAM-INF',
                            'EXT-X-IMAGE-STREAM-INF',
                            'EXT-X-SESSION-DATA',
                        ], true);

                        if ($isPlaylist) {
                            return 'URI="' . playlist_proxy_base($name) . payload_for($abs, 'm3u8') . '"';
                        }

                        $ext = $tag === 'EXT-X-KEY' ? uri_ext($abs, 'key') : uri_ext($abs, 'ts');

                        return 'URI="' . stream_base($name) . payload_for($abs, $ext) . '"';
                    },
                    $line
                );
            }

            if ($tag === 'EXT-X-STREAM-INF') {
                $nextIsPlaylist = true;
            }

            $out[] = $line;
            continue;
        }

        $abs = absolute_url($line, $effectiveUrl);

        if ($mode === 'rewrite') {
            $out[] = $abs;
        } elseif ($nextIsPlaylist) {
            $out[] = playlist_proxy_base($name) . payload_for($abs, 'm3u8');
        } else {
            $out[] = stream_base($name) . payload_for($abs, uri_ext($abs, 'ts'));
        }

        $nextIsPlaylist = false;
    }

    return implode("\n", $out) . "\n";
}

function send_hls_headers(string $filename = 'playlist.m3u8'): void
{
    $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);

    header('Content-Type: application/vnd.apple.mpegurl; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Disposition: inline; filename="' . $safe . '"');
}

/**
 * Return a real HTTP error instead of an offline fallback playlist.
 * Players show the error; operators can inspect the status and message.
 */
function stream_error(int $code, string $message): void
{
    if ($code < 400 || $code > 599) {
        $code = 502;
    }

    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store');
        header('X-Stream-Status: error');
    }

    error_log('Ultimate Xtreme stream error [' . $code . ']: ' . $message);

    echo 'Ultimate Xtreme stream error (' . $code . ')' . "\n";
    echo $message . "\n";

    exit;
}

// =========================================================================
// Channel library + generated M3U
// =========================================================================

function channels_cache_dir(): string
{
    $dir = DATA_DIR . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

/**
 * Fetch (and cache) the live channel library for a provider.
 *
 * @return array{ok: bool, error: string, categories: array, streams: array, fetched: int, cached: bool}
 */
function channels_fetch(array $server, bool $refresh = false): array
{
    $cacheDir = channels_cache_dir();
    $key      = substr(md5(
        (string) ($server['name'] ?? '') . '|'
        . (string) ($server['host'] ?? '') . '|'
        . (string) ($server['username'] ?? '') . '|'
        . (string) ($server['password'] ?? '')
    ), 0, 16);
    $file = $cacheDir . DIRECTORY_SEPARATOR . 'channels_' . $key . '.json';
    $ttl  = max(60, (int) setting('cache_ttl', '600'));

    if (!$refresh && is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
        $raw    = @file_get_contents($file);
        $cached = $raw !== false ? json_decode($raw, true) : null;

        if (is_array($cached) && isset($cached['streams']) && is_array($cached['streams'])) {
            $cached['ok']     = true;
            $cached['cached'] = true;

            return $cached;
        }
    }

    $catsRes = api_get($server, ['action' => 'get_live_categories']);
    if (!$catsRes['ok']) {
        return ['ok' => false, 'error' => $catsRes['error'], 'categories' => [], 'streams' => [], 'fetched' => time(), 'cached' => false];
    }

    $streamsRes = api_get($server, ['action' => 'get_live_streams'], 45);
    if (!$streamsRes['ok']) {
        return ['ok' => false, 'error' => $streamsRes['error'], 'categories' => [], 'streams' => [], 'fetched' => time(), 'cached' => false];
    }

    $catMap = [];
    foreach ((array) $catsRes['data'] as $cat) {
        if (is_array($cat)) {
            $catMap[(string) ($cat['category_id'] ?? '')] = trim((string) ($cat['category_name'] ?? ''));
        }
    }

    $streams = [];
    foreach ((array) $streamsRes['data'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = (string) ($item['stream_id'] ?? '');
        if ($id === '') {
            continue;
        }

        $catId = (string) ($item['category_id'] ?? '');
        $name  = trim((string) ($item['name'] ?? ''));

        $streams[] = [
            'id'     => $id,
            'name'   => $name !== '' ? $name : 'Channel ' . $id,
            'icon'   => (string) ($item['stream_icon'] ?? ''),
            'cat_id' => $catId,
            'cat'    => $catMap[$catId] ?? 'Uncategorized',
            'num'    => (string) ($item['num'] ?? ''),
        ];
    }

    usort($streams, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

    $categories = [];
    foreach ($catMap as $id => $name) {
        $categories[] = ['id' => (string) $id, 'name' => $name !== '' ? $name : 'Uncategorized'];
    }
    usort($categories, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

    $payload = [
        'ok'         => true,
        'error'      => '',
        'categories' => $categories,
        'streams'    => $streams,
        'fetched'    => time(),
        'cached'     => false,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }

    return $payload;
}

function channels_cache_clear(): int
{
    $dir   = channels_cache_dir();
    $files = glob($dir . DIRECTORY_SEPARATOR . 'channels_*.json') ?: [];
    $count = 0;

    foreach ($files as $file) {
        if (@unlink($file)) {
            $count++;
        }
    }

    return $count;
}

function m3u_attr(string $value): string
{
    $value = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $value);

    return trim($value);
}

function playlist_m3u(array $server, array $streams): string
{
    $base  = rtrim(app_base_url(), '/');
    $name  = (string) $server['name'];
    $lines = ['#EXTM3U'];

    $title = trim((string) ($server['title'] ?? ''));
    if ($title === '') {
        $title = $name;
    }
    $lines[] = '#PLAYLIST:' . m3u_attr($title);

    foreach ($streams as $ch) {
        $attrs = [
            'tvg-id="' . m3u_attr((string) $ch['id']) . '"',
            'tvg-name="' . m3u_attr((string) $ch['name']) . '"',
        ];

        if (($ch['icon'] ?? '') !== '') {
            $attrs[] = 'tvg-logo="' . m3u_attr((string) $ch['icon']) . '"';
        }

        $attrs[] = 'group-title="' . m3u_attr((string) ($ch['cat'] ?? 'Uncategorized')) . '"';

        $lines[] = '#EXTINF:-1 ' . implode(' ', $attrs) . ',' . m3u_attr((string) $ch['name']);
        $lines[] = $base . '/live/' . rawurlencode($name) . '/' . rawurlencode((string) $ch['id']) . '.m3u8';
    }

    return implode("\n", $lines) . "\n";
}

// =========================================================================
// Misc display helpers
// =========================================================================

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }

    $ts = strtotime($datetime);
    if (!$ts) {
        return '—';
    }

    $diff = time() - $ts;

    if ($diff < 45) {
        return 'just now';
    }
    if ($diff < 3600) {
        return max(1, (int) floor($diff / 60)) . 'm ago';
    }
    if ($diff < 86400) {
        return max(1, (int) floor($diff / 3600)) . 'h ago';
    }
    if ($diff < 604800) {
        return max(1, (int) floor($diff / 86400)) . 'd ago';
    }

    return date('d M Y', $ts);
}

/** @return array{label: string, tone: string} */
function activity_meta(string $action): array
{
    $map = [
        'login'           => ['Signed in', 'info'],
        'logout'          => ['Signed out', 'neutral'],
        'setup'           => ['First admin created', 'success'],
        'server.create'   => ['Server added', 'success'],
        'server.update'   => ['Server updated', 'info'],
        'server.delete'   => ['Server deleted', 'danger'],
        'server.toggle'   => ['Server toggled', 'warning'],
        'user.create'     => ['Team member added', 'success'],
        'user.update'     => ['Team member updated', 'info'],
        'user.delete'     => ['Team member removed', 'danger'],
        'user.toggle'     => ['Team member toggled', 'warning'],
        'account.password'=> ['Password changed', 'warning'],
        'settings.update' => ['Settings updated', 'info'],
        'data.import'     => ['Backup imported', 'warning'],
        'cache.clear'     => ['Cache cleared', 'neutral'],
        'activity.clear'  => ['Activity cleared', 'danger'],
    ];

    return [
        'label' => $map[$action][0] ?? ucfirst(str_replace(['.', '_'], ' ', $action)),
        'tone'  => $map[$action][1] ?? 'neutral',
    ];
}

/**
 * User-facing label for an internal delivery mode value.
 *
 * The database keeps `proxy` and `rewrite` for compatibility, but the console
 * presents the modes as Relay and Direct.
 */
function delivery_mode_label(string $mode): string
{
    return $mode === 'rewrite' ? 'Direct' : 'Relay';
}

/** Human-readable release date for the current build. */
function app_updated_label(string $format = 'j M Y'): string
{
    $time = strtotime(APP_UPDATED);

    return $time ? date($format, $time) : APP_UPDATED;
}

/** @return array<int, array{label: string, ok: bool, value: string}> */
function system_checks(): array
{
    $writable = is_dir(DATA_DIR) && is_writable(DATA_DIR);

    return [
        ['label' => 'PHP runtime',   'ok' => version_compare(PHP_VERSION, '8.0', '>='), 'value' => PHP_VERSION],
        ['label' => 'SQLite (PDO)',  'ok' => extension_loaded('pdo_sqlite'),            'value' => extension_loaded('pdo_sqlite') ? 'available' : 'missing'],
        ['label' => 'cURL',          'ok' => extension_loaded('curl'),                  'value' => extension_loaded('curl') ? 'available' : 'missing'],
        ['label' => 'Data folder',   'ok' => $writable,                                 'value' => $writable ? 'writable' : 'not writable'],
        ['label' => 'OpenSSL',       'ok' => extension_loaded('openssl'),               'value' => extension_loaded('openssl') ? 'available' : 'missing'],
    ];
}
