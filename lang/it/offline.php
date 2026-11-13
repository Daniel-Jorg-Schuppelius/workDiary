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
    'subtitle' => 'Azioni registrate offline — in attesa, in conflitto o rifiutate.',
    'notice' => 'Le voci in attesa e rifiutate esistono solo su questo dispositivo; quelle in attesa vengono trasmesse automaticamente non appena c’è una connessione. I conflitti richiedono la Sua decisione: i dati sono cambiati dopo la registrazione (ad esempio la timbratura, oppure la giornata è stata chiusa nel frattempo) o manca un dato obbligatorio. I conflitti aperti compaiono qui anche se sono nati su un altro dispositivo.',
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
        'time_entry' => 'Tempo di progetto',
        'material_usage' => 'Materiale',
        'learning_unit' => 'Unità didattica completata',
        'inventory_count' => 'Scansione inventario',
        'tour_arrive' => 'Fermata del giro: arrivo',
        'tour_depart' => 'Fermata del giro: partenza',
        'patrol_scan' => 'Punto di controllo',
        'takeoff_line' => 'Riga di computo',
    ],
    'action' => [
        'retry' => 'Riapplica',
        'discard' => 'Elimina',
        'take_server' => 'Mantieni l’altra versione',
        'force_local' => 'Invia la mia versione',
        'edit' => 'Modifica',
        'send_edited' => 'Invia modificato',
        'cancel_edit' => 'Annulla',
    ],
    'conflict_hint' => 'Stato del server: :server',
    'photos_queued' => 'Foto in coda: :count',
    // Konflikte entscheiden (MVP-1595).
    'field' => [
        'missing' => 'Manca',
        'lock' => 'Blocco',
        'stock' => 'Giacenza',
    ],
    // Conflitto per giacenza mancante (MVP-1691): solo con il permesso «Autorizzare giacenze negative».
    'allow_negative_hint' => 'Registra il consumo in giacenza negativa nonostante la giacenza mancante. Se la Sua organizzazione richiede una seconda approvazione, viene creata una richiesta nella casella delle approvazioni.',
    'remote_hint' => 'Registrato su un altro dispositivo: i contenuti si trovano lì. Qui può soltanto scartare il conflitto.',
    'resolve_failed' => 'Non è stato possibile trasmettere la decisione. Riprovi con una connessione.',
    'unresolved' => 'aperto',
    'flash' => [
        'resolved' => 'Conflitto risolto.',
        'not_found' => 'Questo conflitto non esiste più.',
    ],
];
