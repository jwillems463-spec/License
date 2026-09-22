<?php /** @var array $settings @var string $csrf */ ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin · <?= e($settings['site_name'] ?? 'EV Catalog') ?></title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%230b7a5a' d='M13 2 4 14h7l-1 8 9-12h-7z'/></svg>">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
  <div id="admin-root" class="boot">
    <div class="boot-msg">Loading dashboard…</div>
  </div>
  <div class="toast-stack" id="toasts" aria-live="polite"></div>
  <dialog id="confirm-dialog" class="dialog">
    <form method="dialog">
      <h2 data-confirm-title>Are you sure?</h2>
      <p data-confirm-text></p>
      <div class="dialog-actions">
        <button value="cancel" class="btn btn-ghost">Cancel</button>
        <button value="ok" class="btn btn-danger" data-confirm-ok>Delete</button>
      </div>
    </form>
  </dialog>
  <script>
    window.ADMIN = <?= json_encode([
        'base'     => base_path(),
        'csrf'     => $csrf,
        'siteName' => $settings['site_name'] ?? 'EV Catalog',
        'currency' => $settings['currency_symbol'] ?? '$',
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
