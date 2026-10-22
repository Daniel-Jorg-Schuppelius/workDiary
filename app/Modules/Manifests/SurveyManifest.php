<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Umfragen“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class SurveyManifest extends Manifest {
    public function code(): string {
        return 'survey';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Umfragen';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Survey',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'survey_answers',
            'survey_invitations',
            'survey_questions',
            'survey_responses',
            'surveys',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Survey\Demo\SurveyDemoBlock::class,
            ],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            // Umfrage nach Auftragsabschluss (MVP-1293).
            \App\Events\Diary\OrderCompleted::class => [
                \App\Listeners\Survey\InviteSurveyOnOrderCompleted::class,
            ],
        ];
    }
}
