<section class="page-head">
  <div class="container">
    <h1>Browse electric vehicles</h1>
    <p class="muted" data-result-count>Loading…</p>
  </div>
</section>

<div class="container catalog-layout">
  <aside class="filters" id="filters" data-filters aria-label="Filters">
    <div class="filters-head">
      <h2>Filters</h2>
      <button type="button" class="icon-btn filters-close" data-filters-close aria-label="Close filters">&times;</button>
    </div>
    <form data-filter-form>
      <div class="field">
        <label for="f-q">Search</label>
        <input id="f-q" name="q" type="search" placeholder="Brand or model">
      </div>

      <fieldset class="field">
        <legend>Brand</legend>
        <div class="check-list" data-filter-brands></div>
      </fieldset>

      <fieldset class="field">
        <legend>Body type</legend>
        <div class="check-list" data-filter-bodies></div>
      </fieldset>

      <fieldset class="field">
        <legend>Drivetrain</legend>
        <div class="check-list check-inline" data-filter-drives></div>
      </fieldset>

      <fieldset class="field">
        <legend>Price (<?= e($settings['currency_symbol'] ?? '$') ?>)</legend>
        <div class="range-pair">
          <input name="min_price" type="number" min="0" step="1000" placeholder="Min" aria-label="Minimum price">
          <span>–</span>
          <input name="max_price" type="number" min="0" step="1000" placeholder="Max" aria-label="Maximum price">
        </div>
      </fieldset>

      <div class="field">
        <label for="f-range">Minimum range: <output data-range-out>any</output></label>
        <input id="f-range" name="min_range" type="range" min="0" max="800" step="50" value="0">
      </div>

      <div class="field">
        <label for="f-seats">Minimum seats</label>
        <select id="f-seats" name="min_seats">
          <option value="">Any</option>
          <option value="4">4+</option>
          <option value="5">5+</option>
          <option value="7">7+</option>
        </select>
      </div>

      <div class="field">
        <label for="f-dc">Min. DC fast charging</label>
        <select id="f-dc" name="min_dc">
          <option value="">Any</option>
          <option value="100">100 kW+</option>
          <option value="150">150 kW+</option>
          <option value="200">200 kW+</option>
          <option value="250">250 kW+</option>
        </select>
      </div>

      <button type="reset" class="btn btn-ghost btn-block" data-filter-reset>Reset filters</button>
    </form>
  </aside>
  <div class="filters-backdrop" data-filters-backdrop hidden></div>

  <section class="results">
    <div class="results-toolbar">
      <button type="button" class="btn btn-ghost filters-open" data-filters-open aria-controls="filters">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4"/></svg> Filters
        <span class="badge" data-active-filters hidden></span>
      </button>
      <div class="active-chips" data-active-chips></div>
      <label class="sort">
        <span class="sr-only">Sort by</span>
        <select data-sort>
          <option value="featured">Featured</option>
          <option value="price_asc">Price: low to high</option>
          <option value="price_desc">Price: high to low</option>
          <option value="range_desc">Range: longest</option>
          <option value="accel_asc">Quickest 0–100</option>
          <option value="charging_desc">Fastest charging</option>
          <option value="newest">Newest added</option>
          <option value="name_asc">Name A–Z</option>
        </select>
      </label>
      <div class="view-toggle" role="group" aria-label="Layout">
        <button type="button" class="icon-btn" data-view="grid" aria-pressed="true" title="Grid view">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
        </button>
        <button type="button" class="icon-btn" data-view="table" aria-pressed="false" title="Table view">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>

    <div class="card-grid" data-results aria-live="polite">
      <?php for ($i = 0; $i < 6; $i++): ?><div class="card skeleton"></div><?php endfor; ?>
    </div>
    <nav class="pagination" data-pagination aria-label="Pagination"></nav>
  </section>
</div>
