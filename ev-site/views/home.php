<section class="hero">
  <div class="container hero-inner">
    <h1><?= e($settings['hero_title'] ?? '') ?></h1>
    <p class="lead"><?= e($settings['hero_subtitle'] ?? '') ?></p>
    <form class="hero-search" action="<?= e(url('evs')) ?>" method="get" role="search">
      <label class="sr-only" for="hero-q">Search EVs</label>
      <input id="hero-q" name="q" type="search" placeholder="Search by brand or model, e.g. “IONIQ 5”" autocomplete="off">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
    <div class="hero-stats" data-hero-stats></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Featured EVs</h2>
      <a class="link-arrow" href="<?= e(url('evs')) ?>">View all</a>
    </div>
    <div class="card-grid" data-featured>
      <?php for ($i = 0; $i < 4; $i++): ?><div class="card skeleton"></div><?php endfor; ?>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <div class="section-head"><h2>Browse by body type</h2></div>
    <div class="chip-row" data-body-types></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head"><h2>Browse by brand</h2></div>
    <div class="brand-grid" data-brands></div>
  </div>
</section>
