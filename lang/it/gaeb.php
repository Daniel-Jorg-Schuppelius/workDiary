<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gaeb.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Computi metrici',
    'subtitle' => 'Importa computi GAEB e traccia le voci',
    'empty' => 'Nessun computo metrico importato finora.',
    'import_button' => 'Importa file GAEB',

    'columns' => [
        'name' => 'Denominazione',
        'project' => 'Progetto',
        'phase' => 'Fase',
        'version' => 'Versione GAEB',
        'items' => 'Voci',
        'reference_no' => 'Rif.',
        'short_text' => 'Testo breve',
        'quantity' => 'Quantità',
        'unit' => 'Unità',
        'unit_price' => 'PU',
        'total_price' => 'Totale',
        'type' => 'Tipo',
        'status' => 'Stato',
        'executed' => 'Misurazione',
        'remaining' => 'Residuo',
    ],

    'import' => [
        'title' => 'Importa file GAEB',
        'file' => 'File GAEB DA XML',
        'file_hint' => 'GAEB DA XML 3.x (es. .x81, .x83, .x86 o .xml).',
        'project' => 'Progetto (facoltativo)',
        'project_none' => '— nessun progetto —',
        'name' => 'Denominazione (facoltativa)',
        'name_hint' => 'Sostituisce il nome del progetto presente nel file.',
        'submit' => 'Importa',
        'status' => [
            'pending' => 'In verifica',
            'preflight_failed' => 'Controllo preliminare fallito',
            'imported' => 'Importato',
            'conflict' => 'Conflitto',
        ],
    ],

    'show' => [
        'positions' => 'Voci',
        'history' => 'Cronologia importazioni',
        'no_imports' => 'Nessuna importazione registrata.',
        'imported_at' => 'Importato il',
        'back' => 'Torna all’elenco',
    ],

    'phase' => [
        '81' => 'Computo metrico',
        '82' => 'Stima dei costi',
        '83' => 'Richiesta di offerta',
        '84' => 'Presentazione offerta',
        '85' => 'Offerta alternativa',
        '86' => 'Aggiudicazione',
    ],

    'item' => [
        'type' => [
            'standard' => 'Voce normale',
            'alternative' => 'Voce alternativa',
            'optional' => 'Voce opzionale',
            'lump_sum' => 'Voce a corpo',
            'markup' => 'Voce di maggiorazione',
            'note' => 'Nota',
        ],
        'status' => [
            'draft' => 'Bozza',
            'imported' => 'Importato',
            'quoted' => 'Offerto',
            'ordered' => 'Ordinato',
            'in_progress' => 'In corso',
            'completed' => 'Completato',
            'replaced' => 'Sostituito',
            'cancelled' => 'Annullato',
        ],
    ],

    'preflight' => [
        'version_unknown' => 'Impossibile rilevare la versione GAEB.',
        'version_unsupported' => 'La versione GAEB :version non è supportata (linea obiettivo 3.3).',
        'phase_unknown' => 'La fase di scambio «:code» è sconosciuta.',
        'no_items' => 'Il file non contiene voci.',
        'item_missing_ref' => 'Voce senza numero d’ordine: :text',
        'duplicate_ref' => 'Il numero d’ordine :ref compare più volte.',
        'missing_quantity' => 'La voce :ref non ha quantità.',
        'non_positive_quantity' => 'La voce :ref ha una quantità ≤ 0.',
        'missing_unit' => 'La voce :ref non ha unità.',
        'missing_price' => 'La voce :ref non ha prezzo unitario in una fase con prezzi.',
        'missing_text' => 'La voce :ref non ha testo breve/lungo.',
    ],

    'flash' => [
        'imported' => 'Computo metrico importato con :items voci.',
        'preflight_failed' => 'Importazione interrotta: :count errori nel controllo preliminare. Nessuna voce scritta.',
        'conflict' => 'Reimportazione interrotta: le voci in esecuzione (:refs) verrebbero sovrascritte.',
    ],

    'progress' => [
        'title' => 'Misurazione / avanzamento',
        'record' => 'Registra misurazione',
        'quantity' => 'Quantità',
        'note' => 'Nota',
        'source' => [
            'manual' => 'Manuale',
            'measurement' => 'Misurazione',
            'protocol' => 'Verbale',
            'material' => 'Consumo materiale',
        ],
        'flash' => [
            'recorded' => 'Misurazione registrata.',
        ],
    ],

    'mapping' => [
        'title' => 'Collegamento',
        'add' => 'Collega',
        'target_type' => 'Tipo destinazione',
        'article' => 'Articolo',
        'material' => 'Materiale',
        'factor' => 'Fattore',
        'flash' => [
            'linked' => 'Voce collegata.',
        ],
    ],

    'workflow' => [
        'status' => 'Imposta stato',
        'add_addendum' => 'Aggiungi variante',
        'remaining_title' => 'Lavori residui',
        'no_remaining' => 'Nessun lavoro residuo aperto.',
        'flash' => [
            'item_updated' => 'Stato voce modificato.',
            'bill_updated' => 'Stato computo modificato.',
            'addendum_added' => 'Variante aggiunta.',
        ],
    ],

    'costing' => [
        'title' => 'Analisi costi',
        'planned' => 'Previsto',
        'executed' => 'Effettivo (misurato)',
        'remaining' => 'Residuo',
        'progress' => 'Avanzamento',
    ],

    'export' => [
        'button' => 'Esporta GAEB',
        'title' => 'Esportazione GAEB',
        'phase' => 'Fase',
    ],
];
