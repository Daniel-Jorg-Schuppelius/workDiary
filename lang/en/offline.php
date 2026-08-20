<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Offline-Sync (Feature 035): Offline-Änderungs-Seite + Statusanzeige.
return [
    'title' => 'Offline changes',
    'subtitle' => 'Actions captured offline on this device — pending, conflicting or rejected.',
    'notice' => 'This list lives only on this device. Pending entries are transferred automatically once a connection exists; rejected entries can be retried or discarded. Conflicts need a decision: somebody else changed the same record.',
    'empty' => 'No offline changes on this device.',
    'section' => [
        'pending' => 'Pending',
        'rejected' => 'Rejected',
        'conflict' => 'Conflicts',
    ],
    'type' => [
        'clock_in' => 'Clock in',
        'clock_out' => 'Clock out',
        'comment' => 'Order comment',
        'form' => 'Form',
        'attendance_correct' => 'Attendance time correction',
    ],
    'action' => [
        'retry' => 'Apply again',
        'discard' => 'Discard',
        'take_server' => 'Keep the other version',
        'force_local' => 'Send my version',
    ],
    'conflict_hint' => 'Server state: :server',
    'photos_queued' => 'Photos queued: :count',
];
