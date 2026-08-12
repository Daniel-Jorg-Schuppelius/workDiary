<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : terminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Terminali di timbratura',
    'intro' => 'Terminali RFID/NFC fissi permettono ai dipendenti senza dispositivo aziendale di timbrare entrata e uscita. Gli eventi confluiscono nella stessa logica di presenza delle timbrature da browser (correzioni, report). I token del dispositivo e gli identificativi dei badge vengono memorizzati solo come hash.',

    'new_heading' => 'URL di ingest del terminale',
    'new_hint' => 'Inseriscilo ora nel terminale — il token viene mostrato solo questa volta.',

    'terminals_heading' => 'Terminali',
    'no_terminals' => 'Nessun terminale registrato finora.',
    'badges_heading' => 'Badge',
    'no_badges' => 'Nessun badge assegnato finora.',

    'field' => [
        'name' => 'Etichetta',
        'name_placeholder' => 'ad es. Capannone Nord',
        'site' => 'Sede',
        'no_site' => '— senza sede —',
    ],

    'badge' => [
        'user' => 'Dipendente',
        'label' => 'Etichetta',
        'uid' => 'Identificativo badge',
        'uid_placeholder' => 'UID RFID/NFC',
        'uid_help' => 'Memorizzato solo come hash (nessun identificativo in chiaro).',
        'validity' => 'Validità',
        'valid_from' => 'Valido dal',
        'valid_until' => 'Valido fino al',
        'outside_validity' => 'fuori validità',
    ],

    'action' => [
        'register' => 'Registra',
        'disable' => 'Disattiva',
        'assign' => 'Assegna',
        'revoke' => 'Revoca',
        'rotate' => 'Ruota token',
        'rotate_help' => 'Genera un nuovo token del dispositivo — il vecchio diventa subito non valido.',
    ],

    'col' => [
        'status' => 'Stato',
        'status_display' => 'Visualizzazione stato',
        'last_seen' => 'Ultima attività',
    ],

    'status_display' => [
        'on' => 'Attiva',
        'off' => 'Disattivata',
        'help' => 'Mostra saldo/ferie residue sul dispositivo dopo la timbratura (visibile ai presenti) — disattivata di default.',
    ],

    'buffer' => [
        'label' => 'Buffer',
        'help' => 'Eventi offline segnalati dal terminale non ancora trasmessi.',
    ],

    'status' => [
        'active' => 'Attivo',
        'inactive' => 'Disattivato',
        'revoked' => 'Revocato',
    ],

    'flash' => [
        'registered' => 'Terminale registrato.',
        'terminal_disabled' => 'Terminale disattivato.',
        'badge_assigned' => 'Badge assegnato.',
        'badge_revoked' => 'Badge revocato.',
        'badge_taken' => 'Questo identificativo badge è già assegnato.',
        'token_rotated' => 'Token del dispositivo ruotato — nuovo URL di ingest visibile una sola volta.',
        'status_enabled' => 'Visualizzazione stato attivata.',
        'status_disabled' => 'Visualizzazione stato disattivata.',
    ],
];
