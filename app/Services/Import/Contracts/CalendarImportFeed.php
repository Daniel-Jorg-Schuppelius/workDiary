<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarImportFeed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Contracts;

use App\Models\Platform\Organization;
use DateTimeImmutable;

/**
 * Kalenderverbindung als Quelle des Zeitimports (MVP-976, Erweiterungspunkt):
 * liefert die Termine eines Zeitraums als iCalendar-Dokument, das denselben
 * Weg nimmt wie eine hochgeladene `.ics`-Datei.
 */
interface CalendarImportFeed {
    /** Kennung der Quelle, z. B. `caldav`. */
    public function key(): string;

    public function label(): string;

    /**
     * Verbindungen der Organisation, aus denen gelesen werden darf.
     *
     * @return list<array{id: string, label: string}>
     */
    public function connections(Organization $organization): array;

    /** @throws \RuntimeException wenn der Kalender nicht abrufbar ist (ohne Zugangsdaten in der Meldung) */
    public function fetch(Organization $organization, string $connectionId, DateTimeImmutable $from, DateTimeImmutable $until): string;
}
