<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : quotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Angebote (Feature 112, MVP-601: Nachfassen).
return [
    'follow_up' => [
        'title' => 'Solleciti preventivi',
        'subtitle' => 'Solleciti in scadenza, preventivi in scadenza e inviati senza data',
        'action' => 'Registra sollecito',
        'submit' => 'Registra',
        'recorded' => 'Sollecito registrato.',
        'scheduled' => 'Data di sollecito impostata.',
        'empty' => 'Nulla da sollecitare.',
        'dialog_title' => 'Sollecita il preventivo :number',
        'dialog_hint' => 'Il risultato viene conservato come nota di comunicazione nel fascicolo cliente.',
        'result' => 'Esito del colloquio',
        'result_hint' => 'Che cosa ha detto il cliente? Questa nota sarà la base del prossimo preventivo.',
        'next_at' => 'Sollecitare di nuovo il',
        'next_at_hint' => 'Lasciare vuoto quando il sollecito è concluso.',
        'note_subject' => 'Sollecito per il preventivo :number',
        'next_action' => 'Sollecitare di nuovo il preventivo :number',
        'wrong_status' => 'Solo i preventivi inviati o approvati possono essere sollecitati.',
        'no_customer' => 'Il preventivo non ha un cliente — senza cliente non c’è un fascicolo per la nota.',
        'kpi' => [
            'due' => 'In scadenza',
            'upcoming' => 'In arrivo',
            'expiring' => 'In scadenza (:days giorni)',
            'expiring_hint' => 'Nessuna risposta — poi il preventivo va rifatto o prorogato.',
            'untracked' => 'Senza data',
            'untracked_hint' => 'Inviato, ma nessuno ha fissato una data di sollecito.',
        ],
        'section' => [
            'due' => 'In scadenza',
            'upcoming' => 'In arrivo',
            'expiring' => 'In scadenza senza risposta',
            'untracked' => 'Inviato senza data di sollecito',
        ],
        'column' => [
            'number' => 'Preventivo',
            'customer' => 'Cliente',
            'owner' => 'Responsabile',
            'follow_up_at' => 'Sollecito il',
            'valid_until' => 'Valido fino al',
            'total' => 'Totale',
        ],
        'filter' => ['mine' => 'Solo i miei'],
    ],
];
