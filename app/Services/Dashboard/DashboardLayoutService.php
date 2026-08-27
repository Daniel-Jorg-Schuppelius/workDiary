<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardLayoutService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Dashboard\{Widget, WidgetRegistry};
use App\Enums\Dashboard\WidgetWidth;
use App\Models\{Organization, User, UserDashboardWidget};
use App\Support\Dashboard\DashboardLayoutItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Löst das Kachel-Layout eines Nutzers auf und schreibt es zurück.
 *
 * Drei Ebenen, in dieser Reihenfolge (die erste, die eine Kachel kennt,
 * gewinnt):
 *   1. Nutzerwahl        — user_dashboard_widgets
 *   2. Org-Vorgabe       — organizations.settings['dashboard']['default_layout']
 *   3. Klassen-Vorgabe   — Widget::defaultWidth(), sichtbar, Sortierung ans Ende
 *
 * Kacheln ohne gespeicherten Eintrag erscheinen dadurch automatisch, sobald
 * sie registriert werden — neue Kacheln bleiben bei bestehenden Nutzern nicht
 * unsichtbar. Die Breite bleibt bewusst NULL, solange sie niemand angefasst
 * hat, damit spätere Änderungen an der Klassen-Vorgabe durchschlagen.
 */
class DashboardLayoutService {
    public const SETTINGS_GROUP = 'dashboard';

    public const SETTINGS_KEY = 'default_layout';

    public const SETTINGS_TABS_KEY = 'tabs';

    /** Präferenz-Schlüssel der nutzereigenen Bereichsliste. */
    public const PREFERENCE_TABS_KEY = 'dashboard_tabs';

    /** Obergrenze für Bereiche — mehr wird zur unübersichtlichen Leiste. */
    public const MAX_TABS = 8;

    public function __construct(private readonly WidgetRegistry $registry) {}

    /**
     * Alle für den Nutzer verfügbaren Kacheln inklusive aufgelöstem Layout,
     * sortiert nach Position und Label.
     *
     * @return Collection<int, DashboardLayoutItem>
     */
    public function resolveFor(User $user): Collection {
        /** @var Collection<string, UserDashboardWidget> $stored */
        $stored = $user->dashboardWidgets()->get()->keyBy('widget_key');
        $orgDefaults = $this->orgDefaults($user->organization);

        return $this->registry->availableFor($user)
            ->map(function (Widget $widget) use ($stored, $orgDefaults): DashboardLayoutItem {
                $row = $stored->get($widget->key());
                $orgRow = $orgDefaults[$widget->key()] ?? null;

                if ($row !== null) {
                    return new DashboardLayoutItem(
                        widget: $widget,
                        sortOrder: $row->sort_order,
                        hidden: $row->hidden,
                        width: $row->width ?? $widget->defaultWidth(),
                        tabKey: $row->tab_key,
                        source: 'user',
                    );
                }

                if ($orgRow !== null) {
                    return new DashboardLayoutItem(
                        widget: $widget,
                        sortOrder: (int) ($orgRow['sort_order'] ?? $widget->defaultOrder()),
                        hidden: (bool) ($orgRow['hidden'] ?? false),
                        width: WidgetWidth::tryFromValue($orgRow['width'] ?? null) ?? $widget->defaultWidth(),
                        tabKey: is_string($orgRow['tab'] ?? null) ? $orgRow['tab'] : null,
                        source: 'organization',
                    );
                }

                return new DashboardLayoutItem(
                    widget: $widget,
                    sortOrder: $widget->defaultOrder(),
                    hidden: $widget->defaultHidden(),
                    width: $widget->defaultWidth(),
                    tabKey: null,
                    source: 'default',
                );
            })
            ->sortBy(fn (DashboardLayoutItem $i) => [$i->sortOrder, $i->widget->label()])
            ->values();
    }

    /**
     * Nur die Kacheln, die das Dashboard tatsächlich rendert.
     *
     * @return Collection<int, DashboardLayoutItem>
     */
    public function visibleFor(User $user): Collection {
        return $this->resolveFor($user)
            ->reject(fn (DashboardLayoutItem $i) => $i->hidden)
            ->values();
    }

