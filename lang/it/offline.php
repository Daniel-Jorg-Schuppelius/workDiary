<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Offline-Sync (Feature 035): Offline-Änderungs-Seite + Statusanzeige.
return [
    'title' => 'Modifiche offline',
    'subtitle' => 'Azioni registrate offline su questo dispositivo — in attesa, in conflitto o rifiutate.',
    'notice' => 'Questo elenco esiste solo su questo dispositivo. Le voci in attesa vengono trasmesse automaticamente non appena c’è una connessione; quelle rifiutate possono essere reinviate o eliminate. I conflitti richiedono una decisione: qualcun altro ha modificato lo stesso record.',
    'empty' => 'Nessuna modifica offline su questo dispositivo.',
    'section' => [
        'pending' => 'In attesa',
        'rejected' => 'Rifiutate',
        'conflict' => 'Conflitti',
    ],
    'type' => [
        'clock_in' => 'Timbratura di entrata',
        'clock_out' => 'Timbratura di uscita',
        'comment' => 'Commento alla commessa',
        'form' => 'Modulo',
        'attendance_correct' => 'Correzione della timbratura',
    ],
    'action' => [
        'retry' => 'Riapplica',
        'discard' => 'Elimina',
        'take_server' => 'Mantieni l’altra versione',
        'force_local' => 'Invia la mia versione',
    ],
    'conflict_hint' => 'Stato del server: :server',
    'photos_queued' => 'Foto in coda: :count',
];
