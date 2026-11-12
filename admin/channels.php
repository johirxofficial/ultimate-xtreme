<?php
require_once __DIR__ . '/../config.php';
require_auth();

$slug = trim(qget('server'));

if ($slug === '') {
    $servers   = servers_all();
    $pageTitle = 'Channels';
    $activeNav = 'channels';
    require __DIR__ . '/partials/header.php';
    ?>
    <div class="page-head">
        <div>
            <p class="eyebrow">Library</p>
            <h1>Channels</h1>
            <p class="muted">Pick a provider to browse its live channel library.</p>
        </div>
        <div class="page-actions">
            <a class="btn ghost" href="<?= e(url('admin/servers.php')) ?>"><?= icon('server') ?> Manage servers</a>
        </div>
    </div>

    <?php if (!$servers): ?>
        <div class="card">
            <div class="empty">
                <span class="empty-icon"><?= icon('tv') ?></span>
                <h3>No providers connected</h3>
                <p class="muted">Add a server first, then come back to browse channels.</p>
                <a class="btn primary" href="<?= e(url('admin/servers.php?new=1')) ?>"><?= icon('plus') ?> Add server</a>
            </div>
        </div>
    <?php else: ?>
        <section class="server-picker">
            <?php foreach ($servers as $srv): ?>
                <?php $isOn = (int) $srv['status'] === 1; ?>
                <a class="picker-card" href="<?= e(url('admin/channels.php?server=' . rawurlencode($srv['name']))) ?>">
                    <div class="picker-top">
                        <span class="picker-avatar"><?= e(strtoupper(substr($srv['title'] !== '' ? $srv['title'] : $srv['name'], 0, 1))) ?></span>
                        <span class="status-pill <?= $isOn ? 'on' : 'off' ?>">
                            <span class="status-dot"></span><?= $isOn ? 'Online' : 'Offline' ?>
                        </span>
                    </div>
                    <strong><?= e($srv['title'] !== '' ? $srv['title'] : $srv['name']) ?></strong>
                    <span class="mono muted small ellipsis"><?= e($srv['host']) ?></span>
                    <span class="picker-foot">
                        <span class="pill <?= ($srv['mode'] ?? 'proxy') === 'rewrite' ? 'rewrite' : 'proxy' ?>">
                            <?= e(delivery_mode_label($srv['mode'] ?? 'proxy')) ?>
                        </span>
                        <span class="picker-go">Browse <?= icon('chevron-right') ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php require __DIR__ . '/partials/footer.php'; ?>
    <?php
    exit;
}

$server = server_by_name($slug);

if (!$server) {
    flash('error', 'That server does not exist.');
    redirect('admin/channels.php');
}

$refresh = qget('refresh') === '1';
$library = channels_fetch($server, $refresh);

$pageTitle = 'Channels · ' . ($server['title'] !== '' ? $server['title'] : $server['name']);
$activeNav = 'channels';
require __DIR__ . '/partials/header.php';

$isOn      = (int) $server['status'] === 1;
$isRewrite = ($server['mode'] ?? 'proxy') === 'rewrite';
$m3uOn     = (int) ($server['playlist'] ?? 1) === 1;
$display   = $server['title'] !== '' ? $server['title'] : $server['name'];

$q   = trim(qget('q'));
$cat = trim(qget('cat'));
$all = $library['ok'] ? $library['streams'] : [];
$categories = $library['ok'] ? $library['categories'] : [];

$filtered = $all;
if ($library['ok']) {
    if ($q !== '') {
        $needle   = mb_strtolower($q);
        $filtered = array_values(array_filter($filtered, static function (array $ch) use ($needle): bool {
            return str_contains(mb_strtolower($ch['name']), $needle)
                || str_contains(mb_strtolower($ch['id']), $needle)
                || str_contains(mb_strtolower($ch['cat']), $needle);
        }));
    }
    if ($cat !== '') {
        $filtered = array_values(array_filter($filtered, static fn(array $ch): bool => (string) $ch['cat_id'] === $cat));
    }
}

