<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullInspectionTourPlanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance\Contracts;

use App\Models\Diary\Tour;
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleUnavailableException;
use Carbon\CarbonInterface;

final class NullInspectionTourPlanner implements InspectionTourPlanner {
    public function available(Organization $organization): bool {
        return false;
    }

    public function planTour(User $driver, CarbonInterface $date, array $orderIds): Tour {
        throw ModuleUnavailableException::for('module.planung');
    }
}
