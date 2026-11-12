<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time-correction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Auftrag einer Zeitbuchung per Korrekturantrag ändern (MVP-1552).
    'order' => [
        'label' => 'Change order',
        'unchanged' => '— unchanged —',
        'none' => '— no order —',
        'current' => 'current',
    ],
    // Eigene Stempelung korrigieren, auch offline (MVP-1595).
    'attendance' => [
        'title' => 'Correct clocking',
        'action' => 'Correct clocking',
        'submit' => 'Send correction',
        'intro' => 'The correction runs as a request through approval; if your organisation allows self-correction, it applies immediately. Without a connection it is stored on the device and transferred on the next contact.',
        'span' => 'Start and end',
        'reason' => 'Reason',
        'reason_placeholder' => 'At least :min characters, e.g. “forgot to clock out”',
        'changed' => 'The clocking has been changed meanwhile — please open the dialog again.',
        'applied' => 'Clocking corrected.',
        'submitted' => 'Correction submitted for approval.',
    ],
];
