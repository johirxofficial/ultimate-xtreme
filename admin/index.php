<?php
require_once __DIR__ . '/../config.php';
require_auth();

$servers  = servers_all();
$total    = count($servers);
$active   = 0;
$proxy    = 0;
$rewrite  = 0;
$playlist = 0;

foreach ($servers as $srv) {
    if ((int) $srv['status'] === 1) {
        $active++;
    }
    if (($srv['mode'] ?? 'proxy') === 'rewrite') {
        $rewrite++;
    } else {
        $proxy++;
    }
    if ((int) ($srv['playlist'] ?? 1) === 1) {
        $playlist++;
    }
}

$activity = activity_recent(7);
$checks   = system_checks();
$allGood  = !in_array(false, array_column($checks, 'ok'), true);
$team     = users_count();
$isAdmin  = auth_is_admin();
$role     = auth_role() === 'admin' ? 'Administrator' : 'Manager';

$pageTitle = 'Dashboard';
$activeNav = 'overview';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Overview · <?= e($role) ?></p>
        <h1>Good to see you, <?= e(auth_user()) ?>.</h1>
        <p class="muted">Here is how your restream stack is doing right now.</p>
    </div>
    <div class="page-actions">
        <?php if ($isAdmin): ?>
            <a class="btn ghost" href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?> Team</a>
        <?php endif; ?>
        <a class="btn ghost" href="<?= e(url('admin/channels.php')) ?>"><?= icon('tv') ?> Channels</a>
        <a class="btn primary" href="<?= e(url('admin/servers.php?new=1')) ?>"><?= icon('plus') ?> Add server</a>
    </div>
</div>

<section class="stat-grid">
    <article class="stat-card accent-brand">
        <div class="stat-icon"><?= icon('server') ?></div>
        <div>
            <span class="stat-label">Total servers</span>
            <strong><?= $total ?></strong>
            <small class="muted"><?= $playlist ?> publishing M3U</small>
        </div>
    </article>
    <article class="stat-card accent-success">
        <div class="stat-icon"><?= icon('activity') ?></div>
        <div>
            <span class="stat-label">Active now</span>
            <strong><?= $active ?></strong>
            <small class="muted"><?= $total - $active ?> paused</small>
        </div>
    </article>
    <article class="stat-card accent-violet">
        <div class="stat-icon"><?= icon('users') ?></div>
        <div>
            <span class="stat-label">Team accounts</span>
            <strong><?= $team ?></strong>
            <small class="muted"><?= $isAdmin ? '<a href="' . e(url('admin/users.php')) . '">Manage access</a>' : 'Admin-managed' ?></small>
        </div>
    </article>
    <article class="stat-card accent-cyan">
        <div class="stat-icon"><?= icon('bolt') ?></div>
        <div>
            <span class="stat-label">Direct delivery</span>
            <strong><?= $rewrite ?></strong>
            <small class="muted"><?= $proxy ?> relay · <?= $rewrite ?> direct</small>
        </div>
    </article>
</section>

