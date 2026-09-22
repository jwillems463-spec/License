<?php
declare(strict_types=1);

use App\Controllers\Admin\BrandController;
use App\Controllers\Admin\SystemController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\VehicleController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Controllers\PublicApiController;
use App\Core\Auth;
use App\Core\Router;

return static function (Router $r): void {
    // ---- Pages -------------------------------------------------------------
    $r->get('/', [PageController::class, 'home']);
    $r->get('/evs', [PageController::class, 'catalog']);
    $r->get('/ev/{slug}', [PageController::class, 'detail']);
    $r->get('/compare', [PageController::class, 'compare']);
    $r->get('/admin', [PageController::class, 'admin']);

    // ---- Public API --------------------------------------------------------
    $r->get('/api/evs', [PublicApiController::class, 'index']);
    $r->get('/api/evs/{id}', [PublicApiController::class, 'show']);
    $r->get('/api/compare', [PublicApiController::class, 'compare']);
    $r->get('/api/brands', [PublicApiController::class, 'brands']);
    $r->get('/api/meta', [PublicApiController::class, 'meta']);

    // ---- Auth --------------------------------------------------------------
    $r->get('/api/auth/me', [AuthController::class, 'me']);
    $r->group([Auth::csrf()], static function (Router $r): void {
        $r->post('/api/auth/login', [AuthController::class, 'login']);
        $r->post('/api/auth/logout', [AuthController::class, 'logout']);
        $r->post('/api/auth/password', [AuthController::class, 'changePassword'], [Auth::requireLogin()]);
    });

    // ---- Admin API (login + CSRF + password changed) -----------------------
    $r->group([Auth::requireReady(), Auth::csrf()], static function (Router $r): void {
        $can = [Auth::class, 'requirePermission'];

        $r->get('/api/admin/stats', [SystemController::class, 'stats'], [$can('dashboard.view')]);

        $r->get('/api/admin/evs', [VehicleController::class, 'index'], [$can('vehicles.view')]);
        $r->post('/api/admin/evs', [VehicleController::class, 'store'], [$can('vehicles.create')]);
        $r->post('/api/admin/evs/bulk', [VehicleController::class, 'bulk'], [$can('vehicles.view')]);
        $r->get('/api/admin/evs/{id}', [VehicleController::class, 'show'], [$can('vehicles.view')]);
        $r->put('/api/admin/evs/{id}', [VehicleController::class, 'update'], [$can('vehicles.update')]);
        $r->delete('/api/admin/evs/{id}', [VehicleController::class, 'destroy'], [$can('vehicles.delete')]);

        $r->get('/api/admin/brands', [BrandController::class, 'index'], [$can('brands.view')]);
        $r->post('/api/admin/brands', [BrandController::class, 'store'], [$can('brands.create')]);
        $r->put('/api/admin/brands/{id}', [BrandController::class, 'update'], [$can('brands.update')]);
        $r->delete('/api/admin/brands/{id}', [BrandController::class, 'destroy'], [$can('brands.delete')]);

        $r->get('/api/admin/users', [UserController::class, 'index'], [$can('users.manage')]);
        $r->post('/api/admin/users', [UserController::class, 'store'], [$can('users.manage')]);
        $r->put('/api/admin/users/{id}', [UserController::class, 'update'], [$can('users.manage')]);
        $r->delete('/api/admin/users/{id}', [UserController::class, 'destroy'], [$can('users.manage')]);

        $r->get('/api/admin/settings', [SystemController::class, 'settings'], [$can('settings.manage')]);
        $r->put('/api/admin/settings', [SystemController::class, 'saveSettings'], [$can('settings.manage')]);

        $r->get('/api/admin/audit', [SystemController::class, 'audit'], [$can('audit.view')]);

        $r->post('/api/admin/uploads', [SystemController::class, 'upload'], [$can('uploads.create')]);
    });
};
