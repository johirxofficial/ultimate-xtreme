<?php
/**
 * Public M3U playlist generator.
 *
 *   /playlist/{server}.m3u
 *   /playlist.php?server={server}
 *
 * Produces a ready-to-import playlist where every channel points at this
 * installation's signed /live endpoint, so proxy/rewrite settings still apply.
 * There is no offline fallback: failures return a real HTTP error.
 */

require_once __DIR__ . '/config.php';

header('X-Robots-Tag: noindex, nofollow', true);

$slug = trim(qget('server'));

if ($slug === '') {
    stream_error(404, 'No playlist was requested.');
}

$server = server_by_name($slug);

if (!$server) {
    stream_error(404, 'The requested server does not exist.');
}

if ((int) $server['status'] !== 1) {
    stream_error(503, 'This server is paused in the console.');
}

if ((int) ($server['playlist'] ?? 1) !== 1) {
    stream_error(404, 'M3U publishing is disabled for this server.');
}

$library = channels_fetch($server);

if (!$library['ok']) {
    stream_error(502, 'The channel library could not be loaded: ' . ($library['error'] ?: 'provider unavailable'));
}

$body = playlist_m3u($server, $library['streams']);
$name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $server['name']);

header('Content-Type: audio/x-mpegurl; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $name . '.m3u"');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');
header('X-Playlist-Channels: ' . count($library['streams']));

echo $body;
