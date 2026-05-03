<?php

namespace App\Observers;

use App\Models\EmergencyAssignment;
use App\Services\PushNotifier;

class EmergencyAssignmentObserver {
    public function created(EmergencyAssignment $assignment): void {
        $assignment->loadMissing('user');

        app(PushNotifier::class)->emergencyAssigned($assignment);
    }
}
