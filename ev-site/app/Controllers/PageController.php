<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SettingsRepository;
use App\Repositories\VehicleRepository;

/**
 * Server-rendered page shells. Data is loaded client-side from the JSON API
 * so every admin change shows up on the next page load / fetch.
 */
final class PageController
{
    public function home(Request $req): void
    {
        $this->page('home', 'home');
    }

    public function catalog(Request $req): void
    {
        $this->page('catalog', 'catalog', ['title' => 'All EVs']);
    }

    public function detail(Request $req): void
    {
        $ev = VehicleRepository::findBySlug($req->param('slug'), true);
        if (!$ev) {
            throw new HttpException(404, 'EV not found.');
        }
        $this->page('detail', 'detail', [
            'title'       => $ev['title'],
            'description' => mb_substr((string) $ev['description'], 0, 160),
            'ev'          => $ev,
        ]);
    }

    public function compare(Request $req): void
    {
        $this->page('compare', 'compare', ['title' => 'Compare EVs']);
    }

    public function admin(Request $req): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        Response::view('admin/app', [
            'settings' => SettingsRepository::all(),
            'csrf'     => Session::csrfToken(),
        ]);
    }

    public static function notFound(): void
    {
        $settings = [];
        try {
            $settings = SettingsRepository::all();
        } catch (\Throwable $e) {
            // DB unavailable — render with defaults
        }
        Response::view('layout', [
            'view' => 'not_found', 'page' => 'notfound', 'title' => 'Page not found', 'settings' => $settings,
        ], 404);
    }

    private function page(string $view, string $page, array $data = []): void
    {
        Response::view('layout', array_merge([
            'view'     => $view,
            'page'     => $page,
            'settings' => SettingsRepository::all(),
        ], $data));
    }
}
