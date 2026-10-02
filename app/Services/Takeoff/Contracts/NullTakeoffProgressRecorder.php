<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullTakeoffProgressRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Takeoff\Contracts;

use App\Models\Gaeb\BoqItem;
use App\Modules\ModuleUnavailableException;

final class NullTakeoffProgressRecorder implements TakeoffProgressRecorder {
    public function recordMeasured(BoqItem $item, string $quantity, ?int $diaryEntryId, string $note, ?int $actorId): void {
        throw ModuleUnavailableException::for('gaeb');
    }
}
