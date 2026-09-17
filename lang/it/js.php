<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : js.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'dialog' => [
        'check_input' => 'Controlla i dati inseriti.',
        'save_failed' => 'Impossibile salvare la finestra di dialogo.',
        'load_failed' => 'Impossibile caricare la finestra di dialogo.',
        'loading' => 'Caricamento…',
        'open_in_new_tab' => 'Apri la pagina in una nuova scheda',
        'switch_to_new' => 'Passa alla nuova modalità',
        'switch_to_legacy' => 'Passa alla modalità legacy',
    ],
    'schedule' => [
        'move_failed' => 'Spostamento non riuscito.',
        'suggest_failed' => 'Impossibile caricare i suggerimenti.',
    ],
    // Dienstplan-Oberflaeche (MVP-797): war zuvor fest verdrahtetes Deutsch.
    'schedule_ui' => [
        'shift_edit' => 'Modifica turno',
        'shift_create' => 'Crea turno',
        'shift_delete_confirm' => 'Eliminare davvero questo turno?',
        'shift_type_edit' => 'Modifica tipo di turno',
        'shift_type_create' => 'Crea tipo di turno',
        'shift_type_delete_confirm' => 'Eliminare davvero questo tipo di turno?',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'close' => 'Chiudi',
        'save_failed' => 'Errore durante il salvataggio.',
        'delete_failed' => 'Errore durante l\'eliminazione.',
        'publish_failed' => 'Errore durante la pubblicazione.',
        'confirm_failed' => 'Errore durante la conferma.',
        'suggestions_title' => 'Proposte di copertura',
        'col_employee' => 'Collaboratore',
        'col_score' => 'Punteggio',
        'col_reason' => 'Motivazione',
    ],
    'bulk' => [
        'select_one' => 'Seleziona prima almeno una voce.',
    ],
    'design' => [
        'inheritance' => '«:base» · :inherited/:total ereditati, :own sovrascritti',
    ],
    // Chat-Oberflaeche (MVP-798): angepinnte Nachrichten werden per JSON geladen.
    'chat' => [
        'pinned_failed' => 'Impossibile caricare i messaggi fissati.',
        'pinned_empty' => 'Nessun messaggio fissato.',
    ],
    'kanban' => [
        'invalid_move' => 'Questo cambio di stato non è previsto nel flusso di lavoro dell\'ordine.',
        'not_allowed' => 'Non sei autorizzato a eseguire questa azione sull\'ordine.',
        'handover_via_order' => 'Il collaudo richiede un protocollo firmato e viene eseguito direttamente nell\'ordine.',
        'no_targets' => 'Al momento non è possibile alcuno spostamento consentito per questa scheda.',
    ],
    'entry_bar' => [
        'options_failed' => 'Impossibile caricare le attività/gli ordini.',
    ],
    'http' => [
        'session_expired' => 'La sessione è scaduta — la pagina verrà ricaricata.',
    ],
    // KI-Tagvorschläge im Tag-Picker (Feature 143, MVP-711)
    'ai' => [
        'tags_no_text' => 'Inserisci prima un contenuto — l’IA suggerisce tag dal testo.',
        'tags_none' => 'Nessun tag esistente corrisponde al testo.',
        'tags_failed' => 'Suggerimento tag IA non possibile: :message',
        'tags_loading' => 'L’IA cerca tag adatti …',
    ],
    // Tastenkürzel-Übersicht (Feature 037, MVP-721): Labels der Registry resources/js/shortcuts.js
    'shortcuts' => [
        'help' => 'Aprire l\'aiuto contestuale della pagina corrente',
        'title' => 'Scorciatoie da tastiera',
        'scope' => [
            'global' => 'Globale',
            'navigation' => 'Navigazione',
            'search' => 'Ricerca',
        ],
        'search' => 'Apri la ricerca globale',
        'shortcuts' => 'Mostra questa panoramica',
        'escape' => 'Chiudi finestra o ricerca',
        'search_move' => 'Sposta tra i risultati della ricerca',
        'search_open' => 'Apri il risultato',
        'go_diary' => 'Vai al diario',
        'go_customers' => 'Vai ai clienti',
        'go_projects' => 'Vai ai progetti',
        'new_entry' => 'Nuova voce',
        'then' => 'poi',
    ],
    'quiz' => [
        'progress' => ':answered su :total risposte',
    ],
];
