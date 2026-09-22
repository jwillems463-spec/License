<?php
/** @var string $view @var string $page @var array $settings */
$siteName = $settings['site_name'] ?? 'EV Catalog';
$pageTitle = isset($title) ? $title . ' · ' . $siteName : $siteName . ' — ' . ($settings['site_tagline'] ?? '');
$metaDesc = $description ?? ($settings['site_tagline'] ?? '');
$appConfig = [
    'base'     => base_path(),
    'page'     => $page,
    'currency' => $settings['currency_symbol'] ?? '$',
    'maxCompare' => max(2, min(4, (int) ($settings['max_compare'] ?? 4))),
];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($metaDesc) ?>">
  <meta name="theme-color" content="#0b7a5a">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%230b7a5a' d='M13 2 4 14h7l-1 8 9-12h-7z'/></svg>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="page-<?= e($page) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="<?= e(url('/')) ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>
      <span><?= e($siteName) ?></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">
      <span></span><span></span><span></span>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Main">
      <a href="<?= e(url('/')) ?>" <?= $page === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
      <a href="<?= e(url('evs')) ?>" <?= $page === 'catalog' ? 'aria-current="page"' : '' ?>>Browse EVs</a>
      <a href="<?= e(url('compare')) ?>" class="nav-compare" <?= $page === 'compare' ? 'aria-current="page"' : '' ?>>
        Compare <span class="badge" data-compare-count hidden>0</span>
      </a>
    </nav>
  </div>
</header>

<main id="main">
  <?php require APP_ROOT . '/views/' . $view . '.php'; ?>
</main>

<div class="compare-tray" data-compare-tray hidden>
  <div class="container compare-tray-inner">
    <div class="compare-tray-items" data-compare-items></div>
    <div class="compare-tray-actions">
      <button type="button" class="btn btn-ghost btn-sm" data-compare-clear>Clear</button>
      <a class="btn btn-primary btn-sm" href="<?= e(url('compare')) ?>">Compare now</a>
    </div>
  </div>
</div>

<div class="toast" role="status" aria-live="polite" data-toast hidden></div>

<footer class="site-footer">
  <div class="container footer-inner">
    <p><?= e($settings['footer_text'] ?? '') ?></p>
    <?php if (!empty($settings['contact_email'])): ?>
      <p><a href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a></p>
    <?php endif; ?>
  </div>
</footer>

<script>window.APP = <?= json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
