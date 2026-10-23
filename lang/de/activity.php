<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : activity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Aktivitäts-Feed (Feature 183).
return [
    'title' => 'Aktivität',
    'subtitle' => 'Wer hat wann was geändert · :range',
    'subject_title' => 'Änderungsverlauf',
    'more' => 'Ältere Einträge sind im Audit-Protokoll.',
    'more_in_audit' => 'Ältere Einträge im Audit-Protokoll',
    'newer' => 'Neuere',
    'older' => 'Ältere',
    'filter' => [
        'type' => 'Objektart',
        'all_types' => 'Alle Objektarten',
        'user' => 'Person',
        'all_users' => 'Alle Personen',
    ],
];
