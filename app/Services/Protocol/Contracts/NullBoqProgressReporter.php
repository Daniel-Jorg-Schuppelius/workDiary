<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullBoqProgressReporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Contracts;

use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Modules\ModuleUnavailableException;

final class NullBoqProgressReporter implements BoqProgressReporter {
    public function positionsFor(Protocol $protocol, User $user): array {
        return [];
    }

    public function report(Protocol $protocol, string $position, string $quantity, ?string $note, User $actor): void {
        throw ModuleUnavailableException::for('module.bau');
    }
}
