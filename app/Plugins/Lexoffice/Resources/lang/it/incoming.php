<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → Lexware Office (Feature 163, MVP-1111).

return [
    'label' => 'Lexware Office',
    'amount_differs' => 'In Lexware esiste già il giustificativo :number con un altro importo. Lo verifichi lì e poi avvii di nuovo la consegna.',
    'remark' => 'Dalle fatture ricevute di WorkDiary (:sender).',
    'no_party' => 'Il documento non è ancora assegnato a nessuna parte.',
    'contact_missing' => 'Il contatto :party non è stato né trovato né creato in Lexware.',
    'contact_role' => 'Lexware rifiuta il contatto :party a causa del suo ruolo. Aggiunga in Lexware il ruolo di fornitore o cliente e poi avvii di nuovo la consegna.',
    'header_only' => [
        'no_category' => 'Consegnato senza importi: per la parte e nelle impostazioni non è indicata alcuna categoria contabile.',
        'currency' => 'Consegnato senza importi: Lexware accetta giustificativi solo in euro.',
        'rates' => 'Consegnato senza importi: le aliquote non corrispondono a Lexware o i totali sono incompleti.',
    ],
    'settings' => [
        'transfer' => 'Consegnare le fatture ricevute a Lexware Office',
        'transfer_help' => 'I documenti assegnati arrivano in Lexware come «da verificare». Se lì esiste già un giustificativo con lo stesso numero, viene solo collegato.',
        'incoming_category' => 'Categoria predefinita per i documenti in entrata',
        'outgoing_category' => 'Categoria predefinita per i documenti in uscita',
        'category_help' => 'Senza categoria i giustificativi arrivano in Lexware senza importi. Una categoria sul fornitore o sul cliente ha la precedenza. L’elenco proviene dalla sincronizzazione delle categorie.',
        'no_category' => '— nessuna —',
    ],
    'category' => [
        'title' => 'Categoria contabile in Lexware',
        'help' => 'I documenti delle fatture ricevute arrivano in Lexware con questa categoria invece di quella predefinita nelle impostazioni.',
        'save' => 'Salvare la categoria',
        'saved' => 'Categoria contabile salvata.',
        'cleared' => 'Categoria contabile rimossa; vale quella predefinita nelle impostazioni.',
    ],
];
