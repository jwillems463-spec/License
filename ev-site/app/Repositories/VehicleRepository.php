<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Shared query logic for vehicles (used by public API and admin API).
 */
final class VehicleRepository
{
    public const BODY_TYPES  = ['sedan', 'suv', 'hatchback', 'crossover', 'pickup', 'van', 'coupe', 'wagon'];
    public const DRIVETRAINS = ['FWD', 'RWD', 'AWD'];

    /** Public sort keys => SQL ORDER BY. */
    public const SORTS = [
        'featured'     => 'v.is_featured DESC, v.updated_at DESC',
        'newest'       => 'v.created_at DESC',
        'price_asc'    => 'v.price_usd IS NULL, v.price_usd ASC',
        'price_desc'   => 'v.price_usd DESC',
        'range_desc'   => 'v.range_km DESC',
        'range_asc'    => 'v.range_km IS NULL, v.range_km ASC',
        'accel_asc'    => 'v.acceleration_0_100 IS NULL, v.acceleration_0_100 ASC',
        'charging_desc'=> 'v.charging_dc_kw DESC',
        'name_asc'     => 'b.name ASC, v.model ASC',
    ];

    private const NUMERIC = [
        'price_usd' => 'float', 'battery_kwh' => 'float', 'range_km' => 'int', 'efficiency_wh_km' => 'int',
        'acceleration_0_100' => 'float', 'top_speed_kmh' => 'int', 'power_kw' => 'int', 'torque_nm' => 'int',
        'seats' => 'int', 'charging_ac_kw' => 'float', 'charging_dc_kw' => 'int', 'charge_10_80_min' => 'int',
        'cargo_l' => 'int', 'weight_kg' => 'int', 'model_year' => 'int', 'id' => 'int', 'brand_id' => 'int',
    ];

    private const SELECT = 'SELECT v.*, b.name AS brand_name, b.slug AS brand_slug, b.logo_url AS brand_logo_url
        FROM vehicles v JOIN brands b ON b.id = v.brand_id';

