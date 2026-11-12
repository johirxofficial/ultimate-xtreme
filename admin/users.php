<?php
require_once __DIR__ . '/../config.php';
require_admin();

$currentId = auth_id();
$action    = pget('action');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(pget('csrf'))) {
        flash('error', 'Security token expired. Please try again.');
        redirect('admin/users.php');
    }

    if ($action === 'save') {
        $id       = (int) pget('id');
        $username = trim(pget('username'));
        $role     = pget('role') === 'admin' ? 'admin' : 'manager';
        $status   = isset($_POST['status']) ? 1 : 0;
        $password = pget('password');
        $confirm  = pget('confirm');
        $existing = $id > 0 ? user_find($id) : null;
        $errors   = [];

        if ($id > 0 && !$existing) {
            $errors[] = 'That account no longer exists.';
        }

        if (!username_is_valid($username)) {
            $errors[] = 'Username must be 3–32 characters using letters, numbers, dots, dashes or underscores.';
        }

        $duplicate = user_by_name($username);
        if ($duplicate && (int) $duplicate['id'] !== $id) {
            $errors[] = 'That username is already in use.';
        }

        if ($id === $currentId) {
            $role   = (string) ($existing['role'] ?? 'admin');
            $status = 1;
        }

        if ($id > 0 && $existing && (int) $existing['status'] === 1 && $existing['role'] === 'admin'
            && ($role !== 'admin' || $status !== 1) && active_admin_count($id) < 1) {
            $errors[] = 'Keep at least one active administrator account.';
        }

        if ($id === 0 || $password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'Passwords must be at least 8 characters.';
            } elseif (!hash_equals($password, $confirm)) {
                $errors[] = 'The two passwords do not match.';
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('admin/users.php');
        }

        $savedId = user_save([
            'id'            => $id,
            'username'      => $username,
            'role'          => $role,
            'status'        => $status,
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '',
        ]);

        if ($savedId === $currentId) {
            $_SESSION['admin_user'] = $username;
            $_SESSION['admin_role'] = $role;
        }

        if ($id > 0) {
            activity_log('user.update', 'Updated account "' . $username . '" (' . $role . ', ' . ($status === 1 ? 'active' : 'disabled') . ')');
            flash('success', 'Account "' . $username . '" updated.');
        } else {
            activity_log('user.create', 'Added account "' . $username . '" as ' . $role);
            flash('success', 'Account "' . $username . '" added.');
        }

        redirect('admin/users.php');
    }

    if ($action === 'toggle') {
        $id   = (int) pget('id');
        $user = user_find($id);

        if (!$user) {
            flash('error', 'Account not found.');
        } elseif ($id === $currentId) {
            flash('error', 'You cannot disable your own account.');
        } elseif ((int) $user['status'] === 1 && $user['role'] === 'admin' && active_admin_count($id) < 1) {
            flash('error', 'Keep at least one active administrator account.');
        } else {
            $new = user_toggle($id);
            activity_log('user.toggle', $user['username'] . ' → ' . ($new === 1 ? 'active' : 'disabled'));
            flash('success', 'Account "' . $user['username'] . '" is now ' . ($new === 1 ? 'active' : 'disabled') . '.');
        }

        redirect('admin/users.php');
    }

    if ($action === 'delete') {
        $id   = (int) pget('id');
        $user = user_find($id);

        if (!$user) {
            flash('error', 'Account not found.');
        } elseif ($id === $currentId) {
            flash('error', 'You cannot delete your own account.');
        } elseif ($user['role'] === 'admin' && (int) $user['status'] === 1 && active_admin_count($id) < 1) {
            flash('error', 'Keep at least one active administrator account.');
        } elseif (user_delete($id)) {
            activity_log('user.delete', 'Removed account "' . $user['username'] . '"');
            flash('success', 'Account "' . $user['username'] . '" removed.');
        } else {
            flash('error', 'Could not remove that account.');
        }

        redirect('admin/users.php');
    }

    redirect('admin/users.php');
}

$users    = users_all();
$admins   = 0;
$managers = 0;
$active   = 0;

foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $admins++;
    } else {
        $managers++;
    }
    if ((int) $user['status'] === 1) {
        $active++;
    }
}

$pageTitle = 'Team';
$activeNav = 'users';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Access control</p>
        <h1>Team</h1>
        <p class="muted">Administrators and managers who can sign in to this console.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn primary" data-add-user><?= icon('plus') ?> Add member</button>
    </div>
</div>

