<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileOffboardingStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Hr\Offboarding;

use App\Models\Platform\User;
use App\Services\Hr\PersonnelFileService;
use App\Services\Org\Contracts\OffboardingStep;

/** Personalakte (Feature 141): beim Austritt das Aufbewahrungsende je Dokument setzen. */
final class PersonnelFileOffboardingStep implements OffboardingStep {
    public function __construct(private readonly PersonnelFileService $files) {}

    public function blockers(User $member): array {
        return [];
    }

    public function onExit(User $member): void {
        $this->files->applyRetentionOnExit($member);
    }
}
