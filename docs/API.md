# e-carscompare REST API

Base URL: `https://e-carscompare.com/api` (or `/ev/api` in a subfolder install).
All responses are JSON (`Content-Type: application/json`) and are never cached (`Cache-Control: no-store`), so admin changes appear immediately.

## Conventions

**Success**

```json
{ "data": { ... } }                       // single resource
{ "data": [ ... ], "meta": { ... } }      // collection
```

**Error**

```json
{ "error": { "message": "Please correct the highlighted fields.",
             "fields": { "model": "This field is required." },
             "code": "csrf_mismatch" } }   // "code" only on some errors
```

| Status | Meaning |
|---|---|
| 200 / 201 | OK / created |
| 400 | Malformed JSON body |
| 401 | Not signed in |
| 403 | Signed in but not allowed, **or** CSRF token missing or invalid (`code: "csrf_mismatch"`), **or** the temporary password must be changed first |
| 404 | Not found (drafts are 404 on the public API) |
| 405 | Method not allowed |
| 409 | Conflict (for example, deleting a brand that still has EVs) |
| 422 | Validation failed. See `error.fields` |
| 429 | Too many failed logins (5 per 15 minutes per IP) |

---

## Public endpoints (no auth)

### `GET /api/evs`: list and filter EVs

Returns **published** EVs only.

| Query param | Type | Example | Notes |
|---|---|---|---|
| `q` | string | `ioniq` | Matches brand, model and variant |
| `brand` | csv of slugs or ids | `tesla,kia` | |
| `body_type` | csv | `suv,crossover` | `sedan suv hatchback crossover pickup van coupe wagon` |
| `drivetrain` | csv | `AWD` | `FWD RWD AWD` |
| `min_price` / `max_price` | number | `40000` | |
| `min_range` / `max_range` | int (km) | `500` | |
| `min_seats` | int | `7` | |
| `min_dc` | int (kW) | `150` | Minimum DC charging power |
| `max_accel` | number (s) | `5` | Maximum 0–100 km/h time |
| `year` | int | `2025` | |
| `featured` | `1` | | Featured EVs only |
| `sort` | enum | `range_desc` | `featured` (default) `newest price_asc price_desc range_desc range_asc accel_asc charging_desc name_asc` |
| `page` | int | `2` | |
| `per_page` | int 1–100 | `12` | Default comes from site settings |

```http
GET /api/evs?body_type=suv&min_range=500&sort=price_asc
```

```json
{
  "data": [{
    "id": 2, "slug": "tesla-model-y-long-range-awd-2025", "title": "Tesla Model Y Long Range AWD",
    "brand_id": 1, "brand": { "id": 1, "name": "Tesla", "slug": "tesla", "logo_url": null },
    "model": "Model Y", "variant": "Long Range AWD", "model_year": 2025,
    "body_type": "suv", "drivetrain": "AWD", "price_usd": 50490.0,
    "battery_kwh": 78.1, "range_km": 533, "efficiency_wh_km": 155,
    "acceleration_0_100": 5.0, "top_speed_kmh": 201, "power_kw": 378, "torque_nm": 493,
    "seats": 5, "charging_ac_kw": 11.0, "charging_dc_kw": 250, "charge_10_80_min": 27,
    "cargo_l": 854, "weight_kg": 1979, "image_url": null, "description": "…",
    "status": "published", "is_featured": true,
    "created_at": "2026-01-01 10:00:00", "updated_at": "2026-01-02 09:30:00"
  }],
  "meta": { "total": 3, "page": 1, "per_page": 12, "pages": 1, "sort": "price_asc" }
}
```

### `GET /api/evs/{id|slug}`: EV detail

```http
GET /api/evs/tesla-model-3-long-range-awd-2025
GET /api/evs/1
```

Returns `{ "data": EV, "related": [EV, …up to 4] }`. Related EVs share the same body type or brand and are ordered by closest price. Returns 404 for drafts or unknown EVs.

### `GET /api/compare?ids=1,3,6`: comparison

Takes up to `max_compare` ids (a site setting, 2–4). Unknown ids and drafts are dropped, and the requested order is kept.

```json
{
  "data":  [EV, EV, EV],
  "specs": [{ "key": "range_km", "label": "Range (WLTP)", "unit": "km", "better": "higher", "group": "Battery & range" }, …],
  "best":  { "range_km": [1], "price_usd": [1], "charge_10_80_min": [3, 6], … }
}
```

`best` maps each spec to the id(s) of the winning EV, taking into account whether higher or lower is better. Ties return several ids.

### `GET /api/brands`

`{ "data": [{ "id", "name", "slug", "country", "logo_url", "ev_count" }] }`, where `ev_count` counts published EVs only.

### `GET /api/meta`

Everything a front-end needs to build filters: public `settings`, `body_types`, `drivetrains`, `sorts`, `bounds` (min and max price, range and year, plus the total), and `specs` (the definitions of the comparison rows).

---

## Authentication

Session-cookie auth plus a CSRF token. Every `POST`, `PUT` and `DELETE` request must send the header `X-CSRF-Token: <token>`.
Get the token from `GET /api/auth/me` (it is also embedded in the admin page). The token rotates on login, so use the `csrf_token` returned by each auth response.

| Method | Endpoint | Body | Notes |
|---|---|---|---|
| GET | `/api/auth/me` | | `{ data: user or null, csrf_token }` |
| POST | `/api/auth/login` | `{ email, password }` | `{ data: user, csrf_token }` |
| POST | `/api/auth/logout` | | |
| POST | `/api/auth/password` | `{ current_password, new_password }` | At least 10 characters, with letters and numbers. Clears `must_change_password` |

