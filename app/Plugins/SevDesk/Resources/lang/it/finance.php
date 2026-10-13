<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'error' => [
        'sevdesk_not_configured' => 'sevDesk non è configurato per questa organizzazione (token API mancante).',
        'sevdesk_cancel_not_transferred' => 'La fattura :number non è in sevDesk; lo storno non può essere creato lì. Imposti la serie «Storno» su WorkDiary per numerarlo localmente.',
        'sevdesk_outcome_unclear' => 'Esito della consegna a sevDesk incerto (timeout dopo l\'invio) — non ripetere alla cieca; la prossima esecuzione riconcilia tramite il marcatore di origine.',
    ],
    'sevdesk' => [
        'introduction' => 'Vi fatturiamo come segue le nostre forniture e prestazioni per il periodo :from – :to.',
        'tax_text' => 'IVA :rate%',
    ],
    'invoice_status' => [
        '50' => 'Fattura ricorrente inattiva',
        '100' => 'Bozza',
        '200' => 'aperta',
        '750' => 'pagata parzialmente',
        '1000' => 'pagata',
    ],
];
