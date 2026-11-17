<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardCockpitList.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CarryForward;

use App\Models\CarryForward\CarryForwardNote;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Contracts\View\View;

/**
 * Offene Merker im Cockpit von Kunde oder Asset (Feature 201, MVP-1646): die
 * gemeinsame Liste `cockpit._list` für die Panels der Träger-Module. Das Panel
 * des Moduls prüft Modul und Recht, nennt seine Reihen samt Bezeichnung und
 * Ziel-URL; diese Klasse zählt und baut die Zeilen. Ohne offene Merker kein Panel.
 */
final class CarryForwardCockpitList {
    private const int LIMIT = 10;

    public function __construct(private readonly CarryForwardNotes $notes) {}

    /**
     * @param  array<string, array{label: string, url: string|null}>  $series  Reihenschlüssel → Bezeichnung und Ziel
     */
    public function render(int $organizationId, array $series, string $id, string $title): ?View {
        $open = $this->notes->openInSeries($organizationId, array_map('strval', array_keys($series)), self::LIMIT);
        if ($open['count'] === 0) {
            return null;
        }

        $rows = $open['notes']->map(static function (CarryForwardNote $note) use ($series): array {
            $target = $series[$note->series_key] ?? ['label' => null, 'url' => null];

            return [
                'title' => StringHelper::truncate($note->note, 120),
                'url' => $target['url'],
                'subtitle' => $target['label'],
                'badge' => null,
                'tone' => null,
                'date' => $note->period_key !== null ? (string) __('carry_forward.from_period', ['period' => $note->period_key->label()]) : null,
            ];
        })->all();

        return view('cockpit._list', [
            'id' => $id,
            'title' => $title,
            'icon' => 'push_pin',
            'count' => $open['count'],
            'rows' => $rows,
            'summary' => (string) __('carry_forward.cockpit.summary'),
        ]);
    }
}