<div class="grid-main">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Server status</h2>
                <p class="muted small">Live availability across your providers.</p>
            </div>
            <a class="btn ghost xs" href="<?= e(url('admin/servers.php')) ?>">Manage</a>
        </div>

        <?php if (!$servers): ?>
            <div class="empty">
                <span class="empty-icon"><?= icon('server') ?></span>
                <h3>No servers yet</h3>
                <p class="muted">Connect your first provider to start restreaming.</p>
                <a class="btn primary" href="<?= e(url('admin/servers.php?new=1')) ?>"><?= icon('plus') ?> Add server</a>
            </div>
        <?php else: ?>
            <ul class="server-list">
                <?php foreach (array_slice($servers, 0, 6) as $srv): ?>
                    <?php $isOn = (int) $srv['status'] === 1; ?>
                    <li class="server-list-item">
                        <span class="status-pill <?= $isOn ? 'on' : 'off' ?>">
                            <span class="status-dot"></span><?= $isOn ? 'Online' : 'Offline' ?>
                        </span>
                        <div class="server-list-main">
                            <a class="server-name" href="<?= e(url('admin/channels.php?server=' . rawurlencode($srv['name']))) ?>">
                                <?= e($srv['title'] !== '' ? $srv['title'] : $srv['name']) ?>
                            </a>
                            <span class="mono muted small"><?= e($srv['host']) ?></span>
                        </div>
                        <span class="pill <?= ($srv['mode'] ?? 'proxy') === 'rewrite' ? 'rewrite' : 'proxy' ?>">
                            <?= e(delivery_mode_label($srv['mode'] ?? 'proxy')) ?>
                        </span>
                        <a class="icon-btn" href="<?= e(url('admin/channels.php?server=' . rawurlencode($srv['name']))) ?>"
                           title="Browse channels" aria-label="Browse channels">
                            <?= icon('chevron-right') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>System health</h2>
                <p class="muted small">Runtime checks for this installation.</p>
            </div>
            <span class="badge <?= $allGood ? 'success' : 'warning' ?>">
                <?= $allGood ? 'Healthy' : 'Review' ?>
            </span>
        </div>
        <ul class="check-list">
            <?php foreach ($checks as $check): ?>
                <li>
                    <span class="check-icon <?= $check['ok'] ? 'ok' : 'bad' ?>">
                        <?= icon($check['ok'] ? 'check' : 'x') ?>
                    </span>
                    <span class="check-label"><?= e($check['label']) ?></span>
                    <span class="mono muted small"><?= e($check['value']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Endpoints</h2>
            <p class="muted small">Copy these into your player or IPTV app.</p>
        </div>
        <span class="badge neutral mono"><?= e(url('')) ?></span>
    </div>

    <div class="endpoint-list">
        <div class="endpoint-row">
            <span class="endpoint-label"><?= icon('tv') ?> Channel stream</span>
            <code><?= e(url('live')) ?>/<em>{server}</em>/<em>{channel}</em>.m3u8</code>
            <button type="button" class="icon-btn" data-copy="<?= e(url('live')) ?>/{server}/{channel}.m3u8" title="Copy template" aria-label="Copy template"><?= icon('copy') ?></button>
        </div>
        <div class="endpoint-row">
            <span class="endpoint-label"><?= icon('list') ?> Full M3U playlist</span>
            <code><?= e(url('playlist')) ?>/<em>{server}</em>.m3u</code>
            <button type="button" class="icon-btn" data-copy="<?= e(url('playlist')) ?>/{server}.m3u" title="Copy template" aria-label="Copy template"><?= icon('copy') ?></button>
        </div>
        <div class="endpoint-row">
            <span class="endpoint-label"><?= icon('link') ?> Legacy endpoint</span>
            <code><?= e(url('re-index.php')) ?>?server=<em>{server}</em>&amp;id=<em>{channel}</em></code>
            <button type="button" class="icon-btn" data-copy="<?= e(url('re-index.php')) ?>?server={server}&id={channel}" title="Copy template" aria-label="Copy template"><?= icon('copy') ?></button>
        </div>
        <div class="endpoint-row">
            <span class="endpoint-label"><?= icon('shield') ?> Delivery</span>
            <code>Relay hides the provider address · Direct links players to the provider</code>
            <span class="endpoint-note">per server</span>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Recent activity</h2>
            <p class="muted small">The latest changes in this console.</p>
        </div>
        <a class="btn ghost xs" href="<?= e(url('admin/activity.php')) ?>">View all</a>
    </div>

    <?php if (!$activity): ?>
        <div class="empty slim">
            <span class="empty-icon"><?= icon('clock') ?></span>
            <p class="muted">No activity recorded yet.</p>
        </div>
    <?php else: ?>
        <ul class="activity-list">
            <?php foreach ($activity as $row): ?>
                <?php $meta = activity_meta($row['action']); ?>
                <li>
                    <span class="activity-dot <?= e($meta['tone']) ?>"></span>
                    <div class="activity-main">
                        <strong><?= e($meta['label']) ?></strong>
                        <span class="muted small"><?= e($row['detail']) ?></span>
                    </div>
                    <?php if (!empty($row['actor'])): ?>
                        <span class="activity-actor"><?= e($row['actor']) ?></span>
                    <?php endif; ?>
                    <time class="muted small" datetime="<?= e($row['at']) ?>"><?= e(time_ago($row['at'])) ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
