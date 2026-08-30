<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Stati intermedi (MVP-532): smart working/commissione di servizio.
    'intermediate' => [
        'homeoffice' => 'Smart working',
        'errand' => 'Commissione di servizio',
        'start_homeoffice' => 'Inizia smart working',
        'end_homeoffice' => 'Termina smart working',
        'start_errand' => 'Inizia commissione',
        'end_errand' => 'Termina commissione',
    ],
    'status' => [
        'open' => 'Aperto',
        'closed' => 'Chiuso',
        'auto_closed' => 'Chiuso automaticamente',
        'adjusted' => 'Rettificato',
        'cancelled' => 'Annullato',
    ],
    'source' => [
        'clock' => 'Timbratura',
        'manual' => 'Manuale',
        'import' => 'Importazione',
        'auto_close' => 'Chiusura automatica',
        'terminal' => 'Terminal',
        'phone' => 'Telefono',
        'learning' => 'Tempo di apprendimento',
    ],
    'correction' => [
        'action' => [
            'create' => 'Crea',
            'update' => 'Modifica',
            'delete' => 'Elimina',
        ],
    ],
    'error' => [
        'target_day_locked' => 'Il giorno di destinazione è chiuso o il mese approvato: richieda una correzione dei tempi.',
        'duration_too_long' => 'Una timbratura non può superare le :hours ore.',
    ],
];
