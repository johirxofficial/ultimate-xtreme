<?php
require_once __DIR__ . '/../config.php';
require_admin();

// -------------------------------------------------------------------------
// Export (must run before any output)
// -------------------------------------------------------------------------
if (qget('export') === '1') {
    $export = [
        'app'         => 'ultimatextreme',
        'version'     => APP_VERSION,
        'exported_at' => date('c'),
        'settings'    => [
            'site_title'   => (string) setting('site_title', APP_NAME),
            'default_mode' => (string) setting('default_mode', 'proxy'),
            'cache_ttl'    => (string) setting('cache_ttl', '600'),
        ],
        'servers' => array_map(static function (array $s): array {
            return [
                'name'       => $s['name'],
                'title'      => $s['title'],
                'host'       => $s['host'],
                'username'   => $s['username'],
                'password'   => $s['password'],
                'mode'       => $s['mode'],
                'status'     => (int) $s['status'],
                'playlist'   => (int) ($s['playlist'] ?? 1),
                'user_agent' => $s['user_agent'],
                'note'       => $s['note'],
            ];
        }, servers_all()),
    ];

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ultimatextreme-backup-' . date('Ymd-His') . '.json"');
    header('Cache-Control: no-store');
    echo $json;
    exit;
}

$action = pget('action');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(pget('csrf'))) {
        flash('error', 'Security token expired. Please try again.');
        redirect('admin/settings.php');
    }

    if ($action === 'general') {
        $siteTitle   = trim(pget('site_title'));
        $defaultMode = pget('default_mode') === 'rewrite' ? 'rewrite' : 'proxy';
        $cacheTtl    = (int) pget('cache_ttl', '600');

        $errors = [];

        if ($siteTitle === '') {
            $siteTitle = APP_NAME;
        }
        if ($cacheTtl < 60 || $cacheTtl > 86400) {
            $errors[] = 'Cache TTL must be between 60 and 86400 seconds.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            setting_set('site_title', mb_substr($siteTitle, 0, 60));
            setting_set('default_mode', $defaultMode);
            setting_set('cache_ttl', (string) $cacheTtl);

            activity_log('settings.update', 'General settings updated');
            flash('success', 'Settings saved.');
        }

        redirect('admin/settings.php#general');
    }

    if ($action === 'import') {
        $file = $_FILES['backup'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Choose a backup JSON file to import.');
            redirect('admin/settings.php#data');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            flash('error', 'Backup file is too large (max 5 MB).');
            redirect('admin/settings.php#data');
        }

        $raw  = (string) file_get_contents($file['tmp_name']);
        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['servers']) || !is_array($data['servers'])) {
            flash('error', 'That file is not a valid Ultimate Xtreme backup.');
            redirect('admin/settings.php#data');
        }

        $valid   = [];
        $seen    = [];
        $skipped = 0;

        foreach ($data['servers'] as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $host = rtrim(trim((string) ($row['host'] ?? '')), '/');

            if (!preg_match('/^[a-z0-9][a-z0-9_\-]{1,31}$/', $name) || !host_is_valid($host) || isset($seen[$name])) {
                $skipped++;
                continue;
            }

            $seen[$name] = true;
            $valid[] = [
                'name'       => $name,
                'title'      => trim((string) ($row['title'] ?? '')) ?: ucfirst($name),
                'host'       => $host,
                'username'   => trim((string) ($row['username'] ?? '')),
                'password'   => (string) ($row['password'] ?? ''),
                'mode'       => ($row['mode'] ?? 'proxy') === 'rewrite' ? 'rewrite' : 'proxy',
                'status'     => (int) ($row['status'] ?? 1) === 1 ? 1 : 0,
                'playlist'   => (int) ($row['playlist'] ?? 1) === 1 ? 1 : 0,
                'user_agent' => trim((string) ($row['user_agent'] ?? '')),
                'note'       => trim((string) ($row['note'] ?? '')),
            ];
        }

        if (!$valid) {
            flash('error', 'The backup contains no valid servers.');
            redirect('admin/settings.php#data');
        }

        $pdo = db();

        try {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM servers');

            foreach ($valid as $row) {
                server_save($row);
            }

            $imported = $data['settings'] ?? [];
            if (is_array($imported)) {
                if (!empty($imported['site_title']) && is_string($imported['site_title'])) {
                    $title = trim($imported['site_title']);
                    if ($title !== '') {
                        setting_set('site_title', mb_substr($title, 0, 60));
                    }
                }
                if (isset($imported['default_mode']) && in_array($imported['default_mode'], ['proxy', 'rewrite'], true)) {
                    setting_set('default_mode', $imported['default_mode']);
                }
                if (isset($imported['cache_ttl']) && is_numeric($imported['cache_ttl'])) {
                    $ttl = max(60, min(86400, (int) $imported['cache_ttl']));
                    setting_set('cache_ttl', (string) $ttl);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Import failed. Your previous data was restored.');
            redirect('admin/settings.php#data');
        }

        activity_log('data.import', count($valid) . ' servers imported' . ($skipped > 0 ? ', ' . $skipped . ' skipped' : ''));

        $message = count($valid) . ' server(s) imported.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' invalid entr' . ($skipped === 1 ? 'y was' : 'ies were') . ' skipped.';
        }
        flash('success', $message);
        redirect('admin/settings.php#data');
    }

    if ($action === 'cache') {
        $removed = channels_cache_clear();
        activity_log('cache.clear', $removed . ' cached libraries removed');
        flash('success', 'Cache cleared (' . $removed . ' file(s) removed).');
        redirect('admin/settings.php#data');
    }

    redirect('admin/settings.php');
}

