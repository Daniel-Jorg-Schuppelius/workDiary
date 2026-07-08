<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ideas.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Mappe delle idee',
    ],
    'subtitle' => 'Mappe delle idee private e condivise — visibili solo al proprietario e alle persone esplicitamente autorizzate.',
    'empty' => 'Ancora nessuna mappa delle idee.',
    'privacy_hint' => 'Le nuove mappe sono private: visibili solo a te finché non le condividi esplicitamente con persone o team.',
    'confirm_delete' => 'Spostare la mappa nel cestino?',

    'action' => [
        'create' => 'Crea mappa',
        'edit' => 'Modifica mappa',
        'archive' => 'Archivia',
        'unarchive' => 'Riattiva',
        'restore' => 'Ripristina',
    ],

    'col' => [
        'title' => 'Titolo',
        'description' => 'Descrizione',
        'owner' => 'Proprietario',
        'visibility' => 'Visibilità',
        'nodes' => 'Nodi',
        'updated' => 'Modificata',
        'actions' => 'Azioni',
    ],

    'filter' => [
        'active' => 'Attive',
        'archived' => 'Archiviate',
        'trashed' => 'Cestino',
    ],

    'visibility' => [
        'private' => 'Privata',
        'shared' => 'Condivisa',
    ],

    'share_role' => [
        'viewer' => 'Lettura',
        'editor' => 'Modifica',
    ],

    'color' => [
        'default' => 'Neutro',
        'primary' => 'Blu',
        'success' => 'Verde',
        'warning' => 'Giallo',
        'error' => 'Rosso',
        'info' => 'Turchese',
    ],

    'node_status' => [
        'open' => 'Aperta',
        'in_review' => 'In esame',
        'decided' => 'Decisa',
        'rejected' => 'Rifiutata',
        'done' => 'Realizzata',
    ],

    'import' => [
        'action' => 'Importa',
        'title' => 'Importa mappa delle idee',
        'submit' => 'Importa',
        'file' => 'File',
        'hint' => 'FreeMind/Freeplane (.mm) o OPML. Crea una nuova mappa privata.',
        'done' => 'Mappa importata.',
        'default_title' => 'Mappa importata',
        'error' => [
            'invalid' => 'Il file non è un XML valido.',
            'unsupported' => 'Formato non supportato (solo FreeMind .mm e OPML).',
            'empty' => 'Il file non contiene nodi.',
            'too_deep' => 'La struttura è annidata troppo in profondità.',
            'too_large' => 'La mappa ha troppi nodi.',
        ],
    ],

    'legend' => [
        'context' => 'Contesto (facoltativo)',
        'map' => 'Mappa',
    ],

    'outline' => [
        'title' => 'Struttura',
        'empty' => 'Questa mappa non ha ancora nodi.',
    ],

    'flash' => [
        'created' => 'Mappa creata.',
        'updated' => 'Mappa salvata.',
        'archived' => 'Mappa archiviata.',
        'unarchived' => 'Mappa riattivata.',
        'deleted' => 'Mappa spostata nel cestino.',
        'restored' => 'Mappa ripristinata.',
        'owner_invalid' => 'Nuovo proprietario non valido.',
        'ownership_transferred' => 'Proprietà trasferita.',
        'share_granted' => 'Condivisione concessa.',
        'share_revoked' => 'Condivisione revocata.',
        'share_invalid' => 'Selezione di condivisione non valida (esattamente una persona o un team).',
    ],

    'share' => [
        'title' => 'Condivisioni',
        'none' => 'Questa mappa è privata — nessuna condivisione.',
        'user' => 'Persona',
        'team' => 'Team',
        'role' => 'Ruolo',
        'add' => 'Condividi',
        'revoke' => 'Revoca condivisione',
        'hint' => 'Esattamente una persona O un team per condivisione. L\'appartenenza al team viene verificata all\'accesso.',
    ],

    'notification' => [
        'shared' => ':actor ha condiviso con te una mappa delle idee.',
    ],

    'export' => [
        'generated_at' => 'Creato il',
        'footer_note' => 'Esportazione della vista struttura — le posizioni del canvas sono incluse nell\'esportazione JSON.',
    ],

    'context' => [
        'customer' => 'Cliente',
        'project' => 'Progetto',
    ],

    'convert' => [
        'done' => 'Trasferito:',
        'already' => 'Già trasferito:',
        'error' => [
            'module_disabled' => 'Il modulo di destinazione non è attivato.',
            'target_not_allowed' => 'Questa destinazione non è consentita.',
        ],
    ],

    'editor' => [
        'outline' => 'Struttura',
        'canvas' => 'Mappa',
        'saving' => 'Salvataggio …',
        'undo_delete' => 'Annulla eliminazione',
        'keys_hint' => 'Invio: nuovo nodo · Tab: rientro · Alt+↑/↓: sposta · F2: rinomina',
        'conflict_title' => 'Modifica simultanea rilevata — la tua versione era obsoleta.',
        'conflict_take_server' => 'Usa la versione del server',
        'conflict_retry_mine' => 'Riapplica la mia modifica',
        'new_node' => 'Nuova idea',
        'convert_task' => 'In attività',
        'convert_project' => 'In progetto',
        'convert_knowledge' => 'In articolo di conoscenza',
        'confirm_delete_node' => 'Spostare il nodo e i sottonodi nel cestino?',
        'add_child' => 'Aggiungi sottonodo',
        'rename' => 'Rinomina',
        'details' => 'Dettagli',
        'move_up' => 'Sposta su',
        'move_down' => 'Sposta giù',
        'indent' => 'Aumenta rientro',
        'outdent' => 'Riduci rientro',
        'delete' => 'Elimina',
        'expand' => 'Espandi ramo',
        'collapse' => 'Comprimi ramo',
        'zoom_in' => 'Ingrandisci',
        'zoom_out' => 'Riduci',
        'zoom_reset' => 'Reimposta zoom al 100%',
        'fit' => 'Adatta vista',
        'arrange' => 'Riorganizza',
        'arrange_hint' => 'Disponi automaticamente tutti i nodi ad albero',
        'canvas_large' => 'Area di lavoro grande',
        'canvas_small' => 'Area di lavoro compatta',
        'canvas_keys_hint' => 'Tab: sottonodo · Invio: nodo fratello · doppio clic sull\'area: nuova idea · trascina su un nodo: riaggancia',
        'canvas_a11y_hint' => 'Modifica accessibile nella vista struttura.',
        'export_svg' => 'Esporta come immagine SVG',
        'export_png' => 'Esporta come immagine PNG',
        'history' => 'Cronologia',
        'history_empty' => 'Ancora nessuna modifica.',
        'presence_suffix' => 'sta modificando',
        'note' => 'Nota',
        'color' => 'Colore',
        'status' => 'Stato',
        'status_none' => '— nessuno stato',
    ],

    'error' => [
        'conflict' => 'Il nodo è stato modificato nel frattempo — verifica lo stato attuale.',
        'cycle' => 'Un nodo non può essere spostato sotto un proprio discendente.',
        'root_immovable' => 'Il nodo radice non può essere spostato né eliminato.',
        'foreign_node' => 'Il nodo non appartiene a questa mappa.',
    ],
];
