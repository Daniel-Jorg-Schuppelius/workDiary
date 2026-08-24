<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAssessmentSnapshotObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Services\Isms\AssessmentSnapshotService;
use Illuminate\Database\Eloquent\Model;

/**
 * Stichtags-Rekonstruktion (Nachtrag 046b; B4 aus dem Provider gezogen):
 * jede Bewertungsänderung (SoA-Aussage, Norm-Konformitätsstatus) erzeugt
 * einen append-only Snapshot — Model-Events, damit auch Service-Updates
 * erfasst werden. Registriert auf IsmsApplicabilityStatement + IsmsNormStatus.
 */
class IsmsAssessmentSnapshotObserver {
    public function __construct(private readonly AssessmentSnapshotService $snapshots) {}

    public function saved(Model $model): void {
        $this->snapshots->record($model);
    }
}
