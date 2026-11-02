<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleCacheStampObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Services\Reselling\Register\ResaleCacheStamp;
use Illuminate\Database\Eloquent\Model;

/**
 * Zählt den Org-Stempel der Reselling-Caches hoch, sobald sich Abos, Perioden,
 * Bezüge, Vorlagen-Zuordnungen, ignorierte Positionen, Aufteilungen oder
 * Lizenzverkäufe ändern (Leistungsanalyse 2026-11-02). Läufe ohne Modell-Events
 * (Vorschlagslauf) und die Spiegel-Syncs der Plugins stempeln selbst.
 */
class ResaleCacheStampObserver {
    public function saved(Model $model): void {
        $this->bump($model);
    }

    public function deleted(Model $model): void {
        $this->bump($model);
    }

    private function bump(Model $model): void {
        $organizationId = $model->getAttribute('organization_id');
        if (is_numeric($organizationId)) {
            ResaleCacheStamp::bump((int) $organizationId);
        }
    }
}
