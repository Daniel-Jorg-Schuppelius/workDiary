<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarEventSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Contracts;

use App\Models\Platform\User;
use Carbon\CarbonInterface;

/**
 * Erweiterungspunkt des Kalenders (MVP-1299): ein Modul steuert eigene Termine
 * bei, ohne dass der Kalender es kennt — eingetragen in `extensions()` des
 * Manifests. Sichtbarkeit und Rechte prüft die Quelle selbst.
 */
interface CalendarEventSource {
    /**
     * Termine im Zeitraum in der Form von {@see \App\Services\Calendar\CalendarEventService::events()}.
     *
     * @return list<array{id: string, title: string, start: string, end: string|null, color: string, url: string|null}>
     */
    public function events(CarbonInterface $start, CarbonInterface $end, User $user, bool $teamScope, ?int $filterUserId): array;
}
