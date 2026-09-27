<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullTrainingNeedSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Contracts;

use App\Models\Platform\Organization;

final class NullTrainingNeedSource implements TrainingNeedSource {
    public function trainingNeeds(Organization $organization): array {
        return [];
    }
}