$perPage = 50;
$total   = count($filtered);
$pages   = max(1, (int) ceil($total / $perPage));
$page    = max(1, min($pages, (int) qget('page', '1')));
$slice   = array_slice($filtered, ($page - 1) * $perPage, $perPage);

$pageUrl = static function (int $p) use ($server, $q, $cat): string {
    return url('admin/channels.php') . '?' . http_build_query(array_filter([
        'server' => $server['name'],
        'q'      => $q,
        'cat'    => $cat,
        'page'   => $p,
    ], static fn($v) => $v !== '' && $v !== null));
};
?>

<div class="page-head">
    <div>
        <div class="head-with-back">
            <a class="icon-btn" href="<?= e(url('admin/channels.php')) ?>" title="All servers" aria-label="All servers"><?= icon('chevron-left') ?></a>
            <div>
                <p class="eyebrow">Channel library</p>
                <h1><?= e($display) ?></h1>
            </div>
        </div>
        <p class="muted mono small"><?= e($server['host']) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn ghost" href="<?= e(url('admin/channels.php?server=' . rawurlencode($server['name']) . '&refresh=1')) ?>">
            <?= icon('refresh') ?> Refresh
        </a>
        <?php if ($m3uOn): ?>
            <button type="button" class="btn ghost" data-copy="<?= e(playlist_download_url($server['name'])) ?>">
                <?= icon('link') ?> Copy M3U URL
            </button>
            <a class="btn primary" href="<?= e(playlist_download_url($server['name'])) ?>">
                <?= icon('download') ?> Download M3U
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="chip-row">
    <span class="status-pill <?= $isOn ? 'on' : 'off' ?>"><span class="status-dot"></span><?= $isOn ? 'Online' : 'Offline' ?></span>
    <span class="pill <?= $isRewrite ? 'rewrite' : 'proxy' ?>"><?= e(delivery_mode_label($server['mode'] ?? 'proxy')) ?> mode</span>
    <span class="pill <?= $m3uOn ? 'neutral' : 'off' ?>"><?= $m3uOn ? 'M3U enabled' : 'M3U disabled' ?></span>
    <?php if (!empty($library['fetched'])): ?>
        <span class="chip muted"><?= icon('clock', 'icon inline-icon') ?> Updated <?= e(time_ago(date('Y-m-d H:i:s', (int) $library['fetched']))) ?></span>
    <?php endif; ?>
    <?php if (!empty($library['cached'])): ?>
        <span class="chip muted">cached</span>
    <?php endif; ?>
</div>

