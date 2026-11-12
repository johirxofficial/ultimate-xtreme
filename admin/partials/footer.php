<?php
/**
 * Admin shell — footer with dialogs, toasts and scripts.
 */
$flashes = flash_take();
$uxJson  = json_encode($uxData ?? ['base' => app_base_url(), 'palette' => []], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
    </main>

    <footer class="app-foot">
        <span><?= e(setting('site_title', APP_NAME)) ?> · v<?= e(APP_VERSION) ?></span>
        <span class="dot-sep">•</span>
        <a href="<?= e(url('admin/account.php')) ?>">My account</a>
        <?php if (auth_is_admin()): ?>
            <span class="dot-sep">•</span>
            <a href="<?= e(url('admin/settings.php')) ?>">Settings</a>
        <?php endif; ?>
        <span class="dot-sep">•</span>
        <a href="<?= e(url('admin/about.php')) ?>">About</a>
        <span class="dot-sep">•</span>
        <span>Relay &amp; direct delivery</span>
    </footer>
</div><!-- /.shell -->

<!-- Confirm dialog -->
<dialog class="modal confirm-modal" id="confirmDialog">
    <div class="modal-body confirm-body">
        <div class="confirm-icon"><?= icon('alert') ?></div>
        <div>
            <h3 id="confirmTitle">Are you sure?</h3>
            <p class="muted" id="confirmMessage">This action cannot be undone.</p>
        </div>
    </div>
    <div class="modal-foot">
        <button type="button" class="btn ghost" data-confirm-cancel>Cancel</button>
        <button type="button" class="btn danger" data-confirm-ok id="confirmOk">Confirm</button>
    </div>
</dialog>

<!-- Command palette -->
<dialog class="modal palette-modal" id="paletteDialog">
    <div class="palette-input-wrap">
        <?= icon('search') ?>
        <input type="text" id="paletteInput" placeholder="Jump to a page or server…" autocomplete="off" spellcheck="false" aria-label="Quick jump">
        <kbd>Esc</kbd>
    </div>
    <div class="palette-results" id="paletteResults"></div>
    <div class="palette-foot">
        <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
        <span><kbd>↵</kbd> open</span>
        <span><kbd>Esc</kbd> close</span>
    </div>
</dialog>

<div class="toast-stack" id="toasts" aria-live="polite">
    <?php foreach ($flashes as $f): ?>
        <div class="toast <?= e($f['type']) ?>">
            <?= icon($f['type'] === 'error' ? 'alert' : 'check', 'icon toast-icon') ?>
            <span><?= e($f['message']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<script>window.UX = <?= $uxJson ?>;</script>
<script src="<?= e(app_base_url()) ?>/assets/app.js"></script>
</body>
</html>
