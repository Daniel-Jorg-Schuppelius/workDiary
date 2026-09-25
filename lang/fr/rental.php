<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Conditions de location',
        'signed' => 'Contrat :contract, version :revision, signé le :date',
        'missing' => 'Aucune condition de location signée pour ce client.',
        'missing_required' => 'Aucune condition de location signée — la remise n’est possible qu’ensuite.',
        'create_agreement' => 'Créer les conditions de location',
        'required' => 'La remise nécessite des conditions de location signées par le client (paramètre de l’organisation).',
    ],
];
