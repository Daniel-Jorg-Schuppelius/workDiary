<?php
/*
 * Created on   : Sun Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasCommunicationNotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\CommunicationNote;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Kommunikationsnotizen-Träger (Feature 030, Spec §5). Vollaudit 2026-07
 * (M12): ersetzt die zuvor kopierte Relation auf DiaryEntry/Customer/Project
 * und öffnet die Bezüge Protocol/Asset ohne weitere Kopien.
 */
trait HasCommunicationNotes {
    /** @return MorphMany<CommunicationNote, $this> */
    public function communicationNotes(): MorphMany {
        /** @var MorphMany<CommunicationNote, $this> $relation */
        $relation = $this->morphMany(CommunicationNote::class, 'notable')->latest('occurred_at');

        return $relation;
    }
}
