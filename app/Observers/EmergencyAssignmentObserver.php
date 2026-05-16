<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAssignmentObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\EmergencyAssignment;
use App\Services\PushNotifier;

class EmergencyAssignmentObserver {
    public function created(EmergencyAssignment $assignment): void {
        $assignment->loadMissing('user');

        app(PushNotifier::class)->emergencyAssigned($assignment);
    }
}
