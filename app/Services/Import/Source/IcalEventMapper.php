<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalEventMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source;

use App\Services\Import\Source\Ical\IcalEvent;

/**
 * Bildet ein extrahiertes {@see IcalEvent} auf die kanonischen Spalten **einer**
 * Ziel-Entität ab (MVP-438). So bleibt der {@see IcalImportSource} entitäts-
 * neutral (VEVENT-Iteration, Zeitzone, Ganztags-/OOF-/Serien-Regeln) und die
 * {@see \App\Services\Import\EntitySpec} format-neutral (kennt kein iCal).
 */
interface IcalEventMapper {
    /**
     * Kanonische Feld-Map (keyed nach {@see \App\Services\Import\EntitySpec::columns()}).
     *
     * @return array<string, string>
     */
    public function toRow(IcalEvent $event): array;

    /**
     * Ob `TRANSP:TRANSPARENT`-Ereignisse (frei/Out-of-Office) übersprungen
     * werden. Für Stempelungen ja (keine Anwesenheit), für Projektzeiten nein.
     */
    public function skipsTransparent(): bool;
}
