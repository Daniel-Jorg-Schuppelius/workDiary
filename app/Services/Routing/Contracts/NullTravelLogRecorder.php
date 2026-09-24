<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullTravelLogRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Contracts;

use App\Models\Travel\TravelLog;
use App\Modules\ModuleUnavailableException;

final class NullTravelLogRecorder implements TravelLogRecorder {
    public function available(): bool {
        return false;
    }

    public function create(array $attributes): TravelLog {
        throw ModuleUnavailableException::for('travel');
    }
}
