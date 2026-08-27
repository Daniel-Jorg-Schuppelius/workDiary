<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardPresets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Dashboard\WidgetRegistry;
use App\Enums\Dashboard\WidgetWidth;

/**
 * Fertige Kachel-Anordnungen zum Übernehmen.
 *
 * Ein Preset beschreibt nur Bereiche und Kachel-Zuordnung; gespeichert wird
 * es über denselben Weg wie eine Handeingabe ({@see DashboardLayoutService}),
 * damit Rechte, Modul-Gating und Normalisierung identisch greifen. Kacheln,
 * die ein Preset nicht nennt, werden ausgeblendet — sonst hinge das Ergebnis
 * davon ab, was vorher eingeschaltet war.
 */
class DashboardPresets {
    public const CLASSIC = 'classic';

    public function __construct(private readonly WidgetRegistry $registry) {}

    /** @return list<string> */
    public function keys(): array {
        return [self::CLASSIC];
    }

    public function exists(string $key): bool {
        return in_array($key, $this->keys(), true);
    }

    public function label(string $key): string {
        return match ($key) {
            self::CLASSIC => (string) __('dashboard.preset.classic.label'),
            default => $key,
        };
    }

    public function description(string $key): string {
        return match ($key) {
            self::CLASSIC => (string) __('dashboard.preset.classic.description'),
            default => '',
        };
    }

    /**
     * Bereiche des Presets.
     *
     * @return list<array{key:string,label:string,icon:?string}>
     */
    public function tabs(string $key): array {
        return match ($key) {
            self::CLASSIC => [
                ['key' => 'tab-1', 'label' => (string) __('Überblick'), 'icon' => 'dashboard'],
                ['key' => 'tab-2', 'label' => (string) __('Aufgaben'), 'icon' => 'checklist'],
                ['key' => 'tab-3', 'label' => (string) __('Aktivität'), 'icon' => 'forum'],
                ['key' => 'tab-4', 'label' => (string) __('Finanzen & Reisen'), 'icon' => 'payments'],
            ],
            default => [],
        };
    }

    /**
     * Kachel-Zeilen in Speicher-Reihenfolge. Alle registrierten Kacheln, die
     * das Preset nicht nennt, kommen ausgeblendet hinterher.
     *
     * @return list<array{key:string,hidden:bool,width:?string,tab:?string}>
     */
    public function widgets(string $key): array {
        $plan = match ($key) {
            // Nachbau des Dashboards vor dem Kachel-Umbau: Lesezeichen-Streifen,
            // Onboarding und KPI-Zeile standen über den Registerkarten (hier:
            // ohne Bereich = immer sichtbar), darunter die vier Karten.
            // Die Stempeluhr ist die eine Ergänzung — sie kam mit dem Umbau
            // dazu und ist ausdrücklich gewünscht.
            self::CLASSIC => [
                ['bookmarks', null, null],
                ['data-protection', null, null],
                ['operations-tasks', null, null],
                ['onboarding', null, WidgetWidth::Full],
                ['personal-kpis', null, WidgetWidth::Full],
                ['attendance-clock', null, null],

                ['team-kpis', 'tab-1', WidgetWidth::Full],
                ['today-shifts', 'tab-1', null],
                ['upcoming-shifts', 'tab-1', null],
                ['recent-emergencies', 'tab-1', null],
                ['scheduled-shifts', 'tab-1', null],

                ['open-issues', 'tab-2', null],
                ['recent-entries', 'tab-2', null],

                ['recent-comments', 'tab-3', null],
                ['recent-attachments', 'tab-3', null],
                ['team-activity', 'tab-3', WidgetWidth::Full],

                ['finance', 'tab-4', WidgetWidth::Full],
                ['vacation-flex', 'tab-4', null],
            ],
            default => [],
        };

        $rows = [];
        $used = [];
        foreach ($plan as [$widgetKey, $tab, $width]) {
            if ($this->registry->find($widgetKey) === null) {
                continue;
            }
            $used[] = $widgetKey;
            $rows[] = [
                'key' => $widgetKey,
                'hidden' => false,
                'width' => $width?->value,
                'tab' => $tab,
            ];
        }

        foreach ($this->registry->all() as $widget) {
            if (in_array($widget->key(), $used, true)) {
                continue;
            }
            $rows[] = [
                'key' => $widget->key(),
                'hidden' => true,
                'width' => null,
                'tab' => null,
            ];
        }

        return $rows;
    }
}
