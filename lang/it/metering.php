<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Zählerstands-Faktura (Feature 116, MVP-605).
return [
    'title' => 'Fatturazione a contatore',
    'subtitle' => 'Fatturazione a consumo per cliente e apparecchio dalle letture registrate',
    'empty' => 'Nessun accordo registrato.',
    'created' => 'Accordo registrato.',
    'updated' => 'Accordo aggiornato.',
    'draft_notice' => 'L’elaborazione crea solo bozze di fattura — verifica ed emissione restano manuali.',
    'blocked_external' => 'Per questo cliente la fatturazione è gestita da un sistema esterno — non viene creato alcun documento.',
    'run_done' => 'Fatturato: :created bozza/e, :skipped saltate.',
    'form_hint' => 'Senza lettura finale nel periodo non nasce una bozza, ma una segnalazione — non si stima nulla.',
    'unit_default' => 'unità',
    'action' => [
        'create' => 'Registra accordo',
        'edit' => 'Modifica accordo',
        'run' => 'Fattura ora',
    ],
    'column' => [
        'title' => 'Denominazione',
        'customer' => 'Cliente',
        'asset' => 'Apparecchio',
        'base_price' => 'Prezzo base',
        'unit_price' => 'Prezzo unitario',
        'free_units' => 'Quantità inclusa',
        'unit' => 'Unità',
        'interval' => 'Cadenza',
        'interval_count' => 'Fattore',
        'next_run_on' => 'Prossima fatturazione',
        'end_on' => 'Fine',
        'status' => 'Stato',
    ],
    'interval' => [
        'monthly' => 'mensile',
        'quarterly' => 'trimestrale',
        'yearly' => 'annuale',
    ],
    'status' => [
        'active' => 'Attivo',
        'paused' => 'In pausa',
        'ended' => 'Terminato',
    ],
    'skipped' => [
        'heading' => 'Fatturazioni saltate',
        'hint' => 'Senza lettura non c’è fattura. Inserire la lettura e rifatturare.',
        'reason' => [
            'missing_start_reading' => 'Nessuna lettura iniziale prima del periodo',
            'missing_end_reading' => 'Nessuna lettura nel periodo',
            'negative_consumption' => 'Consumo negativo (sostituzione contatore?)',
            'nothing_to_bill' => 'Né consumo né prezzo base',
        ],
    ],
    'line' => [
        'base' => ':title — prezzo base dal :from al :to',
        'usage' => ':title — consumo :consumption :unit, di cui :free inclusi',
        'estimated' => '(lettura stimata)',
    ],
];
