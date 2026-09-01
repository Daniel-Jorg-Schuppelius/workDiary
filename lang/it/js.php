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
];
