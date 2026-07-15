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
    'subtitle' => 'Actions captured offline on this device — pending or rejected.',
    'notice' => 'This list is stored on this device only. Pending entries sync automatically once a connection is available; rejected entries can be re-applied or discarded.',
    'empty' => 'No offline changes on this device.',
    'section' => [
        'pending' => 'Pending',
        'rejected' => 'Rejected',
    ],
    'type' => [
        'clock_in' => 'Clock in',
        'clock_out' => 'Clock out',
        'comment' => 'Order comment',
        'form' => 'Form',
    ],
    'action' => [
        'retry' => 'Apply again',
        'discard' => 'Discard',
    ],
];
