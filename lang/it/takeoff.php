<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : takeoff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Aufmaßblatt (MVP-1058).
return [
    'title' => 'Misurazione',
    'back' => 'Indietro',
    'default_title' => 'Misurazione :carrier',
    'lines' => 'Righe di misurazione',
    'totals' => 'Quantità per posizione',
    'photos' => 'Foto e schizzi',
    'values' => 'Valori',
    'empty' => 'Ancora nessuna riga — aggiunga una formula con «Aggiungi riga».',
    'no_target' => '— non assegnata —',
    'pdf_note' => 'Calcolato con le formule della REB-VB 23.003. Valori in metri, angoli in gradi centesimali (cerchio completo = 400).',
    'formula' => [
        'Sum' => 'Pezzi / somma',
        'Triangle' => 'Triangolo',
        'Rectangle' => 'Rettangolo / parallelepipedo',
        'Trapezoid' => 'Trapezio',
        'Circle' => 'Cerchio / settore',
        'Mean' => 'Media',
        'Free' => 'Formula libera',
    ],
    'value' => [
        'amount' => 'Valore',
        'base' => 'Base',
        'height' => 'Altezza',
        'depth' => 'Profondità / altezza (solido, facoltativa)',
        'length' => 'Lunghezza',
        'width' => 'Larghezza',
        'side_a' => 'Lato a',
        'side_c' => 'Lato c',
        'radius' => 'Raggio',
        'angle' => 'Angolo in gradi centesimali (400 = cerchio completo)',
        'expression' => 'Espressione',
    ],
    'hint' => [
        'Sum' => 'I valori si sommano; un valore negativo sottrae.',
        'Triangle' => 'Base × altezza ÷ 2; con profondità come solido.',
        'Rectangle' => 'Lunghezza × larghezza; con profondità/altezza come solido.',
        'Trapezoid' => '(a + c) ÷ 2 × altezza; con profondità come solido.',
        'Circle' => 'Raggio² × π × angolo ÷ 400; 400 è il cerchio completo.',
        'Mean' => 'Media aritmetica dei valori.',
        'Free' => 'Espressione con + − × ÷ e parentesi, virgola o punto decimale.',
        'factor' => 'Numero di parti uguali; negativo sottrae (ad es. −1 per una porta).',
        'label' => 'Locale, elemento o asse.',
        'unit' => 'Vuoto = unità della posizione o dell’articolo.',
    ],
    'col' => [
        'label' => 'Locale / elemento',
        'formula' => 'Formula',
        'values' => 'Valori',
        'factor' => 'Fattore',
        'quantity' => 'Quantità',
        'target' => 'Posizione',
    ],
    'field' => [
        'title' => 'Denominazione',
        'measured_on' => 'Misurato il',
        'note' => 'Nota',
        'boq_item' => 'Posizione del computo',
        'article' => 'Articolo / prestazione',
        'description' => 'Descrizione (senza articolo)',
        'unit' => 'Unità',
    ],
    'action' => [
        'create' => 'Nuova misurazione',
        'edit' => 'Modifica',
        'pdf' => 'PDF',
        'delete' => 'Elimina',
        'add_line' => 'Aggiungi riga',
    ],
    'transition' => [
        'completed' => 'Chiudi',
        'draft' => 'Riapri',
    ],
    'confirm' => [
        'completed' => 'Chiudere la misurazione? Le righe vengono bloccate e le quantità diventano trasferibili.',
        'draft' => 'Riaprire la misurazione? Le quantità già trasferite non cambiano.',
        'delete' => 'Eliminare la misurazione con tutte le righe?',
        'delete_line' => 'Rimuovere davvero questa riga?',
    ],
    'flash' => [
        'created' => 'Misurazione creata.',
        'saved' => 'Misurazione salvata.',
        'deleted' => 'Misurazione eliminata.',
        'status' => 'Stato modificato.',
        'line_saved' => 'Riga salvata.',
        'line_deleted' => 'Riga rimossa.',
    ],
    'error' => [
        'locked' => 'La misurazione è chiusa e non può più essere modificata.',
        'not_computable' => 'Con questi valori la formula non si può calcolare — controlli i valori obbligatori o l’espressione.',
        'not_found' => 'Misurazione non trovata.',
    ],
    'carrier' => [
        'section' => 'Misurazioni',
        'lines' => ':count riga|:count righe',
        'none' => 'Ancora nessuna misurazione.',
    ],
    'transfer' => [
        'title' => 'Trasferire le quantità',
        'action' => 'Trasferisci',
        'confirm' => [
            'quote' => 'Trasferire le quantità in un nuovo preventivo?',
            'invoice' => 'Trasferire le quantità in una bozza di fattura? Il PDF della misurazione viene allegato come documento.',
            'progress' => 'Registrare le quantità come avanzamento delle voci del computo?',
        ],
        'targets' => 'Trasferito in',
        'kind' => [
            'quote' => 'Come preventivo',
            'invoice' => 'Come bozza di fattura',
            'progress' => 'Come avanzamento del computo',
        ],
        'hint' => 'Ogni tipo una volta per misurazione; le posizioni senza prezzo ricevono 0 € e vanno completate nel documento.',
        'done' => 'Trasferito',
        'based_on' => 'Quantità secondo la misurazione «:title».',
        'document_title' => 'Misurazione :title',
        'progress_note' => 'Dalla misurazione «:title»',
        'flash' => [
            'quote' => 'Preventivo creato dalla misurazione.',
            'invoice' => 'Bozza di fattura creata dalla misurazione; il PDF della misurazione è allegato come documento.',
            'progress' => ':count posizioni del computo segnalate.',
        ],
        'error' => [
            'not_completed' => 'Chiuda prima la misurazione.',
            'already' => 'Già trasferito: :kind.',
            'no_customer' => 'All’ordine o al progetto non è collegato alcun cliente.',
            'empty' => 'La misurazione non contiene quantità.',
            'no_boq' => 'Nessuna riga è assegnata a una posizione del computo.',
        ],
    ],
    'chain' => [
        'label' => 'Misurazioni senza documento',
        'measured_on' => 'Misurato il :date',
    ],
    'presets' => [
        'label' => 'Modelli',
    ],
    'quick' => [
        'title' => 'Registrazione rapida',
        'hint' => 'Funziona anche offline — la riga viene inviata alla prossima connessione.',
        'photo' => 'Foto',
        'add' => 'Registra riga',
    ],
];
