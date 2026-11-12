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
    // Online-Vorauszahlung der Direktbuchung (MVP-1515).
    'prepayment' => [
        'hint' => 'Una prenotazione diretta richiede un pagamento anticipato online del :percent % del prezzo lordo. La prenotazione resta valida :minutes minuti; è confermata al ricevimento del pagamento.',
        'amount' => 'Pagamento anticipato :amount',
        'due' => 'Pagamento entro :until',
        'pay_now' => 'Pagare ora',
        'unavailable' => 'La prenotazione diretta non è al momento possibile perché non è configurato alcun pagamento online. Invii una richiesta.',
        'not_due' => 'Per questa prenotazione non c’è alcun pagamento in sospeso.',
        'expired_error' => 'Il termine di pagamento è scaduto; la prenotazione è stata liberata.',
        'checkout_failed' => 'La pagina di pagamento non è al momento raggiungibile. La Sua prenotazione resta valida fino a :until: riprovi con «Pagare ora».',
        'description' => 'Pagamento anticipato :subject dal :from',
        'confirmed' => 'Pagamento ricevuto: la Sua prenotazione è confermata.',
        'pending' => 'Il pagamento non è ancora confermato. Appena il fornitore di pagamento lo comunica, la Sua prenotazione è confermata.',
        'late_notification' => 'Pagamento anticipato dopo la scadenza da :customer',
        'late_message' => ':subject: la prenotazione era già scaduta. Prenoti di nuovo o rimborsi il pagamento.',
        'expired_reason' => 'Pagamento anticipato online non ricevuto in tempo.',
        'withdrawn_reason' => 'Prenotazione diretta non pagata ritirata dal cliente.',
        'on_case' => 'Pagamento anticipato online :amount pagato: viene imputato alla fattura.',
        'credited_on_case' => 'Pagamento anticipato online di :amount trasmesso al programma di contabilità con le voci di noleggio: lo scomputi lì dalla fattura.',
        'profile_percent' => 'Pagamento anticipato per prenotazione diretta (%)',
        'profile_percent_hint' => 'Vuoto = valore dell’organizzazione, 0 = nessun anticipo per questo oggetto di noleggio.',
        'badge' => 'Pagamento anticipato :percent %',
        'hint_list' => 'Una prenotazione diretta richiede il pagamento anticipato online indicato nell’elenco. La prenotazione resta valida :minutes minuti; è confermata al ricevimento del pagamento.',
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
    // Vormerkhinweis, Protokollpositionen, Mindestzustand, Reinigung (MVP-1604).
    'condition' => [
        'below_minimum' => ':asset è nello stato «:current», inferiore allo stato minimo «:minimum» del profilo di noleggio, e non può essere assegnato.',
        'below_badge' => 'sotto lo stato minimo',
    ],
    'soft' => [
        'title' => 'In questo periodo esistono prenotazioni provvisorie per questi apparecchi',
        'hint' => 'Le prenotazioni provvisorie non bloccano. Chiarisca con le pratiche indicate chi riceve l’apparecchio.',
        'reserved_warning' => 'In questo periodo esiste :count prenotazione provvisoria di un’altra pratica. Le pratiche interessate sono indicate nella pratica.|In questo periodo esistono :count prenotazioni provvisorie di altre pratiche. Le pratiche interessate sono indicate nella pratica.',
    ],
    'cleaning' => [
        'not_open' => 'Questa finestra non è una finestra di pulizia aperta.',
        'done' => 'Pulizia completata: l’apparecchio può essere di nuovo assegnato.',
        'mark_done' => 'Pulizia completata',
        'form_hint' => 'Crea una finestra di pulizia; l’apparecchio non può essere assegnato fino al termine della pulizia.',
    ],
    'report' => [
        'checklist' => 'Lista di controllo',
        'accessories' => 'Accessori',
        'quantity_of' => 'Quantità :label',
        'accessories_hint' => 'Deselezioni un pezzo mancante.',
        'accessory_missing' => 'mancante',
        'condition_items' => 'Voci di stato',
        'item_label' => 'Componente o punto di controllo',
        'item_label_n' => 'Voce di stato :n: denominazione',
        'item_state_n' => 'Voce di stato :n: esito',
        'item_note' => 'Osservazione',
        'item_note_n' => 'Voce di stato :n: osservazione',
        'rows_hint' => 'Le righe vuote non vengono salvate.',
        'consumables' => 'Consumo',
        'consumable_label' => 'Materiale di consumo',
        'consumable_label_n' => 'Consumo :n: denominazione',
        'consumable_quantity_n' => 'Consumo :n: quantità',
        'consumable_unit_n' => 'Consumo :n: unità',
        'details' => 'Mostra le voci',
        'point_done' => 'fatto',
        'point_open' => 'aperto',
        'returns' => 'Restituzioni',
    ],
    'profile' => [
        'home_site' => 'Sede di appartenenza',
        'min_condition' => 'Stato minimo',
        'min_condition_hint' => 'Se lo stato attuale è inferiore, l’apparecchio non può essere assegnato.',
        'no_minimum' => 'nessuno stato minimo',
        'current_condition' => 'Stato attuale',
        'current_condition_hint' => 'Viene ripreso dal verbale a ogni restituzione; da adeguare qui dopo una riparazione.',
        'condition_unknown' => 'non rilevato',
        'checklist' => 'Lista di controllo per consegna e restituzione (un punto per riga)',
        'checklist_hint' => 'I punti compaiono da spuntare nei verbali di consegna e restituzione.',
    ],
    'deposit' => [
        'waive' => 'Condonare',
        'waive_reason' => 'Motivo del condono',
        'flash' => [
            'waived' => 'Cauzione condonata.',
        ],
        'error' => [
            'not_waivable' => 'Si possono condonare solo le cauzioni richieste e non ancora ricevute.',
            'reason_required' => 'Il condono di una cauzione richiede una motivazione.',
        ],
    ],
];
