<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Hinweisgebersystem“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class WhistleblowingManifest extends Manifest {
    public function code(): string {
        return 'whistleblowing';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Hinweisgebersystem';
    }

    public function licenseCode(): string {
        return 'module.compliance';
    }

    public function description(): string {
        return 'Hinweisgebersystem (HinSchG).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Whistleblowing',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'whistleblowing_attachments',
            'whistleblowing_case_assignments',
            'whistleblowing_case_conflicts',
            'whistleblowing_case_events',
            'whistleblowing_case_subjects',
            'whistleblowing_case_tombstones',
            'whistleblowing_cases',
            'whistleblowing_deadline_reminders',
            'whistleblowing_emergency_grants',
            'whistleblowing_messages',
            'whistleblowing_portals',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'whistleblowing.internal.*',
            'whistleblowing.portal.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'compliance',
            ],
            'items' => [],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            \App\Events\Platform\OrganizationCreated::class => [
                \App\Listeners\Whistleblowing\SeedWhistleblowingRole::class,
            ],
        ];
    }
}