A user object looks like this: `{ id, name, email, role, must_change_password, last_login_at, permissions: [...] }`.

While `must_change_password` is true, every `/api/admin/*` request returns **403** until the password is changed.

### Roles and permissions

| Permission | admin | editor |
|---|:-:|:-:|
| `dashboard.view` | ✓ | ✓ |
| `vehicles.view` / `vehicles.create` / `vehicles.update` | ✓ | ✓ |
| `vehicles.delete` (including bulk delete) | ✓ | ✗ |
| `brands.view` / `brands.create` / `brands.update` | ✓ | ✓ |
| `brands.delete` | ✓ | ✗ |
| `uploads.create` | ✓ | ✓ |
| `users.manage` | ✓ | ✗ |
| `settings.manage` | ✓ | ✗ |
| `audit.view` | ✓ | ✗ |

Roles and the active flag are re-read from the database on every request, so demoting or deactivating a user takes effect on their next request.

---

## Admin endpoints (auth + CSRF)

### EVs

| Method | Endpoint | Permission | Notes |
|---|---|---|---|
| GET | `/api/admin/evs` | vehicles.view | Same filters as the public list, **plus** `status=draft\|published`; includes drafts. Default sort is `newest`. |
| GET | `/api/admin/evs/{id}` | vehicles.view | |
| POST | `/api/admin/evs` | vehicles.create | Returns 201 with the created EV |
| PUT | `/api/admin/evs/{id}` | vehicles.update | **Partial update**: send only the fields that change |
| DELETE | `/api/admin/evs/{id}` | vehicles.delete | |
| POST | `/api/admin/evs/bulk` | depends on action | `{ ids: [1,2], action: "publish"\|"unpublish"\|"feature"\|"unfeature"\|"delete" }` → `{ data: { affected } }` |

**EV fields** (\* = required on create)

| Field | Type | Rules |
|---|---|---|
| `brand_id`\* | int | Must exist |
| `model`\* | string | ≤120 characters |
| `variant` | string | ≤120 characters |
| `slug` | string | Leave blank to generate it from brand, model, variant and year. Made unique automatically |
| `model_year`\* | int | 1990–2100 |
| `body_type`\* | enum | `sedan suv hatchback crossover pickup van coupe wagon` |
| `drivetrain`\* | enum | `FWD RWD AWD` |
| `price_usd` | decimal | ≥0 |
| `battery_kwh`, `charging_ac_kw`, `acceleration_0_100` | decimal | ≥0 |
| `range_km`, `efficiency_wh_km`, `top_speed_kmh`, `power_kw`, `torque_nm`, `charging_dc_kw`, `charge_10_80_min`, `cargo_l`, `weight_kg` | int | ≥0 |
| `seats` | int | 1–50 |
| `image_url` | string | `http(s)://…` or a site-relative `/uploads/…` path |
| `description` | text | ≤10,000 characters |
| `status` | enum | `draft` (default) or `published` |
| `is_featured` | bool | |

Send `null` or `""` to clear an optional field.

```bash
curl -X PUT https://e-carscompare.com/api/admin/evs/1 \
  -H "X-CSRF-Token: $TOKEN" -H "Content-Type: application/json" -b cookies.txt \
  -d '{"price_usd": 44990, "status": "published"}'
```

### Brands

| Method | Endpoint | Permission | Body |
|---|---|---|---|
| GET | `/api/admin/brands` | brands.view | Includes `ev_count` (all statuses) |
| POST | `/api/admin/brands` | brands.create | `{ name*, slug, country, website, logo_url }` |
| PUT | `/api/admin/brands/{id}` | brands.update | Partial |
| DELETE | `/api/admin/brands/{id}` | brands.delete | Returns 409 if any EV still uses the brand |

### Users (admin only)

| Method | Endpoint | Body |
|---|---|---|
| GET | `/api/admin/users` | |
| POST | `/api/admin/users` | `{ name*, email*, role*, password*, is_active, must_change_password }` |
| PUT | `/api/admin/users/{id}` | Partial. `password` is optional (resets it) |
| DELETE | `/api/admin/users/{id}` | |

Safeguards: you cannot delete, demote or deactivate yourself, and the last active admin cannot be removed.

### Settings (admin only)

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/api/admin/settings` | `{ data: {key: value}, schema: [{key, label, max}] }` |
| PUT | `/api/admin/settings` | Partial. Keys: `site_name site_tagline contact_email currency_symbol hero_title hero_subtitle footer_text max_compare(2-4) items_per_page(6-48)` |

### Uploads

`POST /api/admin/uploads`: `multipart/form-data` with the field `image` (JPG, PNG, WEBP or GIF, 5 MB by default).
Returns `201 { data: { url: "/uploads/2026/09/abc123.webp" } }`. Put the URL in an EV's `image_url` or a brand's `logo_url`.

### Dashboard and audit

| Method | Endpoint | Permission | Returns |
|---|---|---|---|
| GET | `/api/admin/stats` | dashboard.view | Vehicle counts, brand and user counts, `by_body`, `by_brand`, 8 most recent activity entries |
| GET | `/api/admin/audit?page=N` | audit.view | 50 entries per page, newest first |

---

## Hosts that block PUT or DELETE

Some locked-down hosts reject `PUT` and `DELETE`. Send `POST` with the header `X-HTTP-Method-Override: PUT` (or `DELETE`) instead. The router treats it as the real method.
