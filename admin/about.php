<?php
require_once __DIR__ . '/../config.php';
require_auth();

$servers   = servers_all();
$providers = count($servers);
$relay     = 0;
$direct    = 0;

foreach ($servers as $server) {
    if (($server['mode'] ?? 'proxy') === 'rewrite') {
        $direct++;
    } else {
        $relay++;
    }
}

$accounts     = users_all();
$adminCount   = 0;
$managerCount = 0;

foreach ($accounts as $account) {
    if ($account['role'] === 'admin') {
        $adminCount++;
    } else {
        $managerCount++;
    }
}

$checks  = system_checks();
$allGood = !in_array(false, array_column($checks, 'ok'), true);
$role    = auth_role() === 'admin' ? 'Administrator' : 'Manager';

$pageTitle = 'About';
$activeNav = 'about';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Product information</p>
        <h1>About</h1>
        <p class="muted">The build, purpose and moving parts behind this restream console.</p>
    </div>
    <div class="page-actions">
        <a class="btn primary" href="<?= e(url('admin/index.php')) ?>"><?= icon('dashboard') ?> Back to dashboard</a>
    </div>
</div>

<section class="about-hero">
    <div class="about-hero-copy">
        <span class="badge neutral mono">Release v<?= e(APP_VERSION) ?></span>
        <h2><?= e(setting('site_title', APP_NAME)) ?></h2>
        <p>
            A browser-based control room for Xtreme Codes providers. Connect your servers, browse
            channel libraries, publish M3U playlists and choose how every stream is delivered —
            without touching a configuration file.
        </p>
        <div class="about-meta">
            <span><?= icon('clock', 'icon inline-icon') ?> Updated <?= e(app_updated_label('j F Y')) ?></span>
            <span><?= icon('shield', 'icon inline-icon') ?> <?= e($role) ?> access</span>
            <span><?= icon('globe', 'icon inline-icon') ?> <?= e(url('')) ?></span>
        </div>
    </div>

    <div class="about-build">
        <span class="stat-label">Current build</span>
        <strong>v<?= e(APP_VERSION) ?></strong>
        <span class="badge <?= $allGood ? 'success' : 'warning' ?>"><?= $allGood ? 'Healthy' : 'Review' ?></span>
        <small class="muted mono">PHP <?= e(PHP_VERSION) ?> · SQLite</small>
    </div>
</section>

<section class="stat-grid">
    <article class="stat-card accent-brand">
        <div class="stat-icon"><?= icon('bolt') ?></div>
        <div>
            <span class="stat-label">Version</span>
            <strong>v<?= e(APP_VERSION) ?></strong>
            <small class="muted">Installed build</small>
        </div>
    </article>
    <article class="stat-card accent-success">
        <div class="stat-icon"><?= icon('clock') ?></div>
        <div>
            <span class="stat-label">Last update</span>
            <strong class="about-date"><?= e(app_updated_label('j M Y')) ?></strong>
            <small class="muted">Release date</small>
        </div>
    </article>
    <article class="stat-card accent-violet">
        <div class="stat-icon"><?= icon('server') ?></div>
        <div>
            <span class="stat-label">Providers</span>
            <strong><?= $providers ?></strong>
            <small class="muted"><?= $relay ?> relay · <?= $direct ?> direct</small>
        </div>
    </article>
    <article class="stat-card accent-cyan">
        <div class="stat-icon"><?= icon('users') ?></div>
        <div>
            <span class="stat-label">Team</span>
            <strong><?= count($accounts) ?></strong>
            <small class="muted"><?= $adminCount ?> admin · <?= $managerCount ?> manager</small>
        </div>
    </article>
</section>

