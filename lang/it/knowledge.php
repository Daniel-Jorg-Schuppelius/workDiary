<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : knowledge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Base di conoscenza',
        'links' => 'Cronologia dei problemi',
        'linked' => 'Articoli collegati',
        'suggestions' => 'Suggerimenti',
    ],

    'subtitle' => 'Problemi noti, passaggi risolutivi e note interne dall’attività quotidiana.',

    'field' => [
        'title' => 'Titolo',
        'category' => 'Categoria',
        'tags' => 'Tag',
        'status' => 'Stato',
        'problem' => 'Descrizione del problema',
        'solution' => 'Passaggi risolutivi',
        'helpful' => 'Valutazione',
        'creator' => 'Creato da',
        'published_at' => 'Pubblicato il',
        'updated_at' => 'Ultima modifica',
    ],

    'action' => [
        'create' => 'Crea articolo',
        'create_from_subject' => 'Crea articolo da questo',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'show' => 'Visualizza',
        'publish' => 'Pubblica',
        'archive' => 'Archivia',
        'delete' => 'Elimina',
        'link' => 'Collega',
        'unlink' => 'Rimuovi collegamento',
        'back' => 'Indietro',
    ],

    'filter' => [
        'all' => 'Tutti',
        'search' => 'Ricerca',
        'search_placeholder' => 'Cerca in titolo, problema o soluzione',
        'sort' => 'Ordinamento',
        'sort_newest' => 'Prima i più recenti',
        'sort_helpful' => 'Prima i più utili',
    ],

    'feedback' => [
        'title' => 'Questo articolo è stato utile?',
        'helpful' => 'È stato utile',
        'not_helpful' => 'Non è stato utile',
        'already_voted' => 'Hai già votato — votare di nuovo cambia il tuo voto.',
    ],

    'link_kind' => [
        'diary' => 'Incarico',
        'asset' => 'Asset',
        'customer' => 'Cliente',
        'protocol' => 'Protocollo',
    ],

    'hint' => [
        'category' => 'ad es. stampante, rete, riscaldamento …',
        'tags' => 'Separati da virgola, ad es. firmware, modello-x',
        'problem' => 'Quale sintomo/problema si verifica?',
        'solution' => 'Quali passaggi portano alla soluzione?',
    ],

    'flash' => [
        'created' => 'Articolo creato.',
        'updated' => 'Articolo aggiornato.',
        'published' => 'Articolo pubblicato.',
        'archived' => 'Articolo archiviato.',
        'deleted' => 'Articolo eliminato.',
        'feedback_saved' => 'Grazie per la tua valutazione.',
        'linked' => 'Articolo collegato.',
        'unlinked' => 'Collegamento rimosso.',
    ],

    'empty' => 'Nessun articolo di conoscenza presente.',
    'empty_title' => 'Nessun articolo trovato',
    'empty_filtered' => 'Nessun articolo corrisponde ai filtri attuali.',
    'empty_links' => 'Nessun collegamento presente.',
    'empty_context' => 'Nessun articolo collegato e nessun suggerimento corrispondente.',
    'confirm_archive' => 'Archiviare davvero questo articolo? Scomparirà dalla ricerca e dai suggerimenti.',
    'confirm_delete' => 'Eliminare davvero questo articolo?',
    'confirm_unlink' => 'Rimuovere davvero questo collegamento?',
];
