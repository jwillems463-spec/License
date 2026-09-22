<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\VehicleRepository;

final class VehicleController
{
    private static function schema(): array
    {
        return [
            'brand_id'           => ['type' => 'int', 'required' => true, 'min' => 1],
            'model'              => ['type' => 'string', 'required' => true, 'max' => 120],
            'variant'            => ['type' => 'string', 'max' => 120],
            'slug'               => ['type' => 'string', 'max' => 180],
            'model_year'         => ['type' => 'int', 'required' => true, 'min' => 1990, 'max' => 2100],
            'body_type'          => ['type' => 'enum', 'required' => true, 'values' => VehicleRepository::BODY_TYPES],
            'drivetrain'         => ['type' => 'enum', 'required' => true, 'values' => VehicleRepository::DRIVETRAINS],
            'price_usd'          => ['type' => 'float', 'min' => 0, 'max' => 99999999],
            'battery_kwh'        => ['type' => 'float', 'min' => 0, 'max' => 99999],
            'range_km'           => ['type' => 'int', 'min' => 0, 'max' => 5000],
            'efficiency_wh_km'   => ['type' => 'int', 'min' => 0, 'max' => 2000],
            'acceleration_0_100' => ['type' => 'float', 'min' => 0, 'max' => 99],
            'top_speed_kmh'      => ['type' => 'int', 'min' => 0, 'max' => 600],
            'power_kw'           => ['type' => 'int', 'min' => 0, 'max' => 5000],
            'torque_nm'          => ['type' => 'int', 'min' => 0, 'max' => 20000],
            'seats'              => ['type' => 'int', 'min' => 1, 'max' => 50],
            'charging_ac_kw'     => ['type' => 'float', 'min' => 0, 'max' => 999],
            'charging_dc_kw'     => ['type' => 'int', 'min' => 0, 'max' => 2000],
            'charge_10_80_min'   => ['type' => 'int', 'min' => 0, 'max' => 1000],
            'cargo_l'            => ['type' => 'int', 'min' => 0, 'max' => 20000],
            'weight_kg'          => ['type' => 'int', 'min' => 0, 'max' => 60000],
            'image_url'          => ['type' => 'url', 'max' => 500],
            'description'        => ['type' => 'string', 'max' => 10000],
            'status'             => ['type' => 'enum', 'values' => ['draft', 'published'], 'default' => 'draft'],
            'is_featured'        => ['type' => 'bool', 'default' => 0],
        ];
    }

    /** GET /api/admin/evs */
    public function index(Request $req): void
    {
        Response::json(VehicleRepository::search($req->query, true, 20));
    }

    /** GET /api/admin/evs/{id} */
    public function show(Request $req): void
    {
        Response::json(['data' => $this->findOrFail((int) $req->param('id'))]);
    }

    /** POST /api/admin/evs */
    public function store(Request $req): void
    {
        $data = Validator::validate($req->body(), self::schema());
        $this->assertBrand((int) $data['brand_id']);

        $data['slug'] = VehicleRepository::uniqueSlug($this->slugSource($data));
        $data['created_by'] = $data['updated_by'] = Auth::user()['id'];

        $id = Database::insert('vehicles', $data);
        $ev = VehicleRepository::find($id);
        Audit::log('create', 'vehicle', $id, 'Created ' . $ev['title']);
        Response::json(['data' => $ev], 201);
    }

    /** PUT /api/admin/evs/{id} */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $current = $this->findOrFail($id);
        $data = Validator::validate($req->body(), self::schema(), true);

        if (isset($data['brand_id'])) {
            $this->assertBrand((int) $data['brand_id']);
        }
        if (array_key_exists('slug', $data)) {
            // Explicit slug (or blank = regenerate)
            $merged = array_merge($current, $data);
            $data['slug'] = VehicleRepository::uniqueSlug($data['slug'] ?: $this->slugSource($merged), $id);
        }
        $data['updated_by'] = Auth::user()['id'];

        Database::update('vehicles', $id, $data);
        $ev = VehicleRepository::find($id);
        Audit::log('update', 'vehicle', $id, 'Updated ' . $ev['title']);
        Response::json(['data' => $ev]);
    }

    /** DELETE /api/admin/evs/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $ev = $this->findOrFail($id);
        Database::query('DELETE FROM vehicles WHERE id = ?', [$id]);
        Audit::log('delete', 'vehicle', $id, 'Deleted ' . $ev['title']);
        Response::json(['data' => ['ok' => true]]);
    }

    /** POST /api/admin/evs/bulk — {ids:[], action:"publish|unpublish|feature|unfeature|delete"} */
    public function bulk(Request $req): void
    {
        $ids = array_values(array_filter(array_map('intval', (array) $req->input('ids', [])), static fn($i) => $i > 0));
        $action = (string) $req->input('action', '');
        $map = [
            'publish'   => ["status = 'published'", 'vehicles.update'],
            'unpublish' => ["status = 'draft'", 'vehicles.update'],
            'feature'   => ['is_featured = 1', 'vehicles.update'],
            'unfeature' => ['is_featured = 0', 'vehicles.update'],
            'delete'    => [null, 'vehicles.delete'],
        ];
        if (!$ids || !isset($map[$action])) {
            throw new HttpException(422, 'Select at least one EV and a valid action.');
        }
        [$set, $perm] = $map[$action];
        if (!Auth::can($perm)) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($set === null) {
            $count = Database::query("DELETE FROM vehicles WHERE id IN ($in)", $ids)->rowCount();
        } else {
            $params = array_merge([Auth::user()['id']], $ids);
            $count = Database::query("UPDATE vehicles SET $set, updated_by = ? WHERE id IN ($in)", $params)->rowCount();
        }
        Audit::log('bulk_' . $action, 'vehicle', null, sprintf('%s %d EV(s): #%s', ucfirst($action), count($ids), implode(', #', $ids)));
        Response::json(['data' => ['affected' => $count]]);
    }

    private function findOrFail(int $id): array
    {
        $ev = VehicleRepository::find($id);
        if (!$ev) {
            throw new HttpException(404, 'EV not found.');
        }
        return $ev;
    }

    private function assertBrand(int $brandId): void
    {
        if (!Database::value('SELECT id FROM brands WHERE id = ?', [$brandId])) {
            throw new HttpException(422, 'Please correct the highlighted fields.', ['brand_id' => 'Unknown brand.']);
        }
    }

    private function slugSource(array $d): string
    {
        if (!empty($d['slug'])) {
            return $d['slug'];
        }
        $brand = (string) Database::value('SELECT name FROM brands WHERE id = ?', [$d['brand_id']]);
        return implode(' ', array_filter([$brand, $d['model'] ?? '', $d['variant'] ?? '', $d['model_year'] ?? '']));
    }
}
