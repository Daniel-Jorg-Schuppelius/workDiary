<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Unterweisungen und Schulungen“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class TrainingManifest extends Manifest {
    public function code(): string {
        return 'training';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Unterweisungen und Schulungen';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Training',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'training_assignments',
            'training_course_versions',
            'training_courses',
            'training_requirements',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Training\DeadlineScans\TrainingDeadlineScan::class,
            ],
        ];
    }
}
