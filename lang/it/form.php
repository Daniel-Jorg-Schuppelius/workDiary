<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : form.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'templates' => 'Modelli di modulo',
        'template' => 'Modello',
        'submissions' => 'Moduli',
        'submission' => 'Modulo compilato',
        'values' => 'Inserimenti',
        'panel' => 'Moduli',
    ],

    'subtitle' => [
        'templates' => 'Gestire moduli configurabili (verbali, checklist) senza codice.',
        'submissions' => 'Moduli compilati — a prova di versione tramite lo snapshot della definizione dei campi.',
    ],

    'field' => [
        'name' => 'Nome',
        'description' => 'Descrizione',
        'status' => 'Stato',
        'fields' => 'Campi',
        'submissions' => 'Compilati',
        'creator' => 'Creato da',
        'template' => 'Modello',
        'subject' => 'Riferimento',
        'submitted_by' => 'Compilato da',
        'submitted_at' => 'Compilato il',
        'field_label' => 'Etichetta del campo',
        'field_type' => 'Tipo di campo',
        'field_required' => 'Obbligatorio',
        'field_options' => 'Opzioni',
        'field_help' => 'Testo di aiuto',
        'field_unit' => 'Unità',
    ],

    'action' => [
        'create_template' => 'Crea modello',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'activate' => 'Attiva',
        'archive' => 'Archivia',
        'delete' => 'Elimina',
        'add_field' => 'Aggiungi campo',
        'remove_field' => 'Rimuovi campo',
        'fill' => 'Compila modulo',
        'submit' => 'Invia',
        'show' => 'Visualizza',
        'print' => 'Stampa',
        'back' => 'Indietro',
    ],

    'filter' => [
        'all' => 'Tutti',
        'search' => 'Ricerca',
        'search_placeholder' => 'Cerca nome del modello',
        'period' => 'Periodo',
    ],

    'hint' => [
        'options' => 'Separate da virgola, ad es. buono, medio, scarso',
        'unit' => 'ad es. kWh, °C, pezzi',
    ],

    'subject_kind' => [
        'diary' => 'Incarico',
        'customer' => 'Cliente',
        'asset' => 'Asset',
        'project' => 'Progetto',
    ],

    'value' => [
        'yes' => 'Sì',
        'no' => 'No',
    ],

    'validation' => [
        'invalid_row' => 'La definizione del campo nella riga :row non è valida.',
        'label_required' => 'Il campo :row necessita di un’etichetta (max. 160 caratteri).',
        'unknown_type' => 'Il campo :row ha un tipo sconosciuto.',
        'invalid_key' => 'La chiave del campo «:key» non è valida (minuscole, cifre, trattini bassi).',
        'duplicate_key' => 'La chiave del campo «:key» è usata più volte.',
        'select_needs_options' => 'Il campo di selezione «:label» necessita di almeno un’opzione.',
        'fields_required' => 'Il modello necessita di almeno un campo.',
        'too_many_fields' => 'Al massimo :max campi per modello.',
        'template_not_active' => 'Questo modello non è attivo e non può essere compilato.',
    ],

    'flash' => [
        'template_created' => 'Il modello è stato creato.',
        'template_updated' => 'Il modello è stato aggiornato.',
        'template_activated' => 'Il modello è stato attivato.',
        'template_archived' => 'Il modello è stato archiviato.',
        'template_deleted' => 'Il modello è stato eliminato.',
        'submitted' => 'Il modulo è stato salvato.',
    ],

    'empty_templates_title' => 'Nessun modello trovato',
    'empty_templates' => 'Ancora nessun modello di modulo.',
    'empty_submissions_title' => 'Nessun modulo trovato',
    'empty_submissions' => 'Ancora nessun modulo compilato.',
    'empty_filtered' => 'Nessuna voce trovata per i filtri attuali.',
    'empty_panel' => 'Ancora nessun modulo compilato per questo elemento.',
    'confirm_archive' => 'Archiviare davvero questo modello? Sparirà dalla selezione di compilazione.',
    'confirm_delete' => 'Eliminare davvero questo modello? I moduli compilati vengono conservati.',
];
