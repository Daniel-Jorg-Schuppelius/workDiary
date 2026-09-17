<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StartPageResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Enums\User\UserRole;
use App\Models\{Organization, User};
use Illuminate\Support\Facades\Route;

/**
 * Startseite nach dem Login (MVP-799, Feature 037).
 *
 * Reihenfolge: persönliche Wahl im Profil → Vorgabe der Organisation für die
 * erste Rolle der Person in der Reihenfolge von `UserRole` → `null` (der
 * Aufrufer behält seinen bisherigen Standard). Eine Seite zählt nur, wenn die
 * Person sie auch öffnen darf — sonst landete sie nach dem Login auf einem 403.
 *
 * Gilt für die angemeldete Person: Die Menüprüfung liest die Navigation des
 * laufenden Requests (Organisation, Lizenz, Rechte).
 */
class StartPageResolver {
    /** Seiten ohne Menüeintrag, die jede Person im neuen System öffnen darf. */
    private const WITHOUT_MENU_CHECK = ['dashboard', 'diary.index'];

    /** Rollen, für die eine Organisation eine Startseite vorgeben kann (Kunden landen im Portal). */
    public const CONFIGURABLE_ROLES = [
        UserRole::Admin,
        UserRole::Geschaeftsfuehrung,
        UserRole::Personalverwaltung,
        UserRole::Teamleitung,
        UserRole::Buchhaltung,
        UserRole::User,
        UserRole::Aussendienst,
        UserRole::Callcenter,
        UserRole::Support,
        UserRole::TrainingManager,
    ];

    /** @var array<int, list<string>> Menü-Routen je Person, einmal pro Request */
    private array $menuRoutes = [];

    public function __construct(private readonly NavigationRegistry $navigation) {}

    /** Routenname der Startseite oder null, wenn weder Person noch Organisation etwas festgelegt haben. */
    public function routeFor(User $user): ?string {
        $personal = $user->getPreference('startpage');
        if (is_string($personal) && $this->accessible($user, $personal)) {
            return $personal;
        }

        $organization = $user->organization;
        if (! $organization instanceof Organization) {
            return null;
        }

        $roles = $user->getRoleNames()->all();
        foreach (self::CONFIGURABLE_ROLES as $role) {
            if (! in_array($role->value, $roles, true)) {
                continue;
            }
            $route = $this->roleDefault($organization, $role);
            if ($route !== null && $this->accessible($user, $route)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Auswahl für das Profil: nur Seiten, die die Person öffnen darf.
     *
     * @return array<string, string> Routenname → Beschriftung
     */
    public function optionsFor(User $user): array {
        return array_filter(
            self::labels(),
            fn (string $route): bool => $this->accessible($user, $route),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** Vorgabe der Organisation für eine Rolle (null = keine). */
    public function roleDefault(Organization $organization, UserRole $role): ?string {
        $value = data_get((array) ($organization->settings ?? []), self::settingKey($role));

        return is_string($value) && array_key_exists($value, self::labels()) ? $value : null;
    }

    public static function settingKey(UserRole $role): string {
        return 'personalization.startpage_roles.' . $role->value;
    }

    /**
     * Alle wählbaren Startseiten mit übersetzter Beschriftung.
     *
     * @return array<string, string>
     */
    public static function labels(): array {
        $labels = [];
        foreach ((array) config('personalization.startpages', []) as $route => $label) {
            if (is_string($route) && Route::has($route)) {
                $labels[$route] = (string) __((string) $label);
            }
        }

        return $labels;
    }

    /**
     * Optionen für die Settings-Registry (`options_from`).
     *
     * @return list<string>
     */
    public static function routeOptions(): array {
        return array_values(array_filter(array_keys((array) config('personalization.startpages', [])), 'is_string'));
    }

    private function accessible(User $user, string $route): bool {
        if (! array_key_exists($route, self::labels())) {
            return false;
        }

        return in_array($route, self::WITHOUT_MENU_CHECK, true) || in_array($route, $this->menuRoutesFor($user), true);
    }

    /**
     * Routen, die die Person im Menü sieht — Kopfleiste und Seitenleiste ohne
     * persönliche Ausblendungen (ausgeblendet heißt nicht gesperrt).
     *
     * @return list<string>
     */
    private function menuRoutesFor(User $user): array {
        if (isset($this->menuRoutes[$user->id])) {
            return $this->menuRoutes[$user->id];
        }

        $routes = [];
        $collect = static function (array $items) use (&$routes): void {
            foreach ($items as $item) {
                if (is_array($item) && isset($item['route']) && is_string($item['route'])) {
                    $routes[] = $item['route'];
                }
            }
        };

        $collect($this->navigation->build(false, 'duties.index')['mainNavItems']);
        foreach ($this->navigation->filterSidebar($this->navigation->sidebarBlueprint('duties.index')) as $section) {
            $collect((array) ($section['items'] ?? []));
            foreach ((array) ($section['groups'] ?? []) as $group) {
                $collect((array) ($group['items'] ?? []));
            }
        }

        return $this->menuRoutes[$user->id] = array_values(array_unique($routes));
    }
}
