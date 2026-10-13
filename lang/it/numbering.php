<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134),
// Seite „Nummernkreise“ (Phase 144, MVP-1147–1149).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (non attivo – assegna workDiary)',
        'column' => 'Assegnato da',
        'page_hint' => 'Se un programma di contabilità assegna i numeri cliente o fornitore, un nuovo record riceve il suo numero al trasferimento. I numeri esistenti restano invariati. I numeri di fattura, nota di credito e storno li assegna il programma della via di fatturazione, oppure Lexoffice se lì è attivo l\'interruttore. Se un programma assegna i numeri, il formato è bloccato.',
        'billing' => ':program (via di fatturazione)',
        'deviation' => ':program per un cliente|:program per :count clienti',
    ],
    'field' => [
        'locked_hint' => ':program assegna il numero al trasferimento.',
        'row' => ':field (:scope)',
    ],
    'separators' => [
        'prefix' => 'Separatore dopo il prefisso, tutte le serie',
        'year' => 'Separatore dopo l\'anno, tutte le serie',
        'apply' => 'Applica a tutte le serie',
        'hint' => 'Applica entrambi i separatori a tutte le serie assegnate da workDiary e salva la pagina. Un campo vuoto significa nessun separatore. I numeri già assegnati restano invariati.',
    ],
    'preview' => [
        'program' => 'Stabilito da :program',
        'locked_hint' => 'Formato e numero li stabilisce :program.',
        'next_remote' => 'Prossimo in :program: :number',
        'last_adopted' => 'Ultimo da :program: :number',
        'pending' => 'Un contatto attende il suo numero.|:count contatti attendono il loro numero.',
    ],
    'issue' => [
        'no_issuer' => ':program non può trasferire :scope. Scelga nella pagina «Serie numeriche» chi assegna il numero.',
        'quote_pending' => ':program assegna il numero dell\'offerta all\'invio; fino ad allora l\'offerta ha un numero provvisorio.',
        'draft_number' => 'Questo documento ha un numero provvisorio e :program non può riprenderlo. Indichi nella pagina «Serie numeriche» chi assegna il numero.',
        'transfer_failed' => 'Trasmissione a :program non riuscita; il documento resta una bozza. :error',
        'issue_first' => '«:document» ha ancora un numero provvisorio. Emetta prima il documento: il programma di contabilità assegna allora il numero.',
        'invoice_pending' => ':program assegna il numero all\'emissione; WorkDiary trasmette allora il documento. Fino ad allora ha un numero provvisorio.',
    ],
    'flash' => [
        'saved' => 'Una serie numerica salvata.|:count serie numeriche salvate.',
        'unchanged' => 'Nessuna modifica.',
        'transfer_pending' => 'Documento «:document» creato. All\'emissione WorkDiary lo trasmette a :program, che gli assegna anche il numero.',
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
