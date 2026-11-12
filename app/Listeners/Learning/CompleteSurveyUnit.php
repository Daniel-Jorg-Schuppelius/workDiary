<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompleteSurveyUnit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Learning;

use App\Events\Survey\SurveyResponseSubmitted;
use App\Listeners\ModuleListener;
use App\Services\Learning\LearningUnitLinkService;

/** Umfrage-Einheit (MVP-1600): der abgesendete Fragebogen schließt sie ab. */
final class CompleteSurveyUnit extends ModuleListener {
    public function __construct(private readonly LearningUnitLinkService $links) {}

    protected function module(): string {
        return 'learning';
    }

    public function handle(SurveyResponseSubmitted $event): void {
        if (! $this->shouldHandle($event->invitation->organization_id)) {
            return;
        }
        $this->links->completeBySurveyInvitation($event->invitation);
    }
}
