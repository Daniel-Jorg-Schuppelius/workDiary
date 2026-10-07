<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : uploads.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Datei-Uploads mit Zweck (MVP-1074): Hinweise, Fortschritt und Fehler je Datei.
 */

return [
    'label' => 'Fichiers',
    'hint' => 'Autorisé : :formats · jusqu’à :size par fichier · au plus :count fichiers, :total au total.',
    'progress' => 'Envoi en cours … :percent %',
    'error' => [
        'type' => ':name : type de fichier non autorisé.',
        'too_large' => ':name dépasse :size.',
        'count' => 'Veuillez sélectionner au plus :count fichiers à la fois.',
        'total' => 'Les fichiers sont trop volumineux au total (au plus :size par envoi).',
        'failed' => 'L’envoi a échoué. Vos saisies n’ont pas été enregistrées — veuillez réessayer.',
    ],
];
