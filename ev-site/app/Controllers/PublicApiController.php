<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SettingsRepository;
use App\Repositories\VehicleRepository;

final class PublicApiController
{
    /** GET /api/evs */
    public function index(Request $req): void
    {
        Response::json(VehicleRepository::search($req->query, false, SettingsRepository::perPage()));
    }

    /** GET /api/evs/{idOrSlug} */
    public function show(Request $req): void
    {
        $key = $req->param('id');
        $ev = ctype_digit($key)
            ? VehicleRepository::find((int) $key, true)
            : VehicleRepository::findBySlug($key, true);
        if (!$ev) {
            throw new HttpException(404, 'EV not found.');
        }
        Response::json(['data' => $ev, 'related' => VehicleRepository::related($ev)]);
    }

    /** GET /api/compare?ids=1,2,3 */
    public function compare(Request $req): void
    {
        $raw = $req->query['ids'] ?? '';
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
        $max = SettingsRepository::maxCompare();
        $ids = array_slice(array_filter($ids, static fn($i) => ctype_digit(trim((string) $i))), 0, $max);
        if (!$ids) {
            throw new HttpException(422, 'Provide 1 to ' . $max . ' EV ids, e.g. ?ids=1,2');
        }
        $evs = VehicleRepository::findMany($ids);
        Response::json(['data' => $evs, 'specs' => self::compareSpecs(), 'best' => self::best($evs)]);
    }

    /** GET /api/brands */
    public function brands(Request $req): void
    {
        $rows = Database::all(
            "SELECT b.id, b.name, b.slug, b.country, b.logo_url,
                    SUM(CASE WHEN v.status = 'published' THEN 1 ELSE 0 END) AS ev_count
             FROM brands b LEFT JOIN vehicles v ON v.brand_id = b.id
             GROUP BY b.id, b.name, b.slug, b.country, b.logo_url ORDER BY b.name"
        );
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['ev_count'] = (int) $r['ev_count'];
        }
        Response::json(['data' => $rows]);
    }

    /** GET /api/meta — everything the front-end needs to build filters. */
    public function meta(Request $req): void
    {
        $bounds = Database::one(
            "SELECT MIN(price_usd) AS min_price, MAX(price_usd) AS max_price, MIN(range_km) AS min_range,
                    MAX(range_km) AS max_range, MIN(model_year) AS min_year, MAX(model_year) AS max_year, COUNT(*) AS total
             FROM vehicles WHERE status = 'published'"
        ) ?? [];
        $s = SettingsRepository::all();
        Response::json([
            'settings' => [
                'site_name'       => $s['site_name'],
                'site_tagline'    => $s['site_tagline'],
                'currency_symbol' => $s['currency_symbol'],
                'max_compare'     => SettingsRepository::maxCompare(),
                'items_per_page'  => SettingsRepository::perPage(),
            ],
            'body_types'  => VehicleRepository::BODY_TYPES,
            'drivetrains' => VehicleRepository::DRIVETRAINS,
            'sorts'       => array_keys(VehicleRepository::SORTS),
            'bounds'      => array_map(static fn($v) => $v === null ? null : $v + 0, $bounds),
            'specs'       => self::compareSpecs(),
        ]);
    }

    /** Spec definitions: key, label, unit, and which direction is "better". */
    public static function compareSpecs(): array
    {
        return [
            ['key' => 'price_usd',          'label' => 'Price',              'unit' => 'currency', 'better' => 'lower',  'group' => 'Overview'],
            ['key' => 'model_year',         'label' => 'Model year',         'unit' => 'year',         'better' => null,     'group' => 'Overview'],
            ['key' => 'body_type',          'label' => 'Body type',          'unit' => '',         'better' => null,     'group' => 'Overview'],
            ['key' => 'seats',              'label' => 'Seats',              'unit' => '',         'better' => 'higher', 'group' => 'Overview'],
            ['key' => 'range_km',           'label' => 'Range (WLTP)',       'unit' => 'km',       'better' => 'higher', 'group' => 'Battery & range'],
            ['key' => 'battery_kwh',        'label' => 'Battery (usable)',   'unit' => 'kWh',      'better' => 'higher', 'group' => 'Battery & range'],
            ['key' => 'efficiency_wh_km',   'label' => 'Efficiency',         'unit' => 'Wh/km',    'better' => 'lower',  'group' => 'Battery & range'],
            ['key' => 'charging_dc_kw',     'label' => 'DC fast charging',   'unit' => 'kW',       'better' => 'higher', 'group' => 'Charging'],
            ['key' => 'charge_10_80_min',   'label' => '10–80% charge time', 'unit' => 'min',      'better' => 'lower',  'group' => 'Charging'],
            ['key' => 'charging_ac_kw',     'label' => 'AC charging',        'unit' => 'kW',       'better' => 'higher', 'group' => 'Charging'],
            ['key' => 'acceleration_0_100', 'label' => '0–100 km/h',         'unit' => 's',        'better' => 'lower',  'group' => 'Performance'],
            ['key' => 'top_speed_kmh',      'label' => 'Top speed',          'unit' => 'km/h',     'better' => 'higher', 'group' => 'Performance'],
            ['key' => 'power_kw',           'label' => 'Power',              'unit' => 'kW',       'better' => 'higher', 'group' => 'Performance'],
            ['key' => 'torque_nm',          'label' => 'Torque',             'unit' => 'Nm',       'better' => 'higher', 'group' => 'Performance'],
            ['key' => 'drivetrain',         'label' => 'Drivetrain',         'unit' => '',         'better' => null,     'group' => 'Performance'],
            ['key' => 'cargo_l',            'label' => 'Cargo space',        'unit' => 'L',        'better' => 'higher', 'group' => 'Practicality'],
            ['key' => 'weight_kg',          'label' => 'Weight',             'unit' => 'kg',       'better' => 'lower',  'group' => 'Practicality'],
        ];
    }

    /** For each numeric spec, the id(s) of the best EV. */
    private static function best(array $evs): array
    {
        $best = [];
        if (count($evs) < 2) {
            return $best;
        }
        foreach (self::compareSpecs() as $spec) {
            if (!$spec['better']) {
                continue;
            }
            $vals = [];
            foreach ($evs as $ev) {
                if ($ev[$spec['key']] !== null) {
                    $vals[$ev['id']] = $ev[$spec['key']];
                }
            }
            if (count($vals) < 2) {
                continue;
            }
            $target = $spec['better'] === 'higher' ? max($vals) : min($vals);
            $best[$spec['key']] = array_keys(array_filter($vals, static fn($v) => $v == $target));
        }
        return $best;
    }
}
