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
    'easybill' => [
        'introduction' => 'Vi fatturiamo come segue le nostre forniture e prestazioni per il periodo :from – :to.',
        'unit_hour' => 'ore',
        'unit_piece' => 'pz.',
    ],
    // Steuerfall der Übergabe (MVP-1712)
    'tax_case' => [
        'not_taxable_region' => 'I luoghi delle prestazioni non imponibili in Germania si trovano in più aree (paese terzo e UE). easybill richiede una sola opzione fiscale per documento: fatturi ogni area separatamente.',
    ],
    'error' => [
        'ownership_unavailable' => 'Il collegamento non è configurato: lo stato non può essere acquisito al momento.',
        'ownership_not_ready' => 'La fonte non fornisce lo stato al momento: riprovi più tardi.',
        'easybill_no_customer' => 'Il documento non ha un cliente e non può essere trasmesso a easybill.',
        'easybill_cancel_not_transferred' => 'La fattura :number non è in easybill; lo storno non può essere creato lì. Imposti la serie «Storno» su WorkDiary per numerarlo localmente.',
        'easybill_not_configured' => 'easybill non è configurato per questa organizzazione (chiave API mancante).',
        'easybill_outcome_unclear' => 'Esito del trasferimento easybill incerto (timeout dopo l\'invio) — non ripetere alla cieca; la prossima esecuzione riconcilia tramite il marcatore di origine.',
    ],
    'relations' => [
        'draft' => 'Bozza',
        'done' => 'completato',
    ],
];
