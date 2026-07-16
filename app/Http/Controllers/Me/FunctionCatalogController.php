<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FunctionCatalogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Me;

use App\Enums\Licensing\ModuleStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Licensing\{ModuleCatalog, ModuleStatusResolver};
use App\Services\Navigation\{NavFocusService, NavigationRegistry};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Funktionskatalog „Alle Funktionen" (Feature 081): Registry-gespeiste Übersicht
 * aller Bereiche mit Zustand je Nutzer — sichtbar, selbst ausgeblendet,
 * org-deaktiviert oder nicht lizenziert (Upsell). Sicherheitsventil gegen
 * „Funktion verschwunden": reine Projektion, kein Persistenzbedarf.
 */
class FunctionCatalogController extends Controller {
    public function __construct(
        private readonly NavigationRegistry $registry,
        private readonly ModuleStatusResolver $status,
        private readonly ModuleCatalog $catalog,
        private readonly NavFocusService $focus,
    ) {}

    public function index(): View {
        /** @var User $user */
        $user = Auth::user();
        $organization = $user->organization;

        $hidden = $this->registry->hiddenNavKeys($user);
        $moduleBySection = $this->registry->moduleBySectionKey();
        $moduleByItem = $this->registry->moduleByItemRoute();
        $moduleByGroup = $this->registry->moduleByGroupKey();

        $moduleStatus = [];
        if ($organization !== null) {
            foreach ($this->status->forOrganization($organization) as $row) {
                $moduleStatus[$row['code']] = $row['status'];
            }
        }

        $statusOf = static function (?string $module) use ($moduleStatus): ?ModuleStatus {
            if ($module === null) {
                return null;
            }

            return $moduleStatus[$module] ?? ModuleStatus::NotLicensed;
        };

        // Aktiver Arbeitsbereich markiert Einträge, die der Fokus ausblendet — über
        // diesen Katalog bleiben sie auffindbar. `keepSet === null` = kein Filter.
        $activeFocus = $this->focus->resolveActive($user, $organization, session(NavFocusService::SESSION_KEY));
        $focusKeep = $this->focus->keepKeys($activeFocus);
        $keepSet = $focusKeep !== null ? array_flip($focusKeep) : null;

        $sections = [];
        foreach ($this->registry->sidebarBlueprint('duties.index') as $section) {
            $sectionKey = (string) $section['key'];
            $sectionModule = $moduleBySection[$sectionKey] ?? null;
            $sectionHidden = in_array(NavigationRegistry::KEY_SECTION . $sectionKey, $hidden, true);
            $sectionInFocus = $keepSet === null || isset($keepSet[NavigationRegistry::KEY_SECTION . $sectionKey]);

            $entries = [];
            $collect = function (array $items, ?string $groupKey, ?string $groupModule, bool $groupHidden) use (&$entries, $moduleByItem, $sectionModule, $sectionHidden, $hidden, $statusOf, $keepSet, $sectionInFocus): void {
                $groupInFocus = $sectionInFocus
                    || ($groupKey !== null && isset($keepSet[NavigationRegistry::KEY_GROUP . $groupKey]));
                foreach ($items as $item) {
                    if (! is_array($item) || ! $this->registry->mayAccessRoute((string) $item['route'])) {
                        continue;
                    }
                    $module = $moduleByItem[(string) $item['route']] ?? $groupModule ?? $sectionModule;
                    $status = $statusOf($module);
                    $itemHidden = $sectionHidden || $groupHidden
                        || in_array(NavigationRegistry::KEY_ITEM . (string) $item['route'], $hidden, true);
                    $inFocus = $keepSet === null || $groupInFocus
                        || isset($keepSet[NavigationRegistry::KEY_ITEM . (string) $item['route']]);
                    $visible = ($status === null || $status === ModuleStatus::Active) && ! $itemHidden;

                    $entries[] = [
                        'route' => (string) $item['route'],
                        'label' => (string) $item['label'],
                        'icon' => (string) ($item['icon'] ?? 'circle'),
                        'key' => NavigationRegistry::KEY_ITEM . (string) $item['route'],
                        'module' => $module,
                        'module_label' => $module !== null ? $this->catalog->label($module) : null,
                        'module_description' => $module !== null ? $this->catalog->description($module) : null,
                        'status' => $status,
                        'hidden' => $itemHidden,
                        'visible' => $visible,
                        // sichtbar, aber vom aktiven Arbeitsbereich ausgeblendet
                        'in_focus_hidden' => $visible && ! $inFocus,
                    ];
                }
            };

            if (! empty($section['items']) && is_array($section['items'])) {
                $collect($section['items'], null, null, false);
            }
            foreach ((array) ($section['groups'] ?? []) as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $groupHidden = in_array(NavigationRegistry::KEY_GROUP . (string) $group['key'], $hidden, true);
                $collect((array) ($group['items'] ?? []), (string) $group['key'], $moduleByGroup[(string) $group['key']] ?? null, $groupHidden);
            }

            if ($entries !== []) {
                $sections[] = [
                    'key' => $sectionKey,
                    'label' => (string) $section['label'],
                    'hidden' => $sectionHidden,
                    'entries' => $entries,
                ];
            }
        }

        return view('me.functions', [
            'sections' => $sections,
            'canManageScope' => $user->can(Permission::OrganizationScopeManage->value),
            'focusActive' => $activeFocus !== 'all',
            'activeFocusLabel' => $this->focus->label($organization, $activeFocus),
        ]);
    }
}
