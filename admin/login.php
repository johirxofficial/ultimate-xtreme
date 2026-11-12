<?php
require_once __DIR__ . '/../config.php';
require_once APP_ROOT . '/includes/icons.php';

if (auth_check()) {
    redirect('admin/index.php');
}

$setup = auth_setup_needed();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(pget('csrf'))) {
        $error = 'Security token expired. Reload the page and try again.';
    } else {
        $user = trim(pget('username'));
        $pass = pget('password');

        if ($setup) {
            $confirm = pget('confirm');

            if (!username_is_valid($user)) {
                $error = 'Username must be 3–32 characters using letters, numbers, dots, dashes or underscores.';
            } elseif (strlen($pass) < 8) {
                $error = 'Use at least 8 characters for the administrator password.';
            } elseif (!hash_equals($pass, $confirm)) {
                $error = 'The two passwords do not match.';
            } elseif (auth_setup($user, $pass)) {
                flash('success', 'Your administrator account is ready.');
                redirect('admin/index.php');
            } else {
                $error = 'Could not create the account. Please try again.';
            }
        } elseif (auth_attempt($user, $pass)) {
            redirect('admin/index.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$siteTitle = (string) setting('site_title', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title><?= $setup ? 'Set up' : 'Sign in' ?> · <?= e($siteTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>">
</head>
<body class="auth-body">
<?= icon_sprite() ?>

<main class="auth-popup-wrap">
    <section class="auth-popup" role="dialog" aria-modal="true" aria-labelledby="authTitle">
        <div class="auth-popup-bar" aria-hidden="true">
            <span class="auth-popup-lights"><span></span><span></span><span></span></span>
            <span class="auth-popup-bar-title"><?= $setup ? 'First-run setup' : 'Secure sign-in' ?></span>
            <span class="auth-popup-bar-meta">v<?= e(APP_VERSION) ?></span>
        </div>

        <div class="auth-popup-body">
            <header class="auth-popup-head">
                <p class="auth-eyebrow"><?= e($siteTitle) ?> · Restream console</p>
                <h1 id="authTitle"><?= $setup ? 'Create the first administrator' : 'Sign in' ?></h1>
                <p class="muted">
                    <?= $setup
                        ? 'This one-time step creates the account that manages providers, channels, playlists and the team.'
                        : 'Enter your administrator or manager account to continue.' ?>
                </p>
            </header>

            <?php foreach (flash_take() as $f): ?>
                <div class="alert <?= e($f['type'] === 'error' ? 'error' : 'success') ?>">
                    <?= icon($f['type'] === 'error' ? 'alert' : 'check') ?>
                    <span><?= e($f['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ($error !== ''): ?>
                <div class="alert error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" class="auth-form" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <label class="field">
                    <span>Username</span>
                    <input class="input" type="text" name="username" value="<?= e(pget('username')) ?>"
                           required autofocus autocomplete="username" spellcheck="false"
                           placeholder="Your username" maxlength="32">
                </label>

                <label class="field">
                    <span>Password</span>
                    <div class="input-group">
                        <input class="input" type="password" name="password" id="loginPassword" required
                               autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"
                               placeholder="••••••••" <?= $setup ? 'data-strength="loginPassword" minlength="8"' : '' ?>>
                        <button type="button" class="icon-btn in-group" data-reveal="loginPassword" aria-label="Show password">
                            <?= icon('eye') ?>
                        </button>
                    </div>
                    <?php if ($setup): ?>
                        <span class="field-hint">At least 8 characters. A longer passphrase is even better.</span>
                        <span class="password-meter" data-meter-for="loginPassword" aria-hidden="true">
                            <span></span><span></span><span></span><span></span>
                        </span>
                    <?php endif; ?>
                </label>

                <?php if ($setup): ?>
                    <label class="field">
                        <span>Confirm password</span>
                        <input class="input" type="password" name="confirm" required autocomplete="new-password"
                               placeholder="Repeat the password" minlength="8">
                    </label>
                <?php endif; ?>

                <button class="btn primary block lg" type="submit">
                    <?= $setup ? 'Create administrator' : 'Sign in' ?>
                    <?= icon('chevron-right') ?>
                </button>
            </form>

            <footer class="auth-popup-foot">
                <span><?= icon('shield', 'icon inline-icon') ?> CSRF-protected session</span>
                <span>Updated <?= e(app_updated_label()) ?></span>
            </footer>
        </div><!-- /.auth-popup-body -->
    </section>

    <p class="auth-note muted">
        <?= $setup
            ? 'Managers can be added later from Team. Administrators control settings and accounts.'
            : 'Managers can operate servers and channels. Administrators also manage the team and settings.' ?>
    </p>
</main>

<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
