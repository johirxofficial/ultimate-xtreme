<?php
require_once __DIR__ . '/../config.php';
require_auth();

$action = pget('action');

// -------------------------------------------------------------------------
// AJAX: test upstream credentials
// -------------------------------------------------------------------------
if (qget('ajax') === 'test') {
    $server = server_find((int) qget('id'));
    if (!$server) {
        json_out(['ok' => false, 'message' => 'Server not found.']);
    }

    json_out(test_connection($server));
}

// -------------------------------------------------------------------------
// POST actions
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(pget('csrf'))) {
        flash('error', 'Security token expired. Please try again.');
        redirect('admin/servers.php');
    }

    if ($action === 'save') {
        $id       = (int) pget('id');
        $name     = strtolower(trim(pget('name')));
        $title    = trim(pget('title'));
        $host     = rtrim(trim(pget('host')), '/');
        $username = trim(pget('username'));
        $password = pget('password');
        $mode     = pget('mode') === 'rewrite' ? 'rewrite' : 'proxy';
        $status   = isset($_POST['status']) ? 1 : 0;
        $playlist = isset($_POST['playlist']) ? 1 : 0;
        $ua       = trim(pget('user_agent'));
        $note     = trim(pget('note'));

        $errors  = [];
        $current = $id > 0 ? server_find($id) : null;

        if ($id > 0 && !$current) {
            $errors[] = 'That server no longer exists.';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_\-]{1,31}$/', $name)) {
            $errors[] = 'Slug must be 2-32 characters (a-z, 0-9, dash, underscore).';
        }
        if (!host_is_valid($host)) {
            $errors[] = 'Host must be a valid http:// or https:// URL.';
        }
        if (mb_strlen($note) > 500) {
            $errors[] = 'Note is too long (max 500 characters).';
        }

        $duplicate = server_by_name($name);
        if ($duplicate && (int) $duplicate['id'] !== $id) {
            $errors[] = 'The slug "' . $name . '" is already in use.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('admin/servers.php');
        }

        if ($title === '') {
            $title = ucfirst($name);
        }

        server_save([
            'id'           => $id,
            'name'         => $name,
            'title'        => $title,
            'host'         => $host,
            'username'     => $username,
            'password'     => $password,
            'mode'         => $mode,
            'status'     => $status,
            'playlist'   => $playlist,
            'user_agent' => $ua,
            'note'       => $note,
        ]);

        activity_log(
            $id > 0 ? 'server.update' : 'server.create',
            $title . ' (' . $name . ')'
        );

        flash('success', $id > 0 ? 'Server "' . $title . '" updated.' : 'Server "' . $title . '" added.');
        redirect('admin/servers.php');
    }

    if ($action === 'toggle') {
        $server = server_find((int) pget('id'));
        $new    = server_toggle((int) pget('id'));

        if ($new === null) {
            flash('error', 'Server not found.');
        } else {
            activity_log('server.toggle', ($server['title'] ?: $server['name']) . ' → ' . ($new === 1 ? 'ON' : 'OFF'));
            flash('success', 'Server turned ' . ($new === 1 ? 'ON' : 'OFF') . '.');
        }

        redirect('admin/servers.php');
    }

    if ($action === 'delete') {
        $server = server_find((int) pget('id'));

        if ($server && server_delete((int) $server['id'])) {
            activity_log('server.delete', $server['title'] . ' (' . $server['name'] . ')');
            flash('success', 'Server "' . ($server['title'] ?: $server['name']) . '" deleted.');
        } else {
            flash('error', 'Could not delete that server.');
        }

        redirect('admin/servers.php');
    }

    if ($action === 'duplicate') {
        $server = server_find((int) pget('id'));

        if (!$server) {
            flash('error', 'Server not found.');
            redirect('admin/servers.php');
        }

        $base = $server['name'] . '-copy';
        $slug = $base;
        $i    = 2;
        while (server_by_name($slug)) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 99) {
                break;
            }
        }

        if (server_by_name($slug)) {
            flash('error', 'Could not create a unique slug for the copy.');
            redirect('admin/servers.php');
        }

        $title = trim((string) $server['title']) !== '' ? $server['title'] : ucfirst($server['name']);
        $newId = server_save([
            'name'       => $slug,
            'title'      => $title . ' (copy)',
            'host'       => $server['host'],
            'username'   => $server['username'],
            'password'   => $server['password'],
            'mode'       => $server['mode'],
            'status'     => 0,
            'playlist'   => (int) ($server['playlist'] ?? 1),
            'user_agent' => $server['user_agent'],
            'note'       => $server['note'],
        ]);

        activity_log('server.create', 'Duplicated ' . $server['name'] . ' as ' . $slug . ' (#' . $newId . ')');
        flash('success', 'Server duplicated as "' . $slug . '". It starts paused.');
        redirect('admin/servers.php');
    }

    redirect('admin/servers.php');
}

