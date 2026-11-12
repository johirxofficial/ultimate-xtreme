<?php
/**
 * Ultimate Xtreme — Unified Restream Endpoint
 *
 *   /live/{server}/{channel}.m3u8   -> channel playlist
 *   /pl/{server}/{payload}.m3u8     -> nested playlist (master / variants)
 *   /stream/{server}/{payload}.ts   -> proxied segment or key
 *
 * Two delivery modes are supported per server (set in the dashboard):
 *   proxy   : every segment/key/playlist is fetched and relayed by this host
 *   rewrite : the playlist is rewritten with absolute upstream URLs
 *
 * There is no offline fallback. If a server, channel or upstream request
 * fails, a real HTTP error is returned so players and operators see it.
 */

require_once __DIR__ . '/config.php';

header('X-Robots-Tag: noindex, nofollow', true);

$serverName = trim(qget('server'));

if ($serverName === '' || (qget('id') === '' && qget('ts') === '' && qget('pl') === '')) {
    // Plain visit -> dashboard. Never expose anything here.
    redirect('admin/');
}

$server = server_by_name($serverName);

if (!$server) {
    stream_error(404, 'The requested server does not exist.');
}

if ((int) $server['status'] !== 1) {
    stream_error(503, 'This server is paused in the console.');
}

// =========================================================================
// 1) Segment / key relay (streaming, no buffering)
// =========================================================================
if (qget('ts') !== '') {
    $target = payload_decode(qget('ts'));

    if ($target === null) {
        stream_error(400, 'The segment link is invalid or expired.');
    }

    $ext          = strtolower(preg_replace('/[^a-z0-9]/i', '', qget('ext', 'ts')));
    $fallbackType = content_type_for_ext($ext);
    $contentType  = null;
    $started      = false;

    set_time_limit(0);
    while (ob_get_level()) {
        ob_end_clean();
    }

    $cookie = cookie_file($server);
    $ch     = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $target,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => client_ua($server),
        CURLOPT_COOKIEJAR      => $cookie,
        CURLOPT_COOKIEFILE     => $cookie,
        CURLOPT_ENCODING       => '',
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Accept: */*',
            'Connection: keep-alive',
        ],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$contentType): int {
            if (stripos($header, 'content-type:') === 0) {
                $contentType = trim(substr($header, 13));
            }

            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION  => static function ($curl, string $data) use (&$started, &$contentType, $fallbackType): int {
            if (!$started) {
                http_response_code(200);
                header('Content-Type: ' . (($contentType !== null && $contentType !== '') ? $contentType : $fallbackType));
                header('Access-Control-Allow-Origin: *');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('X-Accel-Buffering: no');
                $started = true;
            }

            echo $data;
            flush();

            return strlen($data);
        },
    ]);

    curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = trim((string) curl_error($ch));
    curl_close($ch);

    if (!$started) {
        $status = ($code >= 400 && $code < 600) ? $code : 502;
        $reason = $code > 0
            ? 'The provider responded with HTTP ' . $code . '.'
            : 'The provider could not be reached' . ($error !== '' ? ': ' . $error : '.');

        stream_error($status, $reason);
    }

    exit;
}

// =========================================================================
// 2) Nested playlist relay (master playlists / variants / media playlists)
// =========================================================================
if (qget('pl') !== '') {
    $source = payload_decode(qget('pl'));

    if ($source === null) {
        stream_error(400, 'The playlist link is invalid or expired.');
    }

    $result = fetch_url($source, $server);
    $code   = (int) ($result['code'] ?? 0);

    if ($result['body'] === false || $code !== 200) {
        $status = ($code >= 400 && $code < 600) ? $code : 502;
        $reason = $code > 0
            ? 'The provider playlist failed with HTTP ' . $code . '.'
            : 'The provider playlist could not be reached.';

        stream_error($status, $reason);
    }

    if (strpos((string) $result['body'], '#EXT') === false) {
        stream_error(502, 'The provider returned an invalid playlist.');
    }

    $effective = $result['url'] !== '' ? $result['url'] : $source;

    send_hls_headers($server['name'] . '_stream.m3u8');
    echo rewrite_playlist((string) $result['body'], $server, $effective);
    exit;
}

// =========================================================================
// 3) Channel playlist
// =========================================================================
$channel = trim(qget('id'));
$channel = preg_replace('/\.m3u8?$/i', '', $channel);

if ($channel === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $channel)) {
    stream_error(400, 'The channel id is missing or invalid.');
}

$target = build_target_url($server, $channel);
$result = fetch_url($target, $server);
$code   = (int) ($result['code'] ?? 0);

if ($result['body'] === false || $code !== 200) {
    $status = ($code >= 400 && $code < 600) ? $code : 502;
    $reason = $code > 0
        ? 'The provider channel returned HTTP ' . $code . '.'
        : 'The provider channel could not be reached.';

    stream_error($status, $reason);
}

if (strpos((string) $result['body'], '#EXT') === false) {
    stream_error(502, 'The provider returned an invalid channel playlist.');
}

$effective = $result['url'] !== '' ? $result['url'] : $target;

send_hls_headers($server['name'] . '_' . $channel . '.m3u8');
echo rewrite_playlist((string) $result['body'], $server, $effective);
exit;