    /**
     * Schreibt die Nutzerwahl. Unbekannte Kachel-Schlüssel werden verworfen,
     * damit ein manipuliertes Formular keine Fremdschlüssel einträgt.
     *
     * @param list<array{key:string,hidden?:mixed,width?:?string,tab?:?string}> $rows
     * @param list<array{key:string,label:string,icon?:?string}>|null $tabs Bereichsliste; null lässt die bestehende unangetastet
     */
    public function saveForUser(User $user, array $rows, ?array $tabs = null): void {
        $allowed = $this->registry->availableFor($user)->keys()->all();

        if ($tabs !== null) {
            $tabs = $this->normalizeTabs($tabs);
            $user->setPreference(self::PREFERENCE_TABS_KEY, $tabs);
        }

        $tabKeys = array_column($tabs ?? $this->tabsFor($user), 'key');

        DB::transaction(function () use ($user, $rows, $allowed, $tabKeys): void {
            $user->dashboardWidgets()->delete();
            $sort = 0;
            foreach ($rows as $row) {
                if (! in_array($row['key'], $allowed, true)) {
                    continue;
                }
                UserDashboardWidget::create([
                    'user_id' => $user->id,
                    'widget_key' => $row['key'],
                    'sort_order' => $sort++,
                    'width' => WidgetWidth::tryFromValue($row['width'] ?? null),
                    'tab_key' => $this->normalizeTab($row['tab'] ?? null, $tabKeys),
                    'hidden' => (bool) ($row['hidden'] ?? false),
                ]);
            }
        });
    }

    /**
     * Verwirft die Nutzerwahl — Kacheln UND eigene Bereiche; danach greift
     * wieder die Org-Vorgabe. Nur die Kacheln zu löschen würde eine
     * Bereichsleiste ohne zugeordnete Kacheln stehen lassen.
     */
    public function resetUser(User $user): void {
        $user->dashboardWidgets()->delete();
        $user->setPreference(self::PREFERENCE_TABS_KEY, []);
    }