$servers   = servers_all();
$proxy     = 0;
$rewrite   = 0;

foreach ($servers as $srv) {
    if (($srv['mode'] ?? 'proxy') === 'rewrite') {
        $rewrite++;
    } else {
        $proxy++;
    }
}

$pageTitle = 'Servers';
$activeNav = 'servers';
$autoOpen  = qget('new') === '1';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Providers</p>
        <h1>Servers</h1>
        <p class="muted"><?= count($servers) ?> connected · <?= $proxy ?> relay · <?= $rewrite ?> direct</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn primary" data-add-server><?= icon('plus') ?> Add server</button>
    </div>
</div>

<section class="card">
    <div class="toolbar">
        <div class="toolbar-search">
            <?= icon('search') ?>
            <input type="search" id="serverSearch" placeholder="Search by name, slug or host…" autocomplete="off" spellcheck="false">
        </div>

        <div class="toolbar-filters">
            <label class="select-wrap">
                <?= icon('filter') ?>
                <select id="statusFilter" aria-label="Filter by status">
                    <option value="">All status</option>
                    <option value="1">Online</option>
                    <option value="0">Offline</option>
                </select>
            </label>

            <label class="select-wrap">
                <select id="modeFilter" aria-label="Filter by mode">
                    <option value="">All modes</option>
                    <option value="proxy">Relay</option>
                    <option value="rewrite">Direct</option>
                </select>
            </label>
        </div>
    </div>

    <?php if (!$servers): ?>
        <div class="empty">
            <span class="empty-icon"><?= icon('server') ?></span>
            <h3>No servers yet</h3>
            <p class="muted">Add an Xtreme Codes provider to start building your restream stack.</p>
            <button type="button" class="btn primary" data-add-server><?= icon('plus') ?> Add your first server</button>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table server-table">
                <thead>
                    <tr>
                        <th>Server</th>
                        <th>Host</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $srv): ?>
                        <?php
                        $isOn       = (int) $srv['status'] === 1;
                        $isRewrite  = ($srv['mode'] ?? 'proxy') === 'rewrite';
                        $m3uOn      = (int) ($srv['playlist'] ?? 1) === 1;
                        $display    = $srv['title'] !== '' ? $srv['title'] : $srv['name'];
                        $editData   = [
                            'id'           => (int) $srv['id'],
                            'name'         => $srv['name'],
                            'title'        => $srv['title'],
                            'host'         => $srv['host'],
                            'username'     => $srv['username'],
                            'password'     => $srv['password'],
                            'mode'         => $isRewrite ? 'rewrite' : 'proxy',
                            'status'       => $isOn ? 1 : 0,
                            'playlist'     => $m3uOn ? 1 : 0,
                            'user_agent'   => $srv['user_agent'],
                            'note'         => $srv['note'],
                        ];
                        ?>
                        <tr data-row
                            data-status="<?= $isOn ? '1' : '0' ?>"
                            data-mode="<?= $isRewrite ? 'rewrite' : 'proxy' ?>"
                            data-search="<?= e(strtolower($display . ' ' . $srv['name'] . ' ' . $srv['host'] . ' ' . $srv['username'])) ?>">
                            <td>
                                <div class="cell-stack">
                                    <span class="cell-title"><?= e($display) ?></span>
                                    <span class="cell-sub mono"><?= e($srv['name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <span class="mono ellipsis" title="<?= e($srv['host']) ?>"><?= e($srv['host']) ?></span>
                                    <?php if ($srv['username'] !== ''): ?>
                                        <span class="cell-sub mono"><?= e($srv['username']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="pill <?= $isRewrite ? 'rewrite' : 'proxy' ?>">
                                    <?= e(delivery_mode_label($srv['mode'] ?? 'proxy')) ?>
                                </span>
                                <span class="cell-sub"><?= $m3uOn ? 'M3U on' : 'M3U off' ?></span>
                            </td>
                            <td>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $srv['id'] ?>">
                                    <button type="submit"
                                            class="switch <?= $isOn ? 'on' : 'off' ?>"
                                            title="<?= $isOn ? 'Disable server' : 'Enable server' ?>"
                                            aria-label="<?= $isOn ? 'Disable' : 'Enable' ?> <?= e($srv['name']) ?>">
                                        <span class="switch-dot"></span>
                                    </button>
                                </form>
                                <span class="status-pill <?= $isOn ? 'on' : 'off' ?>">
                                    <span class="status-dot"></span><?= $isOn ? 'Online' : 'Offline' ?>
                                </span>
                            </td>
                            <td class="right">
                                <div class="row-actions">
                                    <a class="icon-btn" href="<?= e(url('admin/channels.php?server=' . rawurlencode($srv['name']))) ?>"
                                       title="Browse channels" aria-label="Browse channels">
                                        <?= icon('tv') ?>
                                    </a>
                                    <button type="button" class="icon-btn" data-copy="<?= e(playlist_download_url($srv['name'])) ?>"
                                            title="Copy M3U playlist URL" aria-label="Copy M3U playlist URL">
                                        <?= icon('link') ?>
                                    </button>
                                    <button type="button" class="icon-btn js-test" data-id="<?= (int) $srv['id'] ?>"
                                            title="Test connection" aria-label="Test connection">
                                        <?= icon('bolt') ?>
                                    </button>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="duplicate">
                                        <input type="hidden" name="id" value="<?= (int) $srv['id'] ?>">
                                        <button type="submit" class="icon-btn" title="Duplicate server"
                                                aria-label="Duplicate <?= e($srv['name']) ?>">
                                            <?= icon('copy') ?>
                                        </button>
                                    </form>
                                    <button type="button" class="icon-btn js-edit"
                                            data-server="<?= e(json_encode($editData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
                                            title="Edit server" aria-label="Edit server">
                                        <?= icon('edit') ?>
                                    </button>
                                    <form method="post" class="inline"
                                          data-confirm="Delete &quot;<?= e($display) ?>&quot; and stop all its streams? This cannot be undone."
                                          data-confirm-label="Delete server">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $srv['id'] ?>">
                                        <button type="submit" class="icon-btn danger" title="Delete server" aria-label="Delete server">
                                            <?= icon('trash') ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-empty" id="filterEmpty" hidden>
            <span class="empty-icon"><?= icon('search') ?></span>
            <p class="muted">No servers match your filters.</p>
        </div>
    <?php endif; ?>
</section>

<dialog class="modal server-modal" id="serverDialog" data-default-mode="<?= e(setting('default_mode', 'proxy')) ?>">
    <form method="post" id="serverForm" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="f-id" value="">

        <header class="modal-head">
            <div>
                <p class="eyebrow" id="dialogEyebrow">New provider</p>
                <h2 id="dialogTitle">Add server</h2>
            </div>
            <button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>

        <div class="modal-body form-stack">
            <div class="grid-2">
                <label class="field">
                    <span>Display name</span>
                    <input class="input" type="text" name="title" id="f-title" placeholder="My provider" maxlength="60">
                </label>

                <label class="field">
                    <span>Slug <em>required</em></span>
                    <input class="input" type="text" name="name" id="f-name" placeholder="myprovider"
                           pattern="[a-zA-Z0-9][a-zA-Z0-9_\-]{1,31}" maxlength="32" required spellcheck="false">
                    <small>Used in URLs: /live/<b>slug</b>/channel.m3u8</small>
                </label>
            </div>

            <label class="field">
                <span>Host URL <em>required</em></span>
                <input class="input" type="url" name="host" id="f-host" placeholder="http://panel.example.com:8080" required spellcheck="false">
            </label>

            <div class="grid-2">
                <label class="field">
                    <span>Username</span>
                    <input class="input" type="text" name="username" id="f-username" spellcheck="false" autocomplete="off">
                </label>

                <label class="field">
                    <span>Password</span>
                    <div class="input-group">
                        <input class="input" type="password" name="password" id="f-password" autocomplete="new-password">
                        <button type="button" class="icon-btn in-group" data-reveal="f-password" aria-label="Show password"><?= icon('eye') ?></button>
                    </div>
                </label>
            </div>

            <div class="grid-2">
                <label class="field">
                    <span>Delivery mode</span>
                    <select class="input" name="mode" id="f-mode">
                        <option value="proxy">Relay — hide the provider address</option>
                        <option value="rewrite">Direct — link players to the provider</option>
                    </select>
                </label>

                <div class="field">
                    <span>Options</span>
                    <div class="check-row">
                        <label class="check"><input type="checkbox" name="status" id="f-status" checked><span>Active</span></label>
                        <label class="check"><input type="checkbox" name="playlist" id="f-playlist" checked><span>Publish M3U</span></label>
                    </div>
                </div>
            </div>

            <label class="field">
                <span>User-Agent <small>optional</small></span>
                <input class="input" type="text" name="user_agent" id="f-ua" placeholder="Default: Chrome desktop" spellcheck="false">
            </label>

            <label class="field">
                <span>Note <small>optional</small></span>
                <textarea class="input" name="note" id="f-note" rows="2" maxlength="500"></textarea>
            </label>
        </div>

        <footer class="modal-foot">
            <button type="button" class="btn ghost" data-close>Cancel</button>
            <button type="submit" class="btn primary"><?= icon('check') ?> Save server</button>
        </footer>
    </form>
</dialog>

<?php if ($autoOpen): ?>
    <script>window.UX_OPEN_NEW = true;</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
