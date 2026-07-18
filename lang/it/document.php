<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : document.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Documenti',
        'versions' => 'Versioni',
        'version_history' => 'Cronologia delle versioni',
    ],

    'subtitle' => 'Gestire contratti, certificati, rapporti di prova e altri documenti.',

    'field' => [
        'title' => 'Titolo',
        'type' => 'Tipo',
        'status' => 'Stato',
        'reference' => 'Riferimento',
        'validity' => 'Validità',
        'valid_from' => 'Valido dal',
        'valid_until' => 'Valido fino al',
        'description' => 'Descrizione',
        'file' => 'File',
        'version' => 'Versione',
        'version_note' => 'Nota di versione',
        'creator' => 'Creato da',
    ],

    'action' => [
        'create' => 'Aggiungi documento',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'archive' => 'Archivia',
        'download' => 'Scarica',
        'add_version' => 'Carica nuova versione',
    ],

    'filter' => [
        'all' => 'Tutti',
        'search' => 'Ricerca',
        'search_placeholder' => 'Cerca nei titoli',
        'expiring' => 'Scade',
        'expiring_days' => 'entro :days giorni',
    ],

    'ref' => [
        'customer' => 'Cliente',
        'project' => 'Progetto',
        'diary' => 'Intervento',
        'asset' => 'Asset',
        'none' => 'Senza riferimento',
    ],

    'badge' => [
        'current' => 'Attuale',
        'expired' => 'Scaduto',
        'expires_soon' => 'In scadenza',
    ],

    'flash' => [
        'created' => 'Il documento è stato creato.',
        'updated' => 'Il documento è stato aggiornato.',
        'deleted' => 'Il documento è stato eliminato.',
        'archived' => 'Il documento è stato archiviato.',
        'version_added' => 'La versione :no è stata caricata.',
    ],

    'error' => [
        'unknown_type' => 'Tipo di documento sconosciuto.',
        'valid_until_before_from' => 'La fine della validità deve essere successiva al suo inizio.',
    ],

    'hint' => [
        'upload' => 'Consentiti: PDF, immagini, file Office, testo/CSV, ZIP — max. :mb MB.',
    ],

    // Rilascio al portale clienti (ondata D — copia dei documenti).
    'customer' => [
        'section' => 'Rilascio al cliente',
        'released' => 'Rilasciato al portale clienti',
        'not_released' => 'Non rilasciato',
        'released_at' => 'Rilasciato il',
        'released_by' => 'Rilasciato da',
        'badge' => 'Portale',
        'not_linked_hint' => 'Solo i documenti collegati a un cliente o a un lavoro possono essere rilasciati.',
        'action' => [
            'release' => 'Rilascia al portale clienti',
            'revoke' => 'Revoca il rilascio',
        ],
        'confirm_revoke' => 'Revocare davvero il rilascio al portale clienti?',
        'flash' => [
            'released' => 'Il documento è stato rilasciato al portale clienti.',
            'revoked' => 'Il rilascio al portale clienti è stato revocato.',
        ],
        'error' => [
            'not_linked' => 'Solo i documenti collegati a un cliente o a un lavoro possono essere rilasciati.',
        ],
        'portal' => [
            'title' => 'Documenti',
            'subtitle' => 'I documenti rilasciati per te.',
            'empty' => 'Non è ancora stato rilasciato alcun documento per te.',
        ],
    ],

    'empty' => 'Ancora nessun documento.',
    'empty_title' => 'Nessun documento trovato',
    'empty_filtered' => 'Nessun documento corrisponde ai filtri attuali.',
    'empty_versions' => 'Ancora nessuna versione.',
    'confirm_delete' => 'Eliminare davvero questo documento con tutte le versioni?',
    'confirm_archive' => 'Archiviare davvero questo documento?',
];
