<?php
/**
 * Admin shell — header + sidebar + topbar.
 *
 * Expects: $pageTitle, $activeNav
 * Nav keys: overview|servers|channels|activity|about|users|settings|account
 */

require_once APP_ROOT . '/includes/icons.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';

$siteTitle = (string) setting('site_title', APP_NAME);
$adminUser = auth_user();
$isAdmin   = auth_is_admin();
$roleLabel = auth_role() === 'admin' ? 'Administrator' : 'Manager';
$initial   = strtoupper(mb_substr($adminUser !== '' ? $adminUser : 'U', 0, 1));
$base      = app_base_url();

$workspaceNav = [
    ['overview', 'Dashboard', 'dashboard', 'admin/index.php'],
    ['servers',  'Servers',   'server',    'admin/servers.php'],
    ['channels', 'Channels',  'tv',        'admin/channels.php'],
];

$systemNav = [
    ['activity', 'Activity', 'activity', 'admin/activity.php'],
    ['about',    'About',    'info',     'admin/about.php'],
];

$adminNav = [];
if ($isAdmin) {
    $adminNav = [
        ['users',    'Team',     'users',    'admin/users.php'],
        ['settings', 'Settings', 'settings', 'admin/settings.php'],
    ];
}

$navServers = servers_all();
$palette    = [];

foreach (array_merge($workspaceNav, $systemNav, $adminNav) as [$key, $label, $ico, $path]) {
    $palette[] = [
        'label' => $label,
        'meta'  => 'Page',
        'href'  => url($path),
        'icon'  => $ico,
    ];
}

$palette[] = [
    'label' => 'Add server',
    'meta'  => 'Action',
    'href'  => url('admin/servers.php?new=1'),
    'icon'  => 'plus',
];

$palette[] = [
    'label' => 'My account',
    'meta'  => 'Account',
    'href'  => url('admin/account.php'),
    'icon'  => 'user',
];

foreach ($navServers as $srv) {
    $palette[] = [
        'label' => $srv['title'] !== '' ? $srv['title'] : $srv['name'],
        'meta'  => 'Server · ' . $srv['name'],
        'href'  => url('admin/channels.php?server=' . rawurlencode($srv['name'])),
        'icon'  => 'tv',
    ];
}

$uxData = [
    'base'    => $base,
    'palette' => $palette,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> · <?= e($siteTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/app.css">
</head>
<body class="admin-body">
<?= icon_sprite() ?>

<div class="sidebar-scrim" data-sidebar-close></div>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="sidebar-brand" href="<?= e(url('admin/index.php')) ?>">
        <span class="brand-text">
            <strong><?= e($siteTitle) ?></strong>
            <small>Restream console</small>
        </span>
    </a>

    <nav class="nav">
        <span class="nav-label">Workspace</span>
        <?php foreach ($workspaceNav as [$key, $label, $ico, $path]): ?>
            <a class="nav-link <?= $activeNav === $key ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>">
                <?= icon($ico) ?>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>

        <span class="nav-label">Operations</span>
        <?php foreach ($systemNav as [$key, $label, $ico, $path]): ?>
            <a class="nav-link <?= $activeNav === $key ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>">
                <?= icon($ico) ?>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($adminNav): ?>
            <span class="nav-label">Administration</span>
            <?php foreach ($adminNav as [$key, $label, $ico, $path]): ?>
                <a class="nav-link <?= $activeNav === $key ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>">
                    <?= icon($ico) ?>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
        <a class="sidebar-account <?= $activeNav === 'account' ? 'is-active' : '' ?>" href="<?= e(url('admin/account.php')) ?>">
            <span class="avatar small"><?= e($initial) ?></span>
            <span class="sidebar-account-text">
                <strong><?= e($adminUser) ?></strong>
                <small><?= e($roleLabel) ?></small>
            </span>
        </a>
        <div class="sidebar-status">
            <span class="status-dot"></span>
            <span>v<?= e(APP_VERSION) ?> · SQLite</span>
        </div>
        <a class="nav-link nav-link-quiet" href="<?= e(url('logout.php')) ?>">
            <?= icon('logout') ?>
            <span>Sign out</span>
        </a>
    </div>
</aside>

<div class="shell">
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="icon-btn menu-btn" data-sidebar-toggle aria-label="Open menu">
                <?= icon('menu') ?>
            </button>
            <nav class="crumbs" aria-label="Breadcrumb">
                <span>Console</span>
                <?= icon('chevron-right', 'icon crumb-sep') ?>
                <strong><?= e($pageTitle) ?></strong>
            </nav>
        </div>

        <div class="topbar-right">
            <button type="button" class="palette-trigger" data-palette-open aria-label="Quick jump">
                <?= icon('search') ?>
                <span>Quick jump</span>
                <kbd>Ctrl K</kbd>
            </button>

            <a class="user-pill" href="<?= e(url('admin/account.php')) ?>" title="Open my account">
                <span class="avatar"><?= e($initial) ?></span>
                <span class="user-meta">
                    <span class="user-name"><?= e($adminUser) ?></span>
                    <span class="user-role <?= auth_role() === 'admin' ? 'is-admin' : '' ?>"><?= e($roleLabel) ?></span>
                </span>
            </a>

            <a class="icon-btn" href="<?= e(url('logout.php')) ?>" title="Sign out" aria-label="Sign out">
                <?= icon('logout') ?>
            </a>
        </div>
    </header>

    <main class="content">
