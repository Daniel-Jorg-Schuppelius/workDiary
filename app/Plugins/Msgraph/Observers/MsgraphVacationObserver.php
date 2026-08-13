<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Filename     : MsgraphVacationObserver.php
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Observers;

use App\Enums\Vacation\VacationStatus;
use App\Models\Vacation;
use App\Plugins\Msgraph\Services\MsgraphOutOfOfficeService;

/**
 * Setzt bei final genehmigtem Urlaub die Outlook-Abwesenheitsnotiz
 * (Feature-103-Delta) — Opt-in je Organisation, Fehler laufen still ins Log.
 */
class MsgraphVacationObserver {
    public function updated(Vacation $vacation): void {
        if (! $vacation->wasChanged('status') || $vacation->status !== VacationStatus::Approved) {
            return;
        }

        app(MsgraphOutOfOfficeService::class)->applyForVacation($vacation);
    }
}