    /**
     * Search with filters + pagination.
     *
     * @param array $f      filters from query string
     * @param bool  $admin  include drafts and status filter
     */
    public static function search(array $f, bool $admin = false, int $defaultPerPage = 12): array
    {
        $where = [];
        $p = [];

        if (!$admin) {
            $where[] = "v.status = 'published'";
        } elseif (!empty($f['status']) && in_array($f['status'], ['draft', 'published'], true)) {
            $where[] = 'v.status = :status';
            $p['status'] = $f['status'];
        }

        if (!empty($f['q']) && is_string($f['q'])) {
            $where[] = "(CONCAT_WS(' ', b.name, v.model, v.variant) LIKE :q)";
            $p['q'] = '%' . addcslashes(mb_substr(trim($f['q']), 0, 100), '%_\\') . '%';
        }

        foreach (self::listParam($f['brand'] ?? null) as $i => $brand) {
            $key = "brand$i";
            $p[$key] = $brand;
            $brandConds[] = ctype_digit($brand) ? "b.id = :$key" : "b.slug = :$key";
        }
        if (!empty($brandConds)) {
            $where[] = '(' . implode(' OR ', $brandConds) . ')';
        }

        $bodies = array_values(array_intersect(self::listParam($f['body_type'] ?? null), self::BODY_TYPES));
        if ($bodies) {
            $in = [];
            foreach ($bodies as $i => $b) { $in[] = ":body$i"; $p["body$i"] = $b; }
            $where[] = 'v.body_type IN (' . implode(',', $in) . ')';
        }

        $drives = array_values(array_intersect(self::listParam($f['drivetrain'] ?? null), self::DRIVETRAINS));
        if ($drives) {
            $in = [];
            foreach ($drives as $i => $d) { $in[] = ":drive$i"; $p["drive$i"] = $d; }
            $where[] = 'v.drivetrain IN (' . implode(',', $in) . ')';
        }

        $ranges = [
            'min_price' => ['v.price_usd', '>='], 'max_price' => ['v.price_usd', '<='],
            'min_range' => ['v.range_km', '>='], 'max_range' => ['v.range_km', '<='],
            'min_seats' => ['v.seats', '>='],    'min_dc'    => ['v.charging_dc_kw', '>='],
            'max_accel' => ['v.acceleration_0_100', '<='],
            'year'      => ['v.model_year', '='],
        ];
        foreach ($ranges as $key => [$col, $op]) {
            if (isset($f[$key]) && $f[$key] !== '' && is_numeric($f[$key])) {
                $where[] = "$col $op :$key";
                $p[$key] = $f[$key] + 0;
            }
        }

        if (!empty($f['featured'])) {
            $where[] = 'v.is_featured = 1';
        }

        $sql = self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $countSql = 'SELECT COUNT(*) FROM vehicles v JOIN brands b ON b.id = v.brand_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

        $sort = $f['sort'] ?? ($admin ? 'newest' : 'featured');
        $order = self::SORTS[$sort] ?? self::SORTS['featured'];

        $perPage = max(1, min(100, (int) ($f['per_page'] ?? $defaultPerPage)));
        $page = max(1, (int) ($f['page'] ?? 1));
        $total = (int) Database::value($countSql, $p);
        $pages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::all("$sql ORDER BY $order, v.id DESC LIMIT $perPage OFFSET $offset", $p);

        return [
            'data' => array_map([self::class, 'present'], $rows),
            'meta' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => $pages,
                'sort'     => array_key_exists($sort, self::SORTS) ? $sort : 'featured',
            ],
        ];
    }

    public static function find(int $id, bool $publishedOnly = false): ?array
    {
        $row = Database::one(self::SELECT . ' WHERE v.id = ?' . ($publishedOnly ? " AND v.status = 'published'" : ''), [$id]);
        return $row ? self::present($row) : null;
    }

    public static function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $row = Database::one(self::SELECT . ' WHERE v.slug = ?' . ($publishedOnly ? " AND v.status = 'published'" : ''), [$slug]);
        return $row ? self::present($row) : null;
    }

    /** Fetch several published vehicles by id, preserving requested order. */
    public static function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all(self::SELECT . " WHERE v.id IN ($in) AND v.status = 'published'", $ids);
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = self::present($r);
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }
        return $out;
    }

    /** Related EVs: same body type or brand. */
    public static function related(array $v, int $limit = 4): array
    {
        $rows = Database::all(
            self::SELECT . " WHERE v.status = 'published' AND v.id <> :id AND (v.body_type = :body OR v.brand_id = :brand)
             ORDER BY (v.body_type = :body2) DESC, ABS(COALESCE(v.price_usd,0) - :price) ASC LIMIT $limit",
            ['id' => $v['id'], 'body' => $v['body_type'], 'body2' => $v['body_type'], 'brand' => $v['brand_id'], 'price' => (float) ($v['price_usd'] ?? 0)]
        );
        return array_map([self::class, 'present'], $rows);
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        $candidate = $slug;
        $n = 2;
        while (true) {
            $exists = Database::value(
                'SELECT id FROM vehicles WHERE slug = ?' . ($ignoreId ? ' AND id <> ?' : ''),
                $ignoreId ? [$candidate, $ignoreId] : [$candidate]
            );
            if (!$exists) {
                return $candidate;
            }
            $candidate = $slug . '-' . $n++;
        }
    }

    /** Normalize DB row types for JSON. */
    public static function present(array $r): array
    {
        foreach (self::NUMERIC as $k => $type) {
            if (array_key_exists($k, $r) && $r[$k] !== null) {
                $r[$k] = $type === 'int' ? (int) $r[$k] : (float) $r[$k];
            }
        }
        $r['is_featured'] = (bool) $r['is_featured'];
        $r['title'] = trim($r['brand_name'] . ' ' . $r['model'] . ' ' . ($r['variant'] ?? ''));
        $r['brand'] = [
            'id'       => $r['brand_id'],
            'name'     => $r['brand_name'],
            'slug'     => $r['brand_slug'],
            'logo_url' => $r['brand_logo_url'],
        ];
        unset($r['brand_name'], $r['brand_slug'], $r['brand_logo_url']);
        return $r;
    }

    /** Accept "a,b" or ["a","b"]. */
    private static function listParam($v): array
    {
        if ($v === null || $v === '') {
            return [];
        }
        $list = is_array($v) ? $v : explode(',', (string) $v);
        return array_slice(array_values(array_filter(array_map(static fn($x) => trim((string) $x), $list), 'strlen')), 0, 20);
    }
}
