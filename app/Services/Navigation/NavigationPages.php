<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavigationPages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Navigation;

use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;

/**
 * Seiten, die der aktuelle Nutzer über die Navigation erreicht (MVP-1082):
 * Seitenleiste, Haupt-, Verwaltungs-, System- und Benutzermenü. Modul und
 * Recht wie im Funktionskatalog; Per-User-Ausblendungen und Arbeitsbereich
 * gelten bewusst nicht, damit Ausgeblendetes auffindbar bleibt.
 */
final class NavigationPages {
    public function __construct(private readonly NavigationRegistry $registry) {}

    /** @return list<array{route: string, label: string, icon: string, area: string, url: string}> */
    public function forCurrentUser(): array {
        $pages = [];
        $add = static function (mixed $item, string $area) use (&$pages): void {
            if (! is_array($item)) {
                return;
            }
            $route = (string) ($item['route'] ?? '');
            if ($route === '' || isset($pages[$route]) || ! Route::has($route)) {
                return;
            }
            try {
                $url = route($route, (array) ($item['route_params'] ?? []));
            } catch (UrlGenerationException) {
                return;
            }
            $pages[$route] = [
                'route' => $route,
                'label' => (string) ($item['label'] ?? ''),
                'icon' => (string) ($item['icon'] ?? 'arrow_forward'),
                'area' => $area,
                'url' => $url,
            ];
        };

        foreach ($this->registry->filterSidebar($this->registry->sidebarBlueprint('duties.index')) as $section) {
            $sectionLabel = (string) ($section['label'] ?? '');
            foreach ((array) ($section['items'] ?? []) as $item) {
                $add($item, $sectionLabel);
            }
            foreach ((array) ($section['groups'] ?? []) as $group) {
                foreach ((array) ($group['items'] ?? []) as $item) {
                    $add($item, trim($sectionLabel . ' › ' . (string) ($group['label'] ?? ''), ' ›'));
                }
            }
        }

        $menus = $this->registry->build(false, 'duties.index');
        foreach ($menus['mainNavItems'] as $item) {
            $add($item, (string) __('Hauptnavigation'));
        }
        foreach ($menus['manageNavItems'] as $item) {
            $add($item, (string) __('Verwaltung'));
        }
        foreach ($menus['adminNavItems'] as $item) {
            $add($item, (string) __('System'));
        }
        foreach ($menus['userNavItems'] as $item) {
            if (! empty($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as $child) {
                    $add($child, __('Benutzermenü') . ' › ' . (string) ($item['label'] ?? ''));
                }
                continue;
            }
            $add($item, (string) __('Benutzermenü'));
        }

        return array_values($pages);
    }
}