<?php if ($library['ok']): ?>
    <section class="stat-grid compact">
        <article class="stat-card">
            <div class="stat-icon"><?= icon('tv') ?></div>
            <div><span class="stat-label">Channels</span><strong><?= count($all) ?></strong></div>
        </article>
        <article class="stat-card">
            <div class="stat-icon"><?= icon('list') ?></div>
            <div><span class="stat-label">Categories</span><strong><?= count($categories) ?></strong></div>
        </article>
        <article class="stat-card">
            <div class="stat-icon"><?= icon('filter') ?></div>
            <div><span class="stat-label">Showing</span><strong><?= $total ?></strong></div>
        </article>
        <article class="stat-card">
            <div class="stat-icon"><?= icon('play') ?></div>
            <div><span class="stat-label">Page</span><strong><?= $page ?><small class="muted"> / <?= $pages ?></small></strong></div>
        </article>
    </section>

    <?php if (!$isOn): ?>
        <div class="alert warning"><?= icon('alert') ?><span>This server is paused. Streams return an error until you switch it back on.</span></div>
    <?php endif; ?>

    <section class="card">
        <div class="toolbar">
            <form class="toolbar-search" method="get" action="<?= e(url('admin/channels.php')) ?>">
                <input type="hidden" name="server" value="<?= e($server['name']) ?>">
                <input type="hidden" name="cat" value="<?= e($cat) ?>">
                <?= icon('search') ?>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search channels by name or id…" autocomplete="off" spellcheck="false">
            </form>

            <div class="toolbar-filters">
                <form method="get" action="<?= e(url('admin/channels.php')) ?>">
                    <input type="hidden" name="server" value="<?= e($server['name']) ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <label class="select-wrap">
                        <?= icon('filter') ?>
                        <select name="cat" data-auto-submit aria-label="Filter by category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category['id']) ?>" <?= $cat === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>

                <?php if ($q !== '' || $cat !== ''): ?>
                    <a class="btn ghost xs" href="<?= e(url('admin/channels.php?server=' . rawurlencode($server['name']))) ?>"><?= icon('x') ?> Clear</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$slice): ?>
            <div class="empty">
                <span class="empty-icon"><?= icon('search') ?></span>
                <h3>No channels found</h3>
                <p class="muted">Try a different search term or category.</p>
                <a class="btn ghost" href="<?= e(url('admin/channels.php?server=' . rawurlencode($server['name']))) ?>">Reset filters</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table channel-table">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Category</th>
                            <th>Stream ID</th>
                            <th class="right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slice as $ch): ?>
                            <?php
                            $liveUrl = playlist_base($server['name']) . rawurlencode($ch['id']) . '.m3u8';
                            $hasLogo = preg_match('~^https?://~i', $ch['icon']) === 1;
                            ?>
                            <tr>
                                <td>
                                    <div class="channel-cell">
                                        <span class="ch-logo">
                                            <span class="ch-logo-fallback"><?= e(mb_strtoupper(mb_substr($ch['name'], 0, 1))) ?></span>
                                            <?php if ($hasLogo): ?>
                                                <img src="<?= e($ch['icon']) ?>" alt="" loading="lazy" onerror="this.remove()">
                                            <?php endif; ?>
                                        </span>
                                        <div class="cell-stack">
                                            <span class="cell-title"><?= e($ch['name']) ?></span>
                                            <span class="cell-sub mono"><?= e($server['name']) ?>/<?= e($ch['id']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge neutral"><?= e($ch['cat']) ?></span></td>
                                <td><span class="mono"><?= e($ch['id']) ?></span></td>
                                <td class="right">
                                    <div class="row-actions">
                                        <button type="button" class="icon-btn" data-copy="<?= e($liveUrl) ?>" title="Copy stream URL" aria-label="Copy stream URL"><?= icon('copy') ?></button>
                                        <a class="icon-btn" href="<?= e($liveUrl) ?>" target="_blank" rel="noopener" title="Open stream" aria-label="Open stream"><?= icon('external') ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a class="btn ghost xs" href="<?= e($pageUrl($page - 1)) ?>"><?= icon('chevron-left') ?> Prev</a>
                    <?php else: ?>
                        <span class="btn ghost xs disabled"><?= icon('chevron-left') ?> Prev</span>
                    <?php endif; ?>

                    <span class="pagination-info">Page <strong><?= $page ?></strong> of <?= $pages ?> · <?= $total ?> channels</span>

                    <?php if ($page < $pages): ?>
                        <a class="btn ghost xs" href="<?= e($pageUrl($page + 1)) ?>">Next <?= icon('chevron-right') ?></a>
                    <?php else: ?>
                        <span class="btn ghost xs disabled">Next <?= icon('chevron-right') ?></span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card">
        <div class="empty">
            <span class="empty-icon bad"><?= icon('alert') ?></span>
            <h3>Could not load channels</h3>
            <p class="muted"><?= e($library['error'] ?: 'The provider did not return a channel library.') ?></p>
            <div class="empty-actions">
                <a class="btn primary" href="<?= e(url('admin/channels.php?server=' . rawurlencode($server['name']) . '&refresh=1')) ?>"><?= icon('refresh') ?> Try again</a>
                <a class="btn ghost" href="<?= e(url('admin/servers.php')) ?>">Open servers</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
