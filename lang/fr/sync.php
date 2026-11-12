<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'error' => [
        'stamp_in_future' => 'L’horodatage se situe dans le futur.',
        'stamp_too_old' => 'L’horodatage date de plus de :days jours et n’est plus accepté.',
        'day_locked' => 'La journée est clôturée ou le mois validé — veuillez demander une correction de temps.',
        'stamp_overlaps' => 'Un pointage existe déjà pour ce moment.',
        'forbidden' => 'Vous n’êtes pas autorisé à effectuer cette action.',
        'module_inactive' => 'Ce domaine n’est pas activé pour votre organisation.',
    ],
    // Konflikte statt Ablehnung: der Nutzer entscheidet (MVP-1593/1594).
    'conflict' => [
        'missing_fields' => 'Des informations obligatoires manquent : :fields.',
        'day_locked' => 'Le :date a été clôturé ou le mois validé entre-temps — la saisie n’a pas été reprise. Modifiez la date ou demandez une correction de temps.',
        'lock' => [
            'day' => 'Journée clôturée',
            'month' => 'Mois validé',
        ],
    ],
];
