<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeSlotSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Contracts;

use App\Models\Platform\User;
use Carbon\CarbonImmutable;

/**
 * Freie Zeitfenster einer Person an einem Tag (MVP-863): definiert vom
 * Kalenderkern für die Online-Terminbuchung, gebunden von der Disposition
 * ({@see \App\Services\Dispatch\GapFillSuggester}). Null-Bindung: keine Fenster.
 */
interface FreeSlotSource {
    /** @return list<array{start: string, end: string, net_minutes: int}> Uhrzeiten als H:i des Tages */
    public function freeSlots(User $user, CarbonImmutable $date): array;
}
