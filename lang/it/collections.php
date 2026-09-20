<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : collections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sammlungen (MVP-809, Feature 155).
return [
    'title' => [
        'index' => 'Raccolte',
        'tree' => 'Albero delle raccolte',
    ],
    'subtitle' => 'Ordina insieme note, mappe di idee, articoli, documenti e contenuti formativi: un contenuto può stare in più raccolte.',
    'action' => [
        'show_archived' => 'Mostra archiviate',
        'hide_archived' => 'Nascondi archiviate',
        'create' => 'Crea raccolta',
        'create_child' => 'Crea sottoraccolta',
        'edit' => 'Modifica raccolta',
        'save' => 'Salva',
        'archive' => 'Archivia',
        'restore' => 'Ripristina',
        'remove_item' => 'Rimuovi dalla raccolta',
        'add_to_collection' => 'Aggiungi a raccolta',
        'add' => 'Aggiungi',
    ],
    'empty' => [
        'tree' => 'Ancora nessuna raccolta.',
        'selection' => 'Nessuna raccolta selezionata.',
        'items' => 'Questa raccolta è vuota oppure contiene solo contenuti che non puoi vedere.',
    ],
    'help' => [
        'intro' => 'Una raccolta ordina i contenuti, non concede accessi: ognuno vede solo ciò che può già vedere.',
        'add_from_detail' => 'Aggiungi i contenuti con «Aggiungi a raccolta» nella loro pagina di dettaglio.',
        'parent' => 'Al massimo :max livelli di profondità.',
        'visibility' => 'Una raccolta privata la vede solo chi l’ha creata.',
        'create_first' => 'Crea prima una raccolta in «Raccolte».',
        'multiple_membership' => 'Un contenuto può stare in più raccolte senza copie.',
    ],
    'visibility' => [
        'organization' => 'Organizzazione',
        'private' => 'Privata',
    ],
    'badge' => [
        'archived' => 'Archiviata',
        'already_in' => 'già inclusa',
    ],
    'field' => [
        'type' => 'Tipo',
        'title' => 'Titolo',
        'added_by' => 'Aggiunto',
        'actions' => 'Azioni',
        'description' => 'Descrizione',
        'parent' => 'Raccolta superiore',
        'no_parent' => '— livello superiore —',
        'visibility' => 'Visibilità',
        'subject' => 'Riferimento',
        'customer' => 'Cliente',
        'collection' => 'Raccolta',
    ],
    'flash' => [
        'created' => 'Raccolta creata.',
        'updated' => 'Raccolta salvata.',
        'archived' => 'Raccolta archiviata.',
        'restored' => 'Raccolta ripristinata.',
        'item_added' => 'Aggiunto a «:collection».',
        'item_removed' => 'Rimosso dalla raccolta.',
        'items_added' => '{0} Nessun contenuto aggiunto.|{1} Un contenuto aggiunto a «:collection».|[2,*] :count contenuti aggiunti a «:collection».',
    ],
    'errors' => [
        'too_deep' => 'Le raccolte si possono annidare al massimo per :max livelli.',
        'cycle' => 'Una raccolta non può stare sotto sé stessa o sotto una sua sottoraccolta.',
        'type_not_allowed' => 'Questo tipo di contenuto non si può aggiungere a una raccolta.',
        'item_not_found' => 'Il contenuto non esiste o non è visibile per te.',
        'parent_invalid' => 'La raccolta superiore scelta non esiste (più).',
    ],
    'type' => [
        'note' => 'Nota',
        'idea_map' => 'Mappa di idee',
        'knowledge_article' => 'Articolo della knowledge base',
        'document' => 'Documento',
        'learning_course' => 'Corso',
        'learning_path' => 'Percorso formativo',
    ],
    'references' => [
        'title' => 'Riferimenti',
        'outgoing' => 'Rimanda a',
        'backlinks' => 'Citato in',
        'action' => [
            'create' => 'Aggiungi riferimento',
            'remove' => 'Rimuovi riferimento',
        ],
        'field' => [
            'target' => 'Destinazione',
            'search' => 'Cerca contenuto …',
        ],
        'kind' => [
            'linked' => 'collegato',
            'converted' => 'convertito',
            'mentioned' => 'citato',
        ],
        'empty' => 'Ancora nessun riferimento: né da qui né verso qui.',
        'empty_picker' => 'Nessun altro contenuto a cui puoi rimandare.',
        'help' => 'Un riferimento collega due contenuti senza dare accesso: vede l’altro lato solo chi può aprirlo.',
        'confirm_remove' => 'Rimuovere questo riferimento? Entrambi i contenuti restano.',
        'flash' => [
            'added' => 'Riferimento a «:title» aggiunto.',
            'removed' => 'Riferimento rimosso.',
        ],
        'errors' => [
            'self' => 'Un contenuto non può rimandare a sé stesso.',
            'not_removable' => 'Questo riferimento è gestito dal suo modulo, non da questa lista.',
        ],
    ],
    'hub' => [
        'title' => 'Conoscenza',
        'subtitle' => 'Note, mappe di idee, articoli della knowledge base, documenti e contenuti formativi in un unico posto, ordinati per raccolte e tag.',
        'search' => 'Cerca nei titoli …',
        'all_types' => 'Tutti i tipi',
        'all_customers' => 'Tutti i clienti',
        'all_contents' => 'Tutti i contenuti',
        'view_list' => 'Elenco',
        'view_tiles' => 'Riquadri',
        'manage' => 'Gestisci raccolte',
        'tag_filter' => 'Filtro per tag',
        'selected' => ':n contenuti selezionati',
        'select_all' => 'Seleziona tutto',
        'select_item' => 'Seleziona «:title»',
        'updated' => 'Modificato',
        'empty' => 'Nessun contenuto trovato.',
        'empty_hint' => 'Allenta i filtri o scegli un’altra raccolta.',
        'add_hits' => 'Aggiungi alla raccolta',
    ],
    'import' => [
        'untitled' => 'Senza titolo',
        'source' => 'Origine',
        'target' => 'Importa come',
        'root_title' => 'Nome della raccolta',
        'root_title_hint' => 'Vuoto: nome della cartella o del blocco appunti. Una raccolta con lo stesso nome viene riutilizzata.',
        'rules' => 'Solo lettura e solo su richiesta: i contenuti già importati restano invariati e non viene riscritto nulla. Al massimo :max nuovi contenuti per esecuzione; un’ulteriore esecuzione importa il resto.',
        'origin' => 'Importato da :source il :date',
        'action' => [
            'start' => 'Avvia importazione',
        ],
        'obsidian' => [
            'title' => 'Importa da Obsidian',
            'action' => 'Importa Obsidian',
            'intro' => 'Legge una cartella Obsidian tramite una connessione cartella esistente dell’acquisizione documenti (Nextcloud, OneDrive, Dropbox, Google Drive). Le sottocartelle diventano raccolte, i tag YAML e i #tag vengono mantenuti, i [[wikilink]] diventano riferimenti.',
            'none' => 'Nessuna connessione cartella attiva. Configura prima in Amministrazione › Acquisizione documenti cloud una connessione che raggiunga la cartella del vault Obsidian.',
            'connection' => 'Connessione cartella',
            'vault_path' => 'Percorso del vault',
            'vault_path_hint' => 'Relativo alla cartella radice della connessione; vuoto = l’intera cartella radice. .obsidian/ e .trash/ restano esclusi.',
        ],
        'onenote' => [
            'title' => 'Importa da OneNote',
            'action' => 'Importa OneNote',
            'intro' => 'Importa un blocco appunti tramite la connessione OneNote in sola lettura: gruppi di sezioni e sezioni diventano raccolte, ogni pagina una nota o un articolo. Il contenuto della pagina viene importato come testo.',
            'none' => 'Nessun blocco appunti trovato.',
            'error' => 'OneNote al momento non è raggiungibile: controlla la connessione nel pannello Microsoft 365.',
            'notebook' => 'Blocco appunti',
        ],
        'flash' => [
            'done' => 'Importati: :created nuovi, :skipped già presenti, :collections raccolte create, :links riferimenti aggiunti.',
            'limited' => 'Raggiunto il limite di :max nuovi contenuti: un’ulteriore esecuzione importa il resto.',
            'failed' => 'L’importazione non è riuscita. I contenuti già importati restano; un’ulteriore esecuzione prosegue.',
            'source_unavailable' => 'L’origine non è (più) disponibile.',
            'notebook_invalid' => 'Il blocco appunti scelto non è (più) disponibile.',
        ],
    ],
];
