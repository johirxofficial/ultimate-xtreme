<?php
/**
 * SQLite storage layer: servers, users, settings, activity + migrations.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }

    $file = DATA_DIR . DIRECTORY_SEPARATOR . 'ultimatextreme.sqlite';

    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    $pdo->exec("CREATE TABLE IF NOT EXISTS servers (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT    NOT NULL UNIQUE,
        title      TEXT    NOT NULL DEFAULT '',
        host       TEXT    NOT NULL,
        username   TEXT    NOT NULL DEFAULT '',
        password   TEXT    NOT NULL DEFAULT '',
        mode       TEXT    NOT NULL DEFAULT 'proxy',
        status     INTEGER NOT NULL DEFAULT 1,
        playlist   INTEGER NOT NULL DEFAULT 1,
        user_agent TEXT    NOT NULL DEFAULT '',
        note       TEXT    NOT NULL DEFAULT '',
        created_at TEXT    NOT NULL DEFAULT '',
        updated_at TEXT    NOT NULL DEFAULT ''
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
        password_hash TEXT    NOT NULL,
        role          TEXT    NOT NULL DEFAULT 'manager',
        status        INTEGER NOT NULL DEFAULT 1,
        created_at    TEXT    NOT NULL DEFAULT '',
        updated_at    TEXT    NOT NULL DEFAULT '',
        last_login    TEXT    NOT NULL DEFAULT ''
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key   TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        at          TEXT NOT NULL DEFAULT '',
        action      TEXT NOT NULL DEFAULT '',
        detail      TEXT NOT NULL DEFAULT '',
        actor       TEXT NOT NULL DEFAULT '',
        ip          TEXT NOT NULL DEFAULT ''
    )");

    // --- migrations -------------------------------------------------------
    $serverColumns = array_column($pdo->query('PRAGMA table_info(servers)')->fetchAll(), 'name');
    if (!in_array('playlist', $serverColumns, true)) {
        $pdo->exec('ALTER TABLE servers ADD COLUMN playlist INTEGER NOT NULL DEFAULT 1');
    }

    // Drop the retired per-server fallback column from older databases.
    // SQLite < 3.35 has no DROP COLUMN, so rebuild the table in a transaction.
    if (in_array('fallback_url', $serverColumns, true)) {
        try {
            $pdo->beginTransaction();
            $pdo->exec("CREATE TABLE servers_rebuild (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL UNIQUE,
                title      TEXT    NOT NULL DEFAULT '',
                host       TEXT    NOT NULL,
                username   TEXT    NOT NULL DEFAULT '',
                password   TEXT    NOT NULL DEFAULT '',
                mode       TEXT    NOT NULL DEFAULT 'proxy',
                status     INTEGER NOT NULL DEFAULT 1,
                playlist   INTEGER NOT NULL DEFAULT 1,
                user_agent TEXT    NOT NULL DEFAULT '',
                note       TEXT    NOT NULL DEFAULT '',
                created_at TEXT    NOT NULL DEFAULT '',
                updated_at TEXT    NOT NULL DEFAULT ''
            )");
            $pdo->exec("INSERT INTO servers_rebuild
                (id, name, title, host, username, password, mode, status, playlist, user_agent, note, created_at, updated_at)
                SELECT id, name, title, host, username, password, mode, status, playlist, user_agent, note, created_at, updated_at
                FROM servers");
            $pdo->exec('DROP TABLE servers');
            $pdo->exec('ALTER TABLE servers_rebuild RENAME TO servers');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Ultimate Xtreme: could not remove retired servers.fallback_url: ' . $e->getMessage());
        }
    }

    $activityColumns = array_column($pdo->query('PRAGMA table_info(activity)')->fetchAll(), 'name');
    if (!in_array('actor', $activityColumns, true)) {
        $pdo->exec("ALTER TABLE activity ADD COLUMN actor TEXT NOT NULL DEFAULT ''");
    }

    $defaults = [
        'app_secret'   => bin2hex(random_bytes(32)),
        'site_title'   => 'Ultimate Xtreme',
        'default_mode' => 'proxy',
        'cache_ttl'    => '600',
    ];

    foreach ($defaults as $key => $value) {
        if (setting($key, null) === null) {
            setting_set($key, $value);
        }
    }

    migrate_legacy_accounts($pdo);

    return $pdo;
}

/**
 * Import the old single-admin settings into the users table (v3.0 upgrade).
 * Legacy fallback settings are retired and removed.
 */
