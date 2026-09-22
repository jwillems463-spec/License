# Component inventory

Every UI component, where it is used, and where it lives in the code.
CSS: `ev-site/assets/css/app.css` (public) and `admin.css` (admin). JS: `ev-site/assets/js/app.js` and `admin.js`.

## Public site

### Layout and navigation

| Component | Used on | Markup / CSS | Behaviour (JS) | Responsive |
|---|---|---|---|---|
| **Site header** | all pages | `views/layout.php` `.site-header` | Sticky; the active page is marked `aria-current` | Fixed 64px height |
| **Nav + hamburger** | all | `.site-nav`, `.nav-toggle` | `initGlobal()` toggles `.open` and `aria-expanded` | Dropdown panel below 768px, inline from 768px |
| **Compare badge** | header | `[data-compare-count]` `.badge` | `Compare.render()` | — |
| **Compare tray** | all except /compare | `.compare-tray` | Lists selected EVs; remove one or Clear; "Compare now" link | Fixed bottom bar; pads the body with `.has-tray` |
| **Footer** | all | `.site-footer` | Text and email come from settings | Wraps |
| **Toast** | all | `[data-toast]` `.toast` | `toast(msg)` with `aria-live=polite` | Centered, width capped to the viewport |
| **Skip link** | all | `.skip-link` | — | — |

### Cards and blocks

| Component | Used on | Markup / CSS | Behaviour | Responsive |
|---|---|---|---|---|
| **EV card** | home, catalog, detail (related) | `card(ev)` → `.card` | Whole card is clickable (stretched link); separate "Compare" checkbox; Featured tag; placeholder SVG when there is no image | Grid of 1 / 2 / 3 / 4 columns (`.card-grid`) |
| **Skeleton card** | home, catalog (loading) | `.card.skeleton` | Shimmer (disabled under `prefers-reduced-motion`) | — |
| **Hero + search** | home | `.hero`, `.hero-search` | GET form → `/evs?q=` | Padding scales up at 640px |
| **Hero stats** | home | `[data-hero-stats]` | From `/api/meta` bounds | Wraps |
| **Chip** | home (body types), catalog (active filters) | `.chip`, `.chip-remove` | Active-filter chip removes its filter on click | Wraps |
| **Brand tile** | home | `.brand-tile` | Links to `/evs?brand=slug` | 2 / 3 / 5 columns |
| **Key-stat tile** | detail | `.key-stats .stat` | Range, 0–100, battery, DC | 2 / 4 / 2 / 4 columns across breakpoints |
| **Spec group** | detail | `.spec-group` (dl) | Built from the `/api/meta` `specs` definitions | 1 / 2 / 3 columns |
| **Breadcrumb** | detail | `.breadcrumb` | — | Wraps |
| **Empty state** | catalog, compare, 404 | `.empty-state` | — | — |

### Tables

| Component | Used on | Markup / CSS | Behaviour |
|---|---|---|---|
| **Results table** | catalog (≡ view) | `resultsTable()` `.data-table` | Alternative to the card grid; the choice is remembered in `localStorage`; scrolls sideways inside `.results-table-wrap` |
| **Comparison table** | compare | `table()` in `initCompare` `.compare-table` | Group header rows; best value per row highlighted (`.best` ★) from the API's `best` map; "Show differences only" toggle; Remove per column; empty slots up to the max; sticky first column; scrolls sideways on mobile |

### Filters and forms

