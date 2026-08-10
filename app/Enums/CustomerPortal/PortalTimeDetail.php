<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalTimeDetail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\CustomerPortal;

/**
 * Detailtiefe der Projektzeiten im Kundenportal (MVP-511). Interne Kosten,
 * Lohnwerte, Kalkulationssätze, Integrationsdaten, Tags und interne
 * Kommentare sind unabhängig von der Stufe NIE Portalinhalt.
 */
enum PortalTimeDetail: string {
    /** Keine Zeiten sichtbar. */
    case None = 'none';

    /** Nur Summen der freigegebenen Zeiten (je Monat/Projekt). */
    case Summary = 'summary';

    /** Einzeleinträge: Datum, Dauer, Projekt, Mitarbeitername. */
    case Entries = 'entries';

    /** Zusätzlich die Beschreibung — nur für veröffentlichte Einträge. */
    case EntriesWithDescription = 'entries_with_description';

    public function label(): string {
        return (string) match ($this) {
            self::None => __('Keine Zeiten'),
            self::Summary => __('Nur Summen'),
            self::Entries => __('Einträge (Datum, Dauer, Projekt, Mitarbeiter)'),
            self::EntriesWithDescription => __('Einträge inkl. Beschreibung (nur veröffentlichte)'),
        };
    }

    public function showsEntries(): bool {
        return $this === self::Entries || $this === self::EntriesWithDescription;
    }
}
