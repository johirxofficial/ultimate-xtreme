<?php
/**
 * Ultimate Xtreme — Application Bootstrap
 *
 * This file replaces the old static server array. Server accounts now live in
 * SQLite and are managed from the dashboard.
 */

define('APP_NAME', 'Ultimate Xtreme');
define('APP_VERSION', '4.0');
define('APP_UPDATED', '2026-11-12');
define('APP_ROOT', __DIR__);
define('DATA_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'data');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
@header_remove('X-Powered-By');

require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';
