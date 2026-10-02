<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffProgressRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Takeoff\Contracts;

use App\Models\Gaeb\BoqItem;

/**
 * Gemessene Menge als Leistungsstand einer LV-Position (MVP-1059): definiert
 * vom Aufmaß, gebunden vom Bau-Modul ({@see \App\Services\Gaeb\BoqProgressService}).
 * Null-Bindung wirft {@see \App\Modules\ModuleUnavailableException}.
 */
interface TakeoffProgressRecorder {
    /** @param  string  $quantity  Dezimaltext mit Punkt */
    public function recordMeasured(BoqItem $item, string $quantity, ?int $diaryEntryId, string $note, ?int $actorId): void;
}