<div class="about-grid">
    <section class="card pad">
        <div class="section-head">
            <h2>What this console does</h2>
            <p class="muted small">The whole workflow lives in one place.</p>
        </div>

        <ul class="about-feature-list">
            <li>
                <span class="about-feature-icon"><?= icon('server') ?></span>
                <div>
                    <strong>Provider control</strong>
                    <p>Add, edit, duplicate or pause Xtreme Codes providers, then test credentials and connection usage on demand.</p>
                </div>
            </li>
            <li>
                <span class="about-feature-icon"><?= icon('tv') ?></span>
                <div>
                    <strong>Channel library</strong>
                    <p>Browse live channels through the provider API with search, category filters and a cached, refreshable library.</p>
                </div>
            </li>
            <li>
                <span class="about-feature-icon"><?= icon('list') ?></span>
                <div>
                    <strong>M3U publishing</strong>
                    <p>Publish a playlist per provider and copy signed channel URLs that work in any HLS-compatible player.</p>
                </div>
            </li>
            <li>
                <span class="about-feature-icon"><?= icon('activity') ?></span>
                <div>
                    <strong>Audit trail</strong>
                    <p>Every server, channel, team and settings change is recorded with an actor and a timestamp.</p>
                </div>
            </li>
            <li>
                <span class="about-feature-icon"><?= icon('alert') ?></span>
                <div>
                    <strong>Honest failures</strong>
                    <p>There is no fallback playlist. Paused servers, missing channels and provider errors return real HTTP errors.</p>
                </div>
            </li>
        </ul>
    </section>

    <section class="card pad">
        <div class="section-head">
            <h2>Access &amp; roles</h2>
            <p class="muted small">Access is managed from the console, not from a config file.</p>
        </div>

        <div class="about-role">
            <div class="about-role-head">
                <span class="role-badge is-admin"><?= icon('shield') ?> Administrator</span>
                <strong>Full control</strong>
            </div>
            <p>Manages providers, channels and playlists, plus the team, settings, backups and cache controls.</p>
        </div>

        <div class="about-role">
            <div class="about-role-head">
                <span class="role-badge is-manager"><?= icon('user') ?> Manager</span>
                <strong>Day-to-day operations</strong>
            </div>
            <p>Operates servers, channels and playlists, and can review the activity trail. Team and settings stay admin-only.</p>
        </div>

        <p class="about-note muted">
            Passwords are stored as secure hashes. Provider credentials remain in the local SQLite database
            and are only sent to the provider when a stream or channel request needs them.
        </p>
    </section>
</div>

<section class="card pad">
    <div class="section-head">
        <h2>Delivery modes</h2>
        <p class="muted small">Choose a default in Settings or override it per server.</p>
    </div>

    <div class="about-mode-grid">
        <article class="about-mode">
            <span class="pill proxy">Relay</span>
            <h3>Relay through this server</h3>
            <p>Playlists, segments, keys and init segments are fetched and delivered by this installation, so viewers only see this server's address.</p>
            <ul>
                <li><?= icon('check') ?><span>Provider address stays hidden</span></li>
                <li><?= icon('check') ?><span>Uses this server's bandwidth</span></li>
            </ul>
        </article>

        <article class="about-mode">
            <span class="pill rewrite">Direct</span>
            <h3>Direct provider links</h3>
            <p>Playlists are rewritten with absolute provider URLs, so players connect straight to the provider instead of this server.</p>
            <ul>
                <li><?= icon('check') ?><span>Very light on this server's bandwidth</span></li>
                <li><?= icon('check') ?><span>Provider address is visible to viewers</span></li>
            </ul>
        </article>
    </div>
</section>

<section class="card pad">
    <div class="section-head about-system-head">
        <div>
            <h2>Runtime &amp; storage</h2>
            <p class="muted small">Environment details for this installation.</p>
        </div>
        <span class="badge <?= $allGood ? 'success' : 'warning' ?>"><?= $allGood ? 'Healthy' : 'Review' ?></span>
    </div>

    <div class="about-system-grid">
        <?php foreach ($checks as $check): ?>
            <div class="system-item">
                <span class="check-icon <?= $check['ok'] ? 'ok' : 'bad' ?>"><?= icon($check['ok'] ? 'check' : 'x') ?></span>
                <span><?= e($check['label']) ?></span>
                <span class="mono muted small"><?= e($check['value']) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="system-item">
            <span class="check-icon ok"><?= icon('database') ?></span>
            <span>Database</span>
            <span class="mono muted small">SQLite · <?= $providers ?> servers · <?= count($accounts) ?> users</span>
        </div>
        <div class="system-item">
            <span class="check-icon ok"><?= icon('clock') ?></span>
            <span>Last update</span>
            <span class="mono muted small"><?= e(app_updated_label('j F Y')) ?></span>
        </div>
        <div class="system-item">
            <span class="check-icon ok"><?= icon('shield') ?></span>
            <span>Version</span>
            <span class="mono muted small">v<?= e(APP_VERSION) ?></span>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
