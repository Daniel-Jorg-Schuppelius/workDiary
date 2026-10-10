<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : datev.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// DATEV-Online-Plugin (MVP-122), Aufruf mit `datev-online::datev.…`.
return [
    'plugin' => [
        'description' => 'DATEV Online: accesso con DATEV, lotti contabili tramite importazione EXTF invece del download e immagini dei giustificativi ogni notte verso DATEV Unternehmen online.',
    ],
    'settings' => [
        'client_id' => 'ID client (portale sviluppatori DATEV)',
        'client_id_help' => 'Dalla registrazione dell\'app presso DATEV; inserisca lì questo indirizzo di reindirizzamento: :url',
        'client_secret' => 'Segreto client',
        'sandbox' => 'Usa sandbox',
        'sandbox_help' => 'Ambiente di test di DATEV. Lo disattivi per la produzione solo dopo l\'approvazione di DATEV.',
    ],
    'health' => [
        'not_configured' => 'Nessuna registrazione dell\'app DATEV memorizzata.',
        'not_connected' => 'Accesso a DATEV non effettuato.',
        'no_client' => 'Nessun mandante selezionato.',
        'last_error' => 'Ultimo errore: :error',
        'ok' => 'Connesso a :client.',
    ],
    'connection_status' => [
        'active' => 'connesso',
        'disconnected' => 'non connesso',
    ],
    'incoming' => [
        'label' => 'DATEV Unternehmen online',
    ],
    'transfer_kind' => [
        'extf' => 'Lotto contabile',
        'outgoing_document' => 'Fattura emessa',
        'incoming_document' => 'Fattura ricevuta',
    ],
    'transfer_status' => [
        'pending' => 'in elaborazione',
        'transferred' => 'trasferito',
        'succeeded' => 'importato',
        'failed' => 'non riuscito',
    ],
    'error' => [
        'unknown_client' => 'Questo mandante non è abilitato per l\'accesso effettuato.',
        'not_ready' => 'Prima acceda con DATEV e selezioni un mandante.',
        'batch_not_exported' => 'Si possono trasferire solo lotti contabili chiusi.',
        'client_mismatch' => 'Il lotto appartiene a un consulente o mandante diverso da quello connesso.',
        'file_missing' => 'Il file del lotto manca nell\'archivio.',
        'transfer_failed' => 'DATEV non ha accettato il lotto; i dettagli sono nella lista.',
    ],
    'flash' => [
        'not_configured' => 'DATEV Online non è configurato: mancano ID client e segreto client.',
        'state_invalid' => 'Stato di accesso non valido o scaduto: acceda di nuovo.',
        'oauth_denied' => 'L\'accesso a DATEV è stato annullato.',
        'oauth_failed' => 'Scambio di token con DATEV non riuscito (:class).',
        'connected' => 'Accesso a DATEV effettuato. Selezioni il mandante.',
        'disconnected' => 'Connessione a DATEV interrotta.',
        'client_selected' => 'Mandante :client selezionato.',
        'documents_saved' => 'Impostazioni delle immagini dei giustificativi salvate.',
        'uploaded' => ':transferred giustificativi trasferiti, :failed non riusciti.',
        'batch_transferred' => 'Lotto :no consegnato a DATEV; DATEV sta elaborando l\'importazione.',
        'jobs_refreshed' => ':count importazioni concluse.',
    ],
    'page' => [
        'subtitle' => 'Trasferire lotti contabili e immagini dei giustificativi direttamente a DATEV Unternehmen online.',
        'sandbox' => 'Sandbox',
        'connect' => 'Accedi con DATEV',
        'disconnect' => 'Disconnetti',
        'disconnect_confirm' => 'Disconnettere davvero da DATEV?',
        'not_configured' => 'Nelle impostazioni del plugin mancano ID client e segreto client della registrazione dell\'app DATEV.',
        'client' => [
            'heading' => 'Mandante',
            'choose' => 'Mandante (consulente-mandante)',
            'save' => 'Applica',
            'error' => 'L\'elenco dei mandanti non è disponibile (:class).',
            'none' => 'Nessun mandante abilitato per questo accesso.',
        ],
        'documents' => [
            'heading' => 'Immagini dei giustificativi',
            'hint' => 'Le fatture emesse vanno a DATEV Unternehmen online come «Rechnungsausgang», quelle ricevute come «Rechnungseingang» non appena sono assegnate nelle fatture ricevute, ciascuna una volta e a partire dalla data scelta.',
            'enabled' => 'Trasferire ogni notte le immagini dei giustificativi',
            'since' => 'Dalla data del giustificativo',
            'save' => 'Salva',
            'upload' => 'Trasferisci ora',
        ],
        'batches' => [
            'heading' => 'Lotti contabili',
            'hint' => 'I lotti chiusi vanno a DATEV come importazione EXTF; consulente e mandante del lotto devono corrispondere al mandante connesso.',
            'empty' => 'Nessun lotto contabile chiuso.',
            'transfer' => 'Trasferisci a DATEV',
            'refresh' => 'Verifica stato dell\'importazione',
            'col' => [
                'batch' => 'Lotto',
                'period' => 'Periodo',
                'client' => 'Consulente-mandante',
                'status' => 'DATEV',
            ],
        ],
        'transfers' => [
            'heading' => 'Giustificativi trasferiti di recente',
            'empty' => 'Nessun giustificativo ancora trasferito.',
            'col' => [
                'kind' => 'Tipo',
                'date' => 'Momento',
                'status' => 'Stato',
            ],
        ],
    ],
];
