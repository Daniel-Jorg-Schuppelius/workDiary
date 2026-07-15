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
    'subtitle' => 'Azioni registrate offline su questo dispositivo — in attesa o rifiutate.',
    'notice' => 'Questo elenco è salvato solo su questo dispositivo. Le voci in attesa vengono sincronizzate automaticamente appena torna la connessione; le voci rifiutate possono essere riapplicate o eliminate.',
    'empty' => 'Nessuna modifica offline su questo dispositivo.',
    'section' => [
        'pending' => 'In attesa',
        'rejected' => 'Rifiutate',
    ],
    'type' => [
        'clock_in' => 'Timbratura di entrata',
        'clock_out' => 'Timbratura di uscita',
        'comment' => 'Commento alla commessa',
        'form' => 'Modulo',
    ],
    'action' => [
        'retry' => 'Riapplica',
        'discard' => 'Elimina',
    ],
];
