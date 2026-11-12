<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vacation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Urlaubsantrag zur Korrektur zurückgeben (MVP-1554).
    'revision' => [
        'title' => 'Korrektur anfordern',
        'action' => 'Korrektur anfordern',
        'legend' => 'Hinweis zur Korrektur',
        'note' => 'Was soll geändert werden?',
        'note_hint' => 'Die antragstellende Person sieht den Hinweis, ändert den Antrag und reicht ihn neu ein.',
        'requested' => 'Urlaubsantrag zur Korrektur zurückgegeben.',
        'resubmitted' => 'Urlaubsantrag geändert und neu eingereicht.',
        'returned' => 'Dieser Antrag wurde zur Korrektur zurückgegeben.',
        'resubmit_hint' => 'Mit dem Speichern reichen Sie den Antrag neu ein.',
        'not_decidable' => 'Der Antrag liegt zur Korrektur bei der antragstellenden Person und kann erst nach der neuen Einreichung entschieden werden.',
    ],
];
