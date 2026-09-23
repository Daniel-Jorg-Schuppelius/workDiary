<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Klassifikationen, Tags, Branchenprofile“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ClassificationManifest extends Manifest {
    public function code(): string {
        return 'classification';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Klassifikationen, Tags, Branchenprofile';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Classification',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'activity_categories',
            'classifiables',
            'classification_requirements',
            'classifications',
            'entry_types',
            'taggables',
            'tags',
        ];
    }
}
