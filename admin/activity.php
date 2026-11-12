<?php
require_once __DIR__ . '/../config.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(pget('csrf'))) {
        flash('error', 'Security token expired. Please try again.');
        redirect('admin/activity.php');
    }

    if (pget('action') === 'clear') {
        if (!auth_is_admin()) {
            flash('error', 'Only administrators can clear the audit trail.');
            redirect('admin/activity.php');
        }

        activity_clear();
        activity_log('activity.clear', 'All activity history removed');
        flash('success', 'Activity history cleared.');
    }

    redirect('admin/activity.php');
}

$perPage = 30;
$q       = trim(qget('q'));
$filter  = trim(qget('event'));
$q       = mb_substr($q, 0, 80);
$filter  = preg_replace('/[^a-z0-9._-]/i', '', $filter);

$total = activity_count($q, $filter);
$pages = max(1, (int) ceil($total / $perPage));
$page  = max(1, min($pages, (int) qget('page', '1')));
$rows  = activity_recent($perPage, ($page - 1) * $perPage, $q, $filter);
$acts  = activity_actions();

$query = array_filter([
    'q'     => $q,
    'event' => $filter,
], static fn(string $v): bool => $v !== '');

$pageUrl = static function (int $p) use ($query): string {
    $params = $query;
    if ($p > 1) {
        $params['page'] = $p;
    }

    return url('admin/activity.php') . ($params ? '?' . http_build_query($params) : '');
};

$filtered = $q !== '' || $filter !== '';

$pageTitle = 'Activity';
$activeNav = 'activity';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Audit trail</p>
        <h1>Activity</h1>
        <p class="muted">
            <?= $total ?> event<?= $total === 1 ? '' : 's' ?><?= $filtered ? ' matching your filters' : ' recorded' ?> · newest first
        </p>
    </div>
    <?php if (auth_is_admin() && activity_count() > 0): ?>
        <div class="page-actions">
            <form method="post"
                  data-confirm="Clear the entire activity history? This cannot be undone."
                  data-confirm-label="Clear history">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="clear">
                <button class="btn danger ghost-danger" type="submit"><?= icon('trash') ?> Clear history</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<section class="card">
    <form class="toolbar" method="get">
        <div class="toolbar-search">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search detail, actor, action or IP…"
                   autocomplete="off" spellcheck="false" aria-label="Search activity">
        </div>

        <div class="toolbar-filters">
            <label class="select-wrap">
                <?= icon('filter') ?>
                <select name="event" aria-label="Filter by event type" data-auto-submit>
                    <option value="">All events</option>
                    <?php foreach ($acts as $action): ?>
                        <?php $meta = activity_meta($action); ?>
                        <option value="<?= e($action) ?>" <?= $filter === $action ? 'selected' : '' ?>>
                            <?= e($meta['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn ghost" type="submit"><?= icon('search') ?> Search</button>
            <?php if ($filtered): ?>
                <a class="btn ghost" href="<?= e(url('admin/activity.php')) ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$rows): ?>
        <div class="empty">
            <span class="empty-icon"><?= icon('activity') ?></span>
            <h3><?= $filtered ? 'No matching events' : 'No activity yet' ?></h3>
            <p class="muted">
                <?= $filtered
                    ? 'Try a different search term or event type.'
                    : 'Sign-ins, server changes, team updates and maintenance actions will appear here.' ?>
            </p>
            <?php if ($filtered): ?>
                <a class="btn ghost" href="<?= e(url('admin/activity.php')) ?>"><?= icon('x') ?> Clear filters</a>
            <?php else: ?>
                <a class="btn primary" href="<?= e(url('admin/servers.php?new=1')) ?>"><?= icon('plus') ?> Add a server</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table activity-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Detail</th>
                        <th>Actor</th>
                        <th class="hide-sm">IP address</th>
                        <th class="right">When</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $meta = activity_meta((string) $row['action']); ?>
                        <tr>
                            <td>
                                <span class="badge <?= e($meta['tone']) ?>">
                                    <span class="activity-dot <?= e($meta['tone']) ?>"></span>
                                    <?= e($meta['label']) ?>
                                </span>
                            </td>
                            <td><span class="muted"><?= e($row['detail'] !== '' ? $row['detail'] : '—') ?></span></td>
                            <td>
                                <span class="actor-chip <?= ($row['actor'] ?? '') === 'system' || ($row['actor'] ?? '') === '' ? 'is-system' : '' ?>">
                                    <?= icon('user', 'icon') ?>
                                    <?= e(($row['actor'] ?? '') !== '' ? $row['actor'] : 'system') ?>
                                </span>
                            </td>
                            <td class="hide-sm"><span class="mono small"><?= e($row['ip'] !== '' ? $row['ip'] : '—') ?></span></td>
                            <td class="right">
                                <time datetime="<?= e($row['at']) ?>" title="<?= e($row['at']) ?>"><?= e(time_ago($row['at'])) ?></time>
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

                <span class="pagination-info">Page <strong><?= $page ?></strong> of <?= $pages ?></span>

                <?php if ($page < $pages): ?>
                    <a class="btn ghost xs" href="<?= e($pageUrl($page + 1)) ?>">Next <?= icon('chevron-right') ?></a>
                <?php else: ?>
                    <span class="btn ghost xs disabled">Next <?= icon('chevron-right') ?></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
