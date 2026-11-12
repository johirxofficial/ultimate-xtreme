<?php
require_once __DIR__ . '/../config.php';
require_auth();

$user = user_find(auth_id());

if (!$user) {
    auth_logout();
    redirect('admin/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(pget('csrf'))) {
        flash('error', 'Security token expired. Please try again.');
        redirect('admin/account.php');
    }

    if (pget('action') === 'password') {
        $current = pget('current_password');
        $new     = pget('new_password');
        $confirm = pget('confirm_password');

        if (!password_verify($current, (string) $user['password_hash'])) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } elseif (!hash_equals($new, $confirm)) {
            flash('error', 'The new passwords do not match.');
        } elseif (password_verify($new, (string) $user['password_hash'])) {
            flash('error', 'Choose a password you have not used before.');
        } else {
            user_set_password((int) $user['id'], password_hash($new, PASSWORD_DEFAULT));
            session_regenerate_id(true);
            activity_log('account.password', 'Password changed for ' . $user['username']);
            flash('success', 'Your password has been updated.');
        }

        redirect('admin/account.php#password');
    }

    redirect('admin/account.php');
}

$isAdmin   = auth_is_admin();
$roleLabel = $isAdmin ? 'Administrator' : 'Manager';
$initial   = strtoupper(mb_substr((string) $user['username'], 0, 1));
$loginAt   = (int) ($_SESSION['login_at'] ?? 0);

$pageTitle = 'My account';
$activeNav = 'account';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Profile &amp; security</p>
        <h1>My account</h1>
        <p class="muted">Your console identity, access level and password.</p>
    </div>
    <div class="page-actions">
        <?php if ($isAdmin): ?>
            <a class="btn ghost" href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?> Manage team</a>
        <?php endif; ?>
        <a class="btn ghost" href="<?= e(url('logout.php')) ?>"><?= icon('logout') ?> Sign out</a>
    </div>
</div>

<div class="account-grid">
    <section class="card pad account-profile">
        <div class="profile-head">
            <span class="avatar xl"><?= e($initial) ?></span>
            <div>
                <h2><?= e($user['username']) ?></h2>
                <span class="role-badge <?= $isAdmin ? 'is-admin' : 'is-manager' ?>">
                    <?= icon($isAdmin ? 'shield' : 'tv') ?>
                    <?= e($roleLabel) ?>
                </span>
            </div>
        </div>

        <dl class="profile-list">
            <div>
                <dt>Username</dt>
                <dd class="mono"><?= e($user['username']) ?></dd>
            </div>
            <div>
                <dt>Access</dt>
                <dd><?= $isAdmin ? 'Full console, team and settings' : 'Servers, channels, playlists and activity' ?></dd>
            </div>
            <div>
                <dt>Account created</dt>
                <dd><?= e($user['created_at'] !== '' ? date('d M Y', strtotime($user['created_at'])) : '—') ?></dd>
            </div>
            <div>
                <dt>Last sign-in</dt>
                <dd><?= e($user['last_login'] !== '' ? date('d M Y, H:i', strtotime($user['last_login'])) : 'This is your first session') ?></dd>
            </div>
            <div>
                <dt>This session</dt>
                <dd><?= $loginAt > 0 ? e(date('d M Y, H:i', $loginAt)) : 'Active now' ?></dd>
            </div>
        </dl>

        <div class="profile-note">
            <?= icon('info') ?>
            <p class="muted small">
                <?= $isAdmin
                    ? 'As an administrator you can add managers, reset passwords and change site settings.'
                    : 'A manager can add providers, browse channels, copy playlists and review activity. Settings and team management are administrator-only.' ?>
            </p>
        </div>
    </section>

    <section class="card pad" id="password">
        <div class="section-head">
            <h2>Change password</h2>
            <p class="muted small">Use at least 8 characters. Your current session stays signed in.</p>
        </div>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">

            <label class="field">
                <span>Current password</span>
                <div class="input-group">
                    <input class="input" type="password" name="current_password" id="currentPassword"
                           required autocomplete="current-password">
                    <button type="button" class="icon-btn in-group" data-reveal="currentPassword" aria-label="Show password">
                        <?= icon('eye') ?>
                    </button>
                </div>
            </label>

            <label class="field">
                <span>New password</span>
                <div class="input-group">
                    <input class="input" type="password" name="new_password" id="accountPassword"
                           required minlength="8" autocomplete="new-password" data-strength="accountPassword">
                    <button type="button" class="icon-btn in-group" data-reveal="accountPassword" aria-label="Show password">
                        <?= icon('eye') ?>
                    </button>
                </div>
                <span class="password-meter" data-meter-for="accountPassword" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </span>
            </label>

            <label class="field">
                <span>Confirm new password</span>
                <input class="input" type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </label>

            <div class="form-actions">
                <button class="btn primary" type="submit"><?= icon('key') ?> Update password</button>
            </div>
        </form>

        <div class="security-tip">
            <span class="check-icon ok"><?= icon('shield') ?></span>
            <div>
                <strong>Session protection</strong>
                <p class="muted small">Changing your password rotates this session and records the event in Activity.</p>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
