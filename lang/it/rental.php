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
        'title' => 'Condizioni di noleggio',
        'signed' => 'Contratto :contract, versione :revision, firmato il :date',
        'missing' => 'Per questo cliente non esistono condizioni di noleggio firmate.',
        'missing_required' => 'Nessuna condizione di noleggio firmata: la consegna è possibile solo dopo.',
        'create_agreement' => 'Creare le condizioni di noleggio',
        'required' => 'La consegna richiede condizioni di noleggio firmate dal cliente (impostazione dell’organizzazione).',
    ],
    // Direktbuchung und Preisangabe im Portal (MVP-916).
    'portal' => [
        'price' => 'Prezzo (netto)',
        'price_estimate' => 'ca. :amount',
        'direct_intro' => 'Può prenotare direttamente i dispositivi liberi oppure richiedere un dispositivo o un gruppo di dispositivi, e noi confermeremo. Il prezzo deriva dal listino ed è al netto.',
        'direct_book' => 'Prenota subito',
        'direct_hint' => 'Prenota subito il dispositivo scelto se è libero nel periodo.',
        'direct_booked' => 'Dispositivo prenotato — le invieremo i documenti di consegna.',
        'direct_status' => 'Prenotato direttamente',
        'direct_disabled' => 'La prenotazione diretta non è attivata.',
        'direct_case_note' => 'Prenotazione diretta dal portale clienti.',
        'direct_notification' => 'Prenotazione diretta di :customer',
    ],
    // Mietpreisregeln (MVP-950).
    'rule' => [
        'title' => 'Regole di prezzo di noleggio',
        'empty' => 'Nessuna regola: si applica la tariffa giornaliera.',
        'add' => 'Aggiungi regola',
        'line' => ':label (:percent %)',
        'from_utilization' => 'da :percent % di utilizzo',
        'kind' => [
            'season' => 'Stagione',
            'weekday' => 'Giorni della settimana',
            'utilization' => 'Utilizzo',
        ],
        'field' => [
            'kind' => 'Tipo',
            'label' => 'Denominazione',
            'valid_from' => 'Valido dal',
            'valid_until' => 'Valido fino al',
            'weekdays' => 'Giorni della settimana',
            'utilization_min_percent' => 'Da utilizzo (%)',
            'adjust_percent' => 'Maggiorazione/sconto (%)',
        ],
        'weekday' => [
            '1' => 'lun.',
            '2' => 'mar.',
            '3' => 'mer.',
            '4' => 'gio.',
            '5' => 'ven.',
            '6' => 'sab.',
            '7' => 'dom.',
        ],
        'flash' => [
            'saved' => 'Regola di prezzo salvata.',
            'deleted' => 'Regola di prezzo rimossa.',
        ],
    ],
];