    /**
     * Speichert die Org-Vorgabe. Anders als bei der Nutzerwahl bleiben die
     * Werte in den Org-Einstellungen (kein eigener Datensatz je Nutzer):
     * Nutzer ohne eigene Wahl folgen ihr sofort.
     *
     * @param list<array{key:string,hidden?:mixed,width?:?string,tab?:?string}> $rows
     * @param list<array{key:string,label:string,icon?:?string}>|null $tabs
     */
    public function saveOrgDefault(Organization $organization, array $rows, ?array $tabs = null): void {
        $allowed = $this->registry->all()->keys()->all();
        $tabs = $tabs === null ? null : $this->normalizeTabs($tabs);
        $tabKeys = array_column($tabs ?? $this->orgTabs($organization), 'key');

        $layout = [];
        $sort = 0;
        foreach ($rows as $row) {
            if (! in_array($row['key'], $allowed, true)) {
                continue;
            }
            $layout[$row['key']] = [
                'sort_order' => $sort++,
                'hidden' => (bool) ($row['hidden'] ?? false),
                'width' => WidgetWidth::tryFromValue($row['width'] ?? null)?->value,
                'tab' => $this->normalizeTab($row['tab'] ?? null, $tabKeys),
            ];
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $group = is_array($settings[self::SETTINGS_GROUP] ?? null) ? $settings[self::SETTINGS_GROUP] : [];
        $group[self::SETTINGS_KEY] = $layout;
        if ($tabs !== null) {
            $group[self::SETTINGS_TABS_KEY] = $tabs;
        }
        $settings[self::SETTINGS_GROUP] = $group;

        $organization->settings = $settings;
        $organization->save();
    }

    /**
     * Org-Vorgabe als Kachel-Schlüssel → Layout-Zeile.
     *
     * @return array<string, array{sort_order?:int,hidden?:bool,width?:?string,tab?:?string}>
     */
    public function orgDefaults(?Organization $organization): array {
        if ($organization === null) {
            return [];
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $group = is_array($settings[self::SETTINGS_GROUP] ?? null) ? $settings[self::SETTINGS_GROUP] : [];
        $layout = is_array($group[self::SETTINGS_KEY] ?? null) ? $group[self::SETTINGS_KEY] : [];

        /** @var array<string, array{sort_order?:int,hidden?:bool,width?:?string,tab?:?string}> $filtered */
        $filtered = array_filter($layout, static fn ($row) => is_array($row));

        return $filtered;
    }

    public function hasOrgDefault(?Organization $organization): bool {
        return $this->orgDefaults($organization) !== [];
    }

    /**
     * Bereiche des Nutzers: eigene Liste, sonst die der Organisation, sonst
     * keine (dann rendert das Dashboard eine einzige Fläche).
     *
     * @return list<array{key:string,label:string,icon:?string}>
     */
    public function tabsFor(User $user): array {
        $own = $user->getPreference(self::PREFERENCE_TABS_KEY);
        if (is_array($own) && $own !== []) {
            return $this->normalizeTabs($own);
        }

        return $this->orgTabs($user->organization);
    }

    /**
     * Bereiche der Organisation.
     *
     * @return list<array{key:string,label:string,icon:?string}>
     */
    public function orgTabs(?Organization $organization): array {
        if ($organization === null) {
            return [];
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $group = is_array($settings[self::SETTINGS_GROUP] ?? null) ? $settings[self::SETTINGS_GROUP] : [];
        $tabs = $group[self::SETTINGS_TABS_KEY] ?? null;

        return is_array($tabs) ? $this->normalizeTabs($tabs) : [];
    }

    public function hasOwnTabs(User $user): bool {
        $own = $user->getPreference(self::PREFERENCE_TABS_KEY);

        return is_array($own) && $own !== [];
    }

    /**
     * Bringt eine Bereichsliste in Form: nur saubere Schlüssel, nicht-leere
     * Beschriftungen, keine Dubletten, begrenzte Anzahl. Die Schlüssel landen
     * in Alpine-Attributen und dürfen deshalb nichts außer [a-z0-9-] tragen;
     * das Symbol ist ein Material-Symbol-Name (snake_case) und wird ebenso
     * eng gefasst — beides wandert auch in die Org-Einstellungen und wird
     * dort von späteren Lesern ungeprüft gerendert.
     *
     * @param  array<int|string, mixed>  $tabs
     * @return list<array{key:string,label:string,icon:?string}>
     */
    public function normalizeTabs(array $tabs): array {
        $out = [];
        $seen = [];

        foreach ($tabs as $tab) {
            if (! is_array($tab)) {
                continue;
            }
            $key = is_string($tab['key'] ?? null) ? strtolower(trim($tab['key'])) : '';
            $label = is_string($tab['label'] ?? null) ? trim($tab['label']) : '';
            if ($label === '' || preg_match('/^[a-z0-9-]{1,40}$/', $key) !== 1 || in_array($key, $seen, true)) {
                continue;
            }
            $icon = is_string($tab['icon'] ?? null) ? strtolower(trim($tab['icon'])) : '';
            $seen[] = $key;
            $out[] = [
                'key' => $key,
                'label' => mb_substr($label, 0, 40),
                'icon' => preg_match('/^[a-z0-9_]{1,40}$/', $icon) === 1 ? $icon : null,
            ];

            if (count($out) >= self::MAX_TABS) {
                break;
            }
        }

        return $out;
    }

    /**
     * Hält die Kachel-Zuordnung an den bekannten Bereichen fest. Alles andere
     * wird NULL — und NULL heißt „immer sichtbar", also über der
     * Bereichsleiste. Eine Kachel, deren Bereich gelöscht wurde, verschwindet
     * dadurch nie, sondern rutscht nach oben.
     *
     * @param  list<string>  $tabKeys
     */
    private function normalizeTab(?string $tab, array $tabKeys): ?string {
        return $tab !== null && in_array($tab, $tabKeys, true) ? $tab : null;
    }
}