| Component | Used on | Markup / CSS | Behaviour |
|---|---|---|---|
| **Filter panel** | catalog | `views/catalog.php` `.filters` | Instant apply (change events, with a debounce for text and number inputs); syncs state to the URL with `history.replaceState`; Reset button |
| **Checkbox list** | filters (brand, body, drivetrain) | `.check-list` | Built from `/api/brands` and `/api/meta`; brand counts shown |
| **Range pair** | filters (price) | `.range-pair` | min / max number inputs |
| **Range slider** | filters (min range) | `input[type=range]` + `<output>` | Max set from the data bounds |
| **Select** | filters (seats, DC), sort | `.field select`, `.sort select` | — |
| **Filter drawer** | catalog, below 1024px | `.filters.open` + `.filters-backdrop` | Opens from the "Filters" button (with an active-filter count badge); closes with ✕, the backdrop or Esc |
| **View toggle** | catalog | `.view-toggle .icon-btn[aria-pressed]` | Grid or table |
| **Sort select** | catalog | `[data-sort]` | 8 sort orders |
| **Pagination** | catalog | `pagination()` `.pagination` | Prev / next, first and last page, a window of ±1 pages, ellipses |
| **Autocomplete** | compare | `.autocomplete`, `.suggest` (`role=combobox/listbox`) | Debounced `/api/evs?q=`; ↑ / ↓ / Enter / Esc; excludes EVs already selected |
| **Toggle switch** | compare | `.switch` | Differences only |

### State

| Component | Implementation |
|---|---|
| **Compare store** | `Compare` object in `app.js`: `localStorage['ev_compare']` (falls back to memory in private mode); max from settings; syncs across tabs through the `storage` event; the compare page drops EVs that were unpublished or deleted |

## Admin dashboard

| Component | Markup / CSS | Behaviour |
|---|---|---|
| **Login card** | `renderLogin()` `.auth-card` | Inline error; 429 lockout message |
| **Forced password change** | `renderForcePassword()` | Blocks the dashboard until the password is changed |
| **Sidebar nav** | `renderShell()` `.sidebar` | Items filtered by role permissions; off-canvas below 960px |
| **Top bar** | `.topbar` | User name, role badge, Sign out |
| **Stat tile** | `.stat-tile` | Totals from `/api/admin/stats` |
| **Bar chart** | `.bars .bar-row` | Pure CSS horizontal bars (body type, brand) |
| **Activity feed** | `.activity` | Latest 8 audit entries |
| **Toolbar** | `.toolbar` | Search (debounced), status, brand and sort filters |
| **Data table** | `.tbl` | Row checkboxes, select all, thumbnails, status pills, row actions |
| **Bulk action bar** | `.bulk-bar` | Publish / unpublish / feature / unfeature / delete (delete only shown to admins) |
| **Pager** | `.pager` | Prev / next with totals |
| **Form field** | `field()` → `.f` | text, email, password, number with a unit suffix (`.input-unit`), select, textarea, checkbox; inline `.error` for 422 responses |
| **Fieldset card** | `.fieldset` | Groups the EV form sections |
| **Image uploader** | `[data-upload]` + `.image-preview` | Uploads to `/api/admin/uploads`, fills in Image URL, live preview |
| **Modal form** | `modalForm()` using `<dialog>` | Brand and user add/edit |
| **Confirm dialog** | `#confirm-dialog` `confirmDialog()` | All deletes |
| **Toast stack** | `#toasts .toast(.error)` | Success and error feedback |
| **Pill / role badge** | `.pill.published/.draft/.featured/.active/.inactive`, `.role-badge` | Status indicators |
| **Unsaved-changes guard** | `dirty` flag | Asks before changing route and on `beforeunload` |

## Design tokens

Defined as CSS custom properties on `:root`, with dark-mode values under `prefers-color-scheme: dark`:
`--bg --surface --surface-2 --text --muted --border --primary --primary-strong --primary-soft --on-primary --accent --danger --best --best-text --radius --shadow --font`.

To rebrand, change `--primary` and `--primary-strong` (and `--primary-soft`) in both files.

## Accessibility

- Semantic landmarks (header, nav, main, footer, aside) and a skip link
- Every input has a label; filter groups use `fieldset` + `legend`
- `aria-current`, `aria-expanded`, `aria-pressed`, `aria-live` and combobox / listbox roles
- Visible `:focus-visible` outlines; all controls are at least 36–44px tall
- Respects `prefers-reduced-motion` and `prefers-color-scheme`