function migrate_legacy_accounts(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    if ($count === 0) {
        $hash = (string) (setting('admin_hash', '') ?? '');
        if ($hash !== '') {
            $username = trim((string) (setting('admin_user', 'admin') ?? 'admin'));
            if ($username === '') {
                $username = 'admin';
            }

            $now  = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash, role, status, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?)'
            );
            $stmt->execute([$username, $hash, 'admin', $now, $now]);
        }
    }

    // Retired keys from the old fallback/theme era.
    $pdo->exec("DELETE FROM settings WHERE key IN ('admin_hash', 'admin_user', 'fallback_url')");
}

// =========================================================================
// Settings
// =========================================================================

function setting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $default : (string) $value;
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    $stmt->execute([$key, $value]);
}

// =========================================================================
// Servers
// =========================================================================

/** @return array<int, array<string, mixed>> */
function servers_all(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM servers';
    if ($onlyActive) {
        $sql .= ' WHERE status = 1';
    }
    $sql .= ' ORDER BY title COLLATE NOCASE, name COLLATE NOCASE';

    return db()->query($sql)->fetchAll();
}

function server_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM servers WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function server_by_name(string $name): ?array
{
    $stmt = db()->prepare('SELECT * FROM servers WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function server_save(array $data): int
{
    $now = date('Y-m-d H:i:s');
    $id  = (int) ($data['id'] ?? 0);

    $values = [
        (string) ($data['name'] ?? ''),
        (string) ($data['title'] ?? ''),
        (string) ($data['host'] ?? ''),
        (string) ($data['username'] ?? ''),
        (string) ($data['password'] ?? ''),
        ($data['mode'] ?? 'proxy') === 'rewrite' ? 'rewrite' : 'proxy',
        (int) ($data['status'] ?? 1) === 1 ? 1 : 0,
        (int) ($data['playlist'] ?? 1) === 1 ? 1 : 0,
        (string) ($data['user_agent'] ?? ''),
        (string) ($data['note'] ?? ''),
    ];

    if ($id > 0) {
        $values[] = $now;
        $values[] = $id;

        $stmt = db()->prepare(
            'UPDATE servers
                SET name = ?, title = ?, host = ?, username = ?, password = ?, mode = ?,
                    status = ?, playlist = ?, user_agent = ?, note = ?, updated_at = ?
              WHERE id = ?'
        );
        $stmt->execute($values);

        return $id;
    }

    $values[] = $now;
    $values[] = $now;

    $stmt = db()->prepare(
        'INSERT INTO servers
            (name, title, host, username, password, mode, status, playlist, user_agent, note, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute($values);

    return (int) db()->lastInsertId();
}

function server_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM servers WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->rowCount() > 0;
}

function server_toggle(int $id): ?int
{
    $server = server_find($id);
    if (!$server) {
        return null;
    }

    $new = ((int) $server['status'] === 1) ? 0 : 1;
    $stmt = db()->prepare('UPDATE servers SET status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$new, date('Y-m-d H:i:s'), $id]);

    return $new;
}

// =========================================================================
// Users (admin / manager)
// =========================================================================

function users_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

/** @return array<int, array<string, mixed>> */
function users_all(): array
{
    return db()->query(
        "SELECT * FROM users
          ORDER BY CASE role WHEN 'admin' THEN 0 ELSE 1 END, username COLLATE NOCASE"
    )->fetchAll();
}

function user_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function user_by_name(string $username): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function user_save(array $data): int
{
    $id       = (int) ($data['id'] ?? 0);
    $username = (string) ($data['username'] ?? '');
    $role     = ($data['role'] ?? 'manager') === 'admin' ? 'admin' : 'manager';
    $status   = (int) ($data['status'] ?? 1) === 1 ? 1 : 0;
    $hash     = (string) ($data['password_hash'] ?? '');
    $now      = date('Y-m-d H:i:s');

    if ($id > 0) {
        if ($hash !== '') {
            $stmt = db()->prepare(
                'UPDATE users SET username = ?, password_hash = ?, role = ?, status = ?, updated_at = ? WHERE id = ?'
            );
            $stmt->execute([$username, $hash, $role, $status, $now, $id]);
        } else {
            $stmt = db()->prepare(
                'UPDATE users SET username = ?, role = ?, status = ?, updated_at = ? WHERE id = ?'
            );
            $stmt->execute([$username, $role, $status, $now, $id]);
        }

        return $id;
    }

    $stmt = db()->prepare(
        'INSERT INTO users (username, password_hash, role, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$username, $hash, $role, $status, $now, $now]);

    return (int) db()->lastInsertId();
}

function user_set_password(int $id, string $hash): void
{
    $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$hash, date('Y-m-d H:i:s'), $id]);
}

function user_touch_login(int $id): void
{
    $stmt = db()->prepare('UPDATE users SET last_login = ? WHERE id = ?');
    $stmt->execute([date('Y-m-d H:i:s'), $id]);
}

function user_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->rowCount() > 0;
}

function user_toggle(int $id): ?int
{
    $user = user_find($id);
    if (!$user) {
        return null;
    }

    $new = ((int) $user['status'] === 1) ? 0 : 1;
    $stmt = db()->prepare('UPDATE users SET status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$new, date('Y-m-d H:i:s'), $id]);

    return $new;
}

function active_admin_count(?int $excludeId = null): int
{
    $sql    = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 1";
    $params = [];

    if ($excludeId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

// =========================================================================
// Activity log
// =========================================================================

function activity_log(string $action, string $detail = '', ?string $actor = null): void
{
    try {
        if ($actor === null) {
            if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_user'])) {
                $actor = (string) $_SESSION['admin_user'];
            } else {
                $actor = 'system';
            }
        }

        $stmt = db()->prepare('INSERT INTO activity (at, action, detail, actor, ip) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            date('Y-m-d H:i:s'),
            substr($action, 0, 60),
            substr($detail, 0, 300),
            substr($actor, 0, 64),
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable $e) {
        // Logging must never break a request.
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function activity_recent(int $limit = 10, int $offset = 0, string $q = '', string $action = ''): array
{
    $limit  = max(1, min(200, $limit));
    $offset = max(0, $offset);

    [$where, $params] = activity_where($q, $action);

    $sql = 'SELECT * FROM activity' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?';
    $stmt = db()->prepare($sql);

    $i = 1;
    foreach ($params as $param) {
        $stmt->bindValue($i++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function activity_count(string $q = '', string $action = ''): int
{
    [$where, $params] = activity_where($q, $action);

    $stmt = db()->prepare('SELECT COUNT(*) FROM activity' . $where);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/** @return array{0: string, 1: array<int, string>} */
function activity_where(string $q, string $action): array
{
    $clauses = [];
    $params  = [];

    if ($q !== '') {
        $clauses[] = '(detail LIKE ? OR actor LIKE ? OR action LIKE ? OR ip LIKE ?)';
        $like      = '%' . $q . '%';
        $params[]  = $like;
        $params[]  = $like;
        $params[]  = $like;
        $params[]  = $like;
    }

    if ($action !== '') {
        $clauses[] = 'action = ?';
        $params[]  = $action;
    }

    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

/** @return array<int, string> */
function activity_actions(): array
{
    $rows = db()->query('SELECT DISTINCT action FROM activity ORDER BY action COLLATE NOCASE')->fetchAll();

    return array_map(static fn(array $row): string => (string) $row['action'], $rows);
}

function activity_clear(): void
{
    db()->exec('DELETE FROM activity');
}
