<?php /** @var array $ev */ ?>
<div class="container">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a> <span aria-hidden="true">/</span>
    <a href="<?= e(url('evs')) ?>">EVs</a> <span aria-hidden="true">/</span>
    <a href="<?= e(url('evs?brand=' . rawurlencode($ev['brand']['slug']))) ?>"><?= e($ev['brand']['name']) ?></a>
    <span aria-hidden="true">/</span> <span aria-current="page"><?= e($ev['model']) ?></span>
  </nav>
</div>

<section class="detail" data-detail data-ev-id="<?= (int) $ev['id'] ?>" data-ev-slug="<?= e($ev['slug']) ?>">
  <div class="container detail-top">
    <div class="detail-media">
      <div class="media-frame" data-detail-image>
        <?php if (!empty($ev['image_url'])): ?>
          <img src="<?= e($ev['image_url']) ?>" alt="<?= e($ev['title']) ?>">
        <?php endif; ?>
      </div>
    </div>
    <div class="detail-summary">
      <p class="eyebrow"><?= e($ev['brand']['name']) ?> · <?= (int) $ev['model_year'] ?> · <?= e($ev['body_type'] === 'suv' ? 'SUV' : ucfirst($ev['body_type'])) ?></p>
      <h1><?= e($ev['model']) ?> <span class="variant"><?= e($ev['variant'] ?? '') ?></span></h1>
      <p class="price" data-detail-price></p>
      <dl class="key-stats" data-key-stats></dl>
      <div class="detail-actions">
        <button type="button" class="btn btn-primary" data-compare-toggle="<?= (int) $ev['id'] ?>">Add to compare</button>
        <a class="btn btn-ghost" href="<?= e(url('compare')) ?>">Go to comparison</a>
      </div>
      <?php if (!empty($ev['description'])): ?>
        <p class="description"><?= nl2br(e($ev['description'])) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="container">
    <h2 class="section-title">Full specifications</h2>
    <div class="spec-groups" data-spec-groups></div>

    <div class="section-head related-head">
      <h2>Similar EVs</h2>
    </div>
    <div class="card-grid" data-related></div>
  </div>
</section>
