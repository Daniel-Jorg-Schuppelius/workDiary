<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomBlockingSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Event\Contracts;

use App\Models\Facility\Room;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Erweiterungspunkt der Raumbuchung (MVP-863): Ein Modul meldet Termine, die
 * einen Raum über eigene Ressourcen blockieren (Verein: Sportstätten) —
 * keine isolierten Kalender. Registrierung über `Manifest::extensions()`.
 */
interface RoomBlockingSource {
    /** @return Collection<int, \App\Models\Calendar\Event> */
    public function eventsBlockingRoom(Room $room, CarbonInterface $blockStart, CarbonInterface $blockEnd, ?int $ignoreEventId = null): Collection;
}
