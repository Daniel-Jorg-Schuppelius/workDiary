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

    'change_order' => [
        'phase' => [
            'CallChangOrder' => 'Richiesta di variante',
            'SupplBid' => 'Offerta di variante',
            'SupplAgree' => 'Accordo di variante',
        ],
        'initiator' => [
            'Owner' => 'Committente',
            'Contractor' => 'Appaltatore',
        ],
    ],
    'phase' => [
        '31' => 'Rilievo delle quantità',
        '50' => 'Catalogo dei costi di costruzione',
        '51' => 'Determinazione dei costi',
        '52' => 'Dati di calcolo',
        '80' => 'Dati universali del computo',
        '81' => 'Computo metrico',
        '82' => 'Stima dei costi',
        '83' => 'Richiesta di offerta',
        '84' => 'Presentazione dell\'offerta',
        '85' => 'Offerta alternativa',
        '86' => 'Aggiudicazione',
        '87' => 'Conferma d\'ordine',
        '89' => 'Fattura',
        '89B' => 'Documento giustificativo della fattura',
        '83Z' => 'Contratto quadro: richiesta di offerta',
        '84Z' => 'Contratto quadro: presentazione dell\'offerta',
        '86ZE' => 'Contratto quadro: ordine singolo',
        '86ZR' => 'Contratto quadro: ordine quadro',
        '93' => 'Richiesta di prezzo',
        '94' => 'Offerta di prezzo',
        '96' => 'Ordine',
        '97' => 'Conferma d\'ordine (commercio)',
    ],

    'item' => [
        'type' => [
            'standard' => 'Voce normale',
            'base' => 'Voce di base',
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
        'change_order_status' => [
            'Recog' => 'Riconosciuto',
            'Filed' => 'Notificato',
            'Offered' => 'Offerto',
            'Withdrawn' => 'Ritirato',
            'Rejected' => 'Respinto',
            'ObjToRecj' => 'Opposizione al rifiuto',
            'FormAckn' => 'Riconosciuto nel merito',
            'Approved' => 'Approvato',
        ],
    ],

    'preflight' => [
        'version_unknown' => 'Impossibile rilevare la versione GAEB.',
        'version_unsupported' => 'La versione GAEB :version non è supportata (linea obiettivo 3.3).',
        'phase_unknown' => 'La fase di scambio «:code» è sconosciuta.',
        'no_items' => 'Il file non contiene voci.',
        'vendor_record_type' => 'Il file contiene :count record del tipo proprietario :type — il loro contenuto non viene valutato (alcuni sistemi vi collocano i gruppi di costo).',
        'item_missing_ref' => 'Voce senza numero d’ordine: :text',
        'duplicate_ref' => 'Il numero d’ordine :ref compare più volte.',
        'missing_quantity' => 'La voce :ref non ha quantità.',
        'non_positive_quantity' => 'La voce :ref ha una quantità ≤ 0.',
        'missing_unit' => 'La voce :ref non ha unità.',
        'missing_price' => 'La voce :ref non ha prezzo unitario in una fase con prezzi.',
        'unpriced_item' => 'La voce :ref non ha prezzo né è contrassegnata come «non offerta» nell’offerta.',
        'priced_but_not_offered' => 'La voce :ref è contrassegnata come «non offerta» ma riporta un prezzo unitario.',
        'up_components_mismatch' => 'Voce :ref: la somma delle componenti del prezzo unitario (:sum) non corrisponde al prezzo unitario (:price).',
        'missing_text' => 'La voce :ref non ha testo breve/lungo.',
        'total_mismatch' => 'Il totale indicato (:stated) differisce dal totale ricalcolato (:computed).',
        'complement_empty' => 'Posizione :ref: l\'integrazione di testo dell\'offerente :mark non è compilata.',
        'contractor_missing' => 'Questa fase richiede l\'indirizzo dell\'offerente (nome, via, CAP e città nei dati anagrafici della fatturazione elettronica).',
    ],

    'flash' => [
        'imported' => 'Computo metrico importato con :items voci.',
        'preflight_failed' => 'Importazione interrotta: :count errori nel controllo preliminare. Nessuna voce scritta.',
        'conflict' => 'Reimportazione interrotta: le voci in esecuzione (:refs) verrebbero sovrascritte.',
    ],

    'progress' => [
        'from_takeoff' => 'Quantità ricalcolata da :lines righe di misurazione del X31.',
        'takeoff_skipped' => ':count righe con una formula non supportata sono state ignorate.',
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
    'trade' => [
        'missing_own_address' => 'Manca il proprio indirizzo nei dati di fatturazione elettronica; senza di esso il file non identifica il committente.',
        'missing_delivery_date' => 'Senza data di consegna l’ordine resta indeterminato; è stata usata la data d’ordine.',
        'missing_supplier_sku' => ':count riga/righe senza codice articolo del fornitore — il suo sistema trova la merce solo così.',
        'missing_supplier_tax_no' => 'Il fornitore non ha né codice fiscale né partita IVA.',
    ],
    'invoice' => [
        'share_net' => 'Importo netto',
        'share_discount' => 'Sconto',
        'share_vat' => 'IVA :rate %',
        'missing_tax_number' => 'Codice fiscale e partita IVA mancano nei dati di fatturazione elettronica — la legge fiscale ne richiede uno.',
        'missing_recipient' => 'L’indirizzo del destinatario della fattura è incompleto.',
        'missing_service_period' => 'Nessun periodo di prestazione deducibile; è stata usata la data della fattura.',
    ],
    'comparison' => [
        'title' => 'Confronto prezzi',
        'spread' => 'Scarto',
        'rank' => 'Posizione :rank',
        'gap' => ':percent % più conveniente dell’offerta successiva',
        'unusually_low_hint' => 'Un’offerta anomalamente bassa richiede chiarimenti, non l’esclusione (§ 16d VOB/A, § 60 VgV).',
        'incomplete_hint' => 'Non tutti gli offerenti hanno quotato ogni voce — i prezzi mancanti sono lacune, non zeri.',
        'empty_title' => 'Nessuna offerta',
        'empty_hint' => 'Importa le offerte (X84) di questa gara per confrontarle.',
        'button' => 'Confronto prezzi',
    ],
];