<section class="stat-grid stat-grid-sm">
    <article class="stat-card accent-brand">
        <div class="stat-icon"><?= icon('users') ?></div>
        <div>
            <span class="stat-label">Total accounts</span>
            <strong><?= count($users) ?></strong>
            <small class="muted"><?= $active ?> active</small>
        </div>
    </article>
    <article class="stat-card accent-violet">
        <div class="stat-icon"><?= icon('shield') ?></div>
        <div>
            <span class="stat-label">Administrators</span>
            <strong><?= $admins ?></strong>
            <small class="muted">Full access</small>
        </div>
    </article>
    <article class="stat-card accent-cyan">
        <div class="stat-icon"><?= icon('tv') ?></div>
        <div>
            <span class="stat-label">Managers</span>
            <strong><?= $managers ?></strong>
            <small class="muted">Servers &amp; channels</small>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h2>Accounts</h2>
        <p class="muted small">Managers can operate providers and channels. Only administrators can manage the team and settings.</p>
    </div>

    <div class="table-wrap">
        <table class="table user-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="hide-sm">Last sign-in</th>
                    <th class="hide-sm">Created</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $isSelf    = (int) $user['id'] === $currentId;
                    $isOn      = (int) $user['status'] === 1;
                    $isAdmin   = $user['role'] === 'admin';
                    $editData  = [
                        'id'       => (int) $user['id'],
                        'username' => $user['username'],
                        'role'     => $isAdmin ? 'admin' : 'manager',
                        'status'   => $isOn ? 1 : 0,
                        'self'     => $isSelf,
                    ];
                    ?>
                    <tr data-user-row data-search="<?= e(strtolower($user['username'] . ' ' . $user['role'])) ?>">
                        <td>
                            <div class="user-cell">
                                <span class="avatar"><?= e(strtoupper(mb_substr($user['username'], 0, 1))) ?></span>
                                <div class="cell-stack">
                                    <span class="cell-title">
                                        <?= e($user['username']) ?>
                                        <?php if ($isSelf): ?><span class="you-badge">You</span><?php endif; ?>
                                    </span>
                                    <span class="cell-sub"><?= $isAdmin ? 'Full console access' : 'Providers, channels &amp; activity' ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge <?= $isAdmin ? 'is-admin' : 'is-manager' ?>">
                                <?= icon($isAdmin ? 'shield' : 'tv') ?>
                                <?= $isAdmin ? 'Administrator' : 'Manager' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <button type="submit"
                                        class="switch <?= $isOn ? 'on' : 'off' ?>"
                                        title="<?= $isSelf ? 'You cannot disable your own account' : ($isOn ? 'Disable account' : 'Enable account') ?>"
                                        aria-label="<?= $isOn ? 'Disable' : 'Enable' ?> <?= e($user['username']) ?>"
                                        <?= $isSelf ? 'disabled' : '' ?>>
                                    <span class="switch-dot"></span>
                                </button>
                            </form>
                            <span class="status-pill <?= $isOn ? 'on' : 'off' ?>">
                                <span class="status-dot"></span><?= $isOn ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="hide-sm">
                            <span class="muted small"><?= e($user['last_login'] !== '' ? time_ago($user['last_login']) : 'Never') ?></span>
                        </td>
                        <td class="hide-sm">
                            <span class="muted small"><?= e($user['created_at'] !== '' ? date('d M Y', strtotime((string) $user['created_at'])) : '—') ?></span>
                        </td>
                        <td class="right">
                            <div class="row-actions">
                                <button type="button" class="icon-btn js-edit-user"
                                        data-user="<?= e(json_encode($editData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
                                        title="Edit account" aria-label="Edit <?= e($user['username']) ?>">
                                    <?= icon('edit') ?>
                                </button>
                                <form method="post" class="inline"
                                      data-confirm="Remove &quot;<?= e($user['username']) ?>&quot; from the console? They will be signed out immediately."
                                      data-confirm-label="Remove account">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button type="submit" class="icon-btn danger"
                                            title="<?= $isSelf ? 'You cannot delete your own account' : 'Remove account' ?>"
                                            aria-label="Remove <?= e($user['username']) ?>"
                                            <?= $isSelf ? 'disabled' : '' ?>>
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
</section>

<dialog class="modal user-modal" id="userDialog">
    <form method="post" id="userForm" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="u-id" value="">

        <header class="modal-head">
            <div>
                <p class="eyebrow" id="userEyebrow">New team member</p>
                <h2 id="userTitle">Add account</h2>
            </div>
            <button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>

        <div class="modal-body form-stack">
            <label class="field">
                <span>Username <em>required</em></span>
                <input class="input" type="text" name="username" id="u-username"
                       pattern="[a-zA-Z0-9_.\-]{3,32}" maxlength="32" required spellcheck="false">
                <span class="field-hint">3–32 characters. Letters, numbers, dots, dashes and underscores.</span>
            </label>

            <div class="grid-2">
                <label class="field">
                    <span>Role</span>
                    <select class="input" name="role" id="u-role">
                        <option value="manager">Manager — servers, channels &amp; activity</option>
                        <option value="admin">Administrator — full access</option>
                    </select>
                </label>

                <div class="field">
                    <span>Status</span>
                    <label class="check"><input type="checkbox" name="status" id="u-status" checked><span>Active — can sign in</span></label>
                </div>
            </div>

            <div class="alert info slim-alert" id="selfUserNote" hidden>
                <?= icon('info') ?><span>You are editing your own account. Your role and active status are protected.</span>
            </div>

            <div class="grid-2">
                <label class="field">
                    <span>Password <em id="u-pass-req">required</em></span>
                    <div class="input-group">
                        <input class="input" type="password" name="password" id="u-password"
                               autocomplete="new-password" minlength="8" data-strength="u-password">
                        <button type="button" class="icon-btn in-group" data-reveal="u-password" aria-label="Show password"><?= icon('eye') ?></button>
                    </div>
                    <span class="field-hint" id="u-pass-hint">At least 8 characters.</span>
                    <span class="password-meter" data-meter-for="u-password" aria-hidden="true">
                        <span></span><span></span><span></span><span></span>
                    </span>
                </label>

                <label class="field">
                    <span>Confirm password</span>
                    <input class="input" type="password" name="confirm" id="u-confirm" autocomplete="new-password" minlength="8">
                </label>
            </div>
        </div>

        <footer class="modal-foot">
            <button type="button" class="btn ghost" data-close>Cancel</button>
            <button type="submit" class="btn primary"><?= icon('check') ?> Save account</button>
        </footer>
    </form>
</dialog>

<?php require __DIR__ . '/partials/footer.php'; ?>
