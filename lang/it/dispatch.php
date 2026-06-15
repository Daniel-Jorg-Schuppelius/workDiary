<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'heading' => 'Pianificazione',
    'badge_prefix' => 'Pianificazione',
    'set_status' => ':status',
    'override_reason' => 'Motivo della forzatura',
    'override_placeholder' => 'Perché confermare nonostante il conflitto?',
    'conflicts' => [
        'hard' => 'Conflitti bloccanti',
        'soft' => 'Avvisi',
        'none' => 'Nessun conflitto per questa assegnazione.',
    ],
    'vehicle' => [
        'heading' => 'Prenotazione veicolo',
        'label' => 'Veicolo',
        'from' => 'Da',
        'to' => 'A',
        'reserve' => 'Prenota',
        'release' => 'Rilascia',
        'none' => 'Nessuna prenotazione di veicolo per questo ordine.',
    ],
    'reservations' => [
        'title' => 'Prenotazioni veicoli',
        'subtitle' => 'Gestisci le prenotazioni per veicolo.',
        'all_vehicles' => 'Tutti i veicoli',
        'reserved_by' => 'Prenotato da',
        'empty' => 'Nessuna prenotazione disponibile.',
    ],
];
