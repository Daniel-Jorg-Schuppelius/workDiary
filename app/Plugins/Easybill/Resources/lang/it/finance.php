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
    'error' => [
        'easybill_not_configured' => 'easybill non è configurato per questa organizzazione (chiave API mancante).',
        'easybill_outcome_unclear' => 'Esito del trasferimento easybill incerto (timeout dopo l\'invio) — non ripetere alla cieca; la prossima esecuzione riconcilia tramite il marcatore di origine.',
    ],
];
