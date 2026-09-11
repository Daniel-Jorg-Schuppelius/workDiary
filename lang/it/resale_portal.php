<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Portale clienti «i miei abbonamenti» (funzionalità 152): parco senza prezzi, acquisti e giustificativi.
return [
    'title' => 'I miei abbonamenti',
    'menu' => 'Abbonamenti',
    'subtitle' => 'I tuoi abbonamenti e licenze — inclusi quelli dei tuoi clienti finali. Prezzi e importi sono riportati sulle tue fatture.',
    'field' => [
        'product' => 'Denominazione / prodotto',
        'holder' => 'Titolare',
        'quantity' => 'Quantità',
        'term' => 'Durata',
        'interval' => 'Intervallo',
        'renewal' => 'Rinnovo',
        'next_period' => 'Prossimo periodo',
        'status' => 'Stato',
        'kind' => 'Tipo',
        'period' => 'Periodo',
    ],
    'holder' => [
        'end_customer' => 'Cliente finale',
    ],
    'term' => [
        'since' => 'dal :date',
        'range' => ':from – :to',
        'running' => 'in corso',
    ],
    'interval' => [
        'yearly' => 'annuale',
        'monthly' => 'mensile',
    ],
    'next_period' => [
        'none' => 'nessun altro',
    ],
    'period_status' => [
        'open' => 'aperto',
        'billed' => 'fatturato',
        'partial' => 'parzialmente fatturato',
        'waived' => 'non fatturato',
        'disputed' => 'in verifica',
    ],
    'periods' => [
        'title' => 'Periodi di fatturazione',
        'hint' => 'I periodi derivano da inizio, durata e intervallo; «fatturato» significa che hai ricevuto la relativa fattura.',
        'empty' => 'Nessun periodo ancora pianificato.',
    ],
    'empty' => 'Nessun abbonamento registrato.',
    'back' => 'Torna alla panoramica',
    'show' => 'Dettagli',
];
