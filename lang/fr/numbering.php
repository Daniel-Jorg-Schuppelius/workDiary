<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (inactif – workDiary attribue)',
        'column' => 'Attribué par',
        'page_hint' => 'Si un logiciel de comptabilité attribue les numéros de client ou de fournisseur, un nouvel enregistrement reçoit son numéro lors du transfert. Les numéros existants restent inchangés. Les numéros de facture se règlent dans les paramètres du logiciel.',
    ],
    'field' => [
        'locked_hint' => ':program attribue le numéro lors du transfert.',
    ],
    'flash' => [
        'issued' => 'Numéro :number repris de :program.',
        'pending' => 'Le numéro de :program suivra lors du prochain transfert : :reason',
        'conflict' => 'Le numéro :number de :program est déjà attribué à :holder. Vous trouverez le cas dans la boîte de rapprochement.',
    ],
    'conflict' => [
        'title' => 'Le numéro :number de :program est déjà attribué à :holder',
    ],
    'error' => [
        'taken_remote' => 'Le numéro :number est déjà attribué à « :name » dans :program.',
        'taken_local' => 'Le numéro :number est déjà attribué à :holder. Veuillez d\'abord y modifier le numéro.',
        'no_number' => ':program n\'a renvoyé aucun numéro.',
    ],
];
