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
    'subtitle' => 'Actions captured offline — pending, conflicting or rejected.',
    'notice' => 'Pending and rejected entries live only on this device; pending ones are transferred automatically once a connection exists. Conflicts need your decision: the data changed since you captured it (for instance the clocking, or the day has been closed meanwhile) or a required field is missing. Open conflicts also appear here when they arose on another device.',
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
        'time_entry' => 'Project time',
        'material_usage' => 'Material',
        'learning_unit' => 'Learning unit completed',
        'inventory_count' => 'Stocktake scan',
        'tour_arrive' => 'Tour stop arrived',
        'tour_depart' => 'Tour stop departed',
        'patrol_scan' => 'Checkpoint',
        'takeoff_line' => 'Take-off line',
    ],
    'action' => [
        'retry' => 'Apply again',
        'discard' => 'Discard',
        'take_server' => 'Keep the other version',
        'force_local' => 'Send my version',
        'edit' => 'Edit',
        'send_edited' => 'Send edited',
        'cancel_edit' => 'Cancel',
    ],
    'conflict_hint' => 'Server state: :server',
    'photos_queued' => 'Photos queued: :count',
    // Konflikte entscheiden (MVP-1595).
    'field' => [
        'missing' => 'Missing',
        'lock' => 'Lock',
    ],
    'remote_hint' => 'Captured on another device — the contents are stored there. Here you can only discard the conflict.',
    'resolve_failed' => 'The decision could not be transferred. Please try again with a connection.',
    'unresolved' => 'open',
    'flash' => [
        'resolved' => 'Conflict resolved.',
        'not_found' => 'This conflict no longer exists.',
    ],
];
