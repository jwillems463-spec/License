<section class="page-head">
  <div class="container">
    <h1>Compare EVs</h1>
    <p class="muted">Pick up to <span data-max-compare>4</span> EVs. Best values in each row are highlighted.</p>
  </div>
</section>

<div class="container">
  <div class="compare-picker">
    <label for="compare-add" class="sr-only">Add an EV to compare</label>
    <div class="autocomplete">
      <input id="compare-add" type="search" placeholder="Add an EV… type a brand or model" autocomplete="off"
             role="combobox" aria-expanded="false" aria-controls="compare-suggest" aria-autocomplete="list">
      <ul id="compare-suggest" class="suggest" role="listbox" hidden></ul>
    </div>
    <label class="switch">
      <input type="checkbox" data-diff-only>
      <span>Show differences only</span>
    </label>
  </div>

  <div class="compare-wrap" data-compare-root>
    <p class="empty-state">Loading…</p>
  </div>
</div>
