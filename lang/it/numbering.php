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
        'inactive' => ':program (non attivo – assegna workDiary)',
        'column' => 'Assegnato da',
        'page_hint' => 'Se un programma di contabilità assegna i numeri cliente o fornitore, un nuovo record riceve il suo numero al trasferimento. I numeri esistenti restano invariati. I numeri di fattura si impostano nelle impostazioni del programma.',
    ],
    'field' => [
        'locked_hint' => ':program assegna il numero al trasferimento.',
    ],
    'flash' => [
        'issued' => 'Numero :number ripreso da :program.',
        'pending' => 'Il numero di :program seguirà con il prossimo trasferimento: :reason',
        'conflict' => 'Il numero :number di :program è già assegnato a :holder. Trova il caso nella casella di abbinamento.',
    ],
    'conflict' => [
        'title' => 'Il numero :number di :program è già assegnato a :holder',
    ],
    'error' => [
        'taken_remote' => 'Il numero :number è già assegnato a «:name» in :program.',
        'taken_local' => 'Il numero :number è già assegnato a :holder. La preghiamo di modificare prima il numero lì.',
        'no_number' => ':program non ha restituito alcun numero.',
    ],
];
