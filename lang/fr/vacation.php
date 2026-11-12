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
        'title' => 'Demander une correction',
        'action' => 'Demander une correction',
        'legend' => 'Remarque sur la correction',
        'note' => 'Que faut-il modifier ?',
        'note_hint' => 'La personne qui a fait la demande voit la remarque, modifie la demande et la soumet de nouveau.',
        'requested' => 'Demande de congé renvoyée pour correction.',
        'resubmitted' => 'Demande de congé modifiée et soumise de nouveau.',
        'returned' => 'Cette demande a été renvoyée pour correction.',
        'resubmit_hint' => 'En enregistrant, vous soumettez de nouveau la demande.',
        'not_decidable' => 'La demande est en correction auprès de la personne qui l’a faite et ne peut être décidée qu’après une nouvelle soumission.',
    ],
];