$pageTitle = 'Settings';
$activeNav = 'settings';
$checks    = system_checks();

$siteTitle  = (string) setting('site_title', APP_NAME);
$globalMode = (string) setting('default_mode', 'proxy');
$cacheTtl   = (string) setting('cache_ttl', '600');

$accounts    = users_all();
$adminCount  = 0;
$managerCount = 0;
foreach ($accounts as $account) {
    if ($account['role'] === 'admin') {
        $adminCount++;
    } else {
        $managerCount++;
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Settings</h1>
        <p class="muted">Branding, delivery defaults, backups and system health.</p>
    </div>
    <div class="page-actions">
        <a class="btn ghost" href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?> Manage team</a>
    </div>
</div>

<div class="settings-grid">
    <section class="card pad" id="general">
        <div class="section-head">
            <h2>General</h2>
            <p class="muted small">Defaults applied across the console.</p>
        </div>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="general">

            <label class="field">
                <span>Panel title</span>
                <input class="input" type="text" name="site_title" value="<?= e($siteTitle) ?>" maxlength="60">
                <span class="field-hint">Shown in the sidebar, browser title and sign-in screen.</span>
            </label>

            <div class="grid-2">
                <label class="field">
                    <span>Default delivery for new servers</span>
                    <select class="input" name="default_mode">
                        <option value="proxy" <?= $globalMode !== 'rewrite' ? 'selected' : '' ?>>Relay — hide the provider address</option>
                        <option value="rewrite" <?= $globalMode === 'rewrite' ? 'selected' : '' ?>>Direct — link players to the provider</option>
                    </select>
                    <span class="field-hint">Relay keeps delivery on this server. Direct sends players straight to the provider.</span>
                </label>

                <label class="field">
                    <span>Channel cache TTL <small>seconds</small></span>
                    <input class="input" type="number" name="cache_ttl" value="<?= e($cacheTtl) ?>" min="60" max="86400" step="30" required>
                    <span class="field-hint">How long channel libraries stay cached (60–86400).</span>
                </label>
            </div>

            <div class="form-actions">
                <button class="btn primary" type="submit"><?= icon('check') ?> Save settings</button>
            </div>
        </form>
    </section>

    <section class="card pad" id="team">
        <div class="section-head">
            <h2>Team &amp; access</h2>
            <p class="muted small">Administrator and manager accounts.</p>
        </div>

        <div class="mini-stats">
            <div class="mini-stat">
                <strong><?= count($accounts) ?></strong>
                <span>Total accounts</span>
            </div>
            <div class="mini-stat">
                <strong><?= $adminCount ?></strong>
                <span>Administrators</span>
            </div>
            <div class="mini-stat">
                <strong><?= $managerCount ?></strong>
                <span>Managers</span>
            </div>
        </div>

        <p class="muted small">
            Administrators manage accounts and settings. Managers can operate servers, channels and playlists.
        </p>

        <div class="form-actions">
            <a class="btn ghost" href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?> Open team</a>
            <a class="btn ghost" href="<?= e(url('admin/account.php')) ?>"><?= icon('key') ?> My password</a>
        </div>
    </section>

    <section class="card pad span-2" id="data">
        <div class="section-head">
            <h2>Data &amp; maintenance</h2>
            <p class="muted small">Backups include provider credentials. Keep exported files private. Team accounts are not included.</p>
        </div>

        <div class="data-grid">
            <div class="data-card">
                <span class="data-icon"><?= icon('download') ?></span>
                <div>
                    <strong>Export backup</strong>
                    <p class="muted small">Servers and general settings as a JSON file.</p>
                </div>
                <a class="btn ghost" href="<?= e(url('admin/settings.php?export=1')) ?>">Download</a>
            </div>

            <form class="data-card" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="import">
                <span class="data-icon"><?= icon('upload') ?></span>
                <div>
                    <strong>Import backup</strong>
                    <p class="muted small">Replaces all servers and general settings.</p>
                    <input class="input file-input" type="file" name="backup" accept="application/json,.json" required>
                </div>
                <button class="btn ghost" type="submit" data-confirm="Importing replaces every server and general setting. Continue?" data-confirm-label="Import backup">Import</button>
            </form>

            <form class="data-card" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="cache">
                <span class="data-icon"><?= icon('refresh') ?></span>
                <div>
                    <strong>Clear channel cache</strong>
                    <p class="muted small">Forces the next channel page to re-fetch from providers.</p>
                </div>
                <button class="btn ghost" type="submit">Clear cache</button>
            </form>
        </div>
    </section>

    <section class="card pad span-2">
        <div class="section-head">
            <h2>System</h2>
            <p class="muted small">Environment details for troubleshooting.</p>
        </div>

        <div class="system-grid">
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
                <span class="mono muted small">SQLite · <?= count(servers_all()) ?> servers · <?= count($accounts) ?> users</span>
            </div>
            <div class="system-item">
                <span class="check-icon ok"><?= icon('shield') ?></span>
                <span>Version</span>
                <span class="mono muted small">v<?= e(APP_VERSION) ?></span>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
