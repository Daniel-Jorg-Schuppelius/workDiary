<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Contracts;

use App\Models\Travel\TravelLog;

/**
 * Fahrtenbuch-Anbindung der Tourenplanung (MVP-863): definiert von der
 * Disposition, gebunden vom Reisekostenmodul ({@see \App\Services\Travel\TravelLogService}).
 * Null-Bindung: kein Fahrtenbuch — Touren werden nicht materialisiert.
 */
interface TravelLogRecorder {
    public function available(): bool;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TravelLog;
}
