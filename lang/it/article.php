<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : article.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Articoli',
    'subtitle' => 'Anagrafica articoli canonica del tenant (prodotti, materiale, servizi).',
    'empty' => 'Nessun articolo creato finora.',
    'variants' => 'Varianti',
    'options' => 'Opzioni',
    'units' => 'Unità',
    'external_mappings' => 'Corrispondenze esterne',
    'tabs' => [
        'master' => 'Anagrafica',
    ],
    // Consuntivo per articolo (Feature 047, MVP-715).
    'costing' => [
        'title' => 'Consuntivo',
        'subtitle' => 'Materiale pianificato/effettivo, tempo pianificato/effettivo e costo unitario sugli ordini di produzione completati nel periodo.',
        'per_order' => 'Per ordine di produzione',
        'sum' => 'Totale',
        'empty' => 'Nessun ordine di produzione completato nel periodo.',
        'open_order' => 'Apri ordine di produzione',
        'note' => 'Costo materiale dai consumi registrati (altrimenti quantità × costo anagrafico), manodopera dalle ore assegnate × tariffa oraria interna. Quota scarti su tutte le segnalazioni dell’articolo.',
        'kpi' => [
            'orders' => 'Ordini',
            'unit_cost_avg' => 'Costo unitario medio',
            'unit_cost_range' => 'min :min · max :max',
            'material' => 'Materiale effettivo',
            'planned' => 'Pianificato: :value',
            'deviation' => 'Scostamento materiale',
            'minutes' => 'Minuti effettivi',
            'scrap_rate' => 'Quota scarti',
            'scrap_hint' => ':scrap su :produced prodotti',
        ],
        'col' => [
            'order' => 'Ordine',
            'completed_at' => 'Completato il',
            'planned_material' => 'Materiale pianif.',
            'actual_material' => 'Materiale effettivo',
            'labor' => 'Manodopera',
            'total' => 'Totale',
            'minutes' => 'Min. eff. / pianif.',
            'good' => 'Quantità buona',
            'scrap' => 'Scarti',
            'unit_cost' => 'Costo unitario',
            'deviation' => 'Scost. %',
        ],
    ],
    'supplies' => [
        'title' => 'Fonti di approvvigionamento',
        'supplier' => 'Fornitore',
        'sku' => 'N. art. fornitore',
        'price' => 'Prezzo d\'acquisto',
        'lead_time' => 'Tempo di consegna',
        'moq' => 'Q.tà min.',
        'days' => 'giorni',
        'preferred' => 'Preferito',
        'recommended' => 'Consigliato',
        'set_preferred' => 'Imposta come preferito',
        'flash' => ['preferred_set' => 'Fonte di approvvigionamento preferita impostata.'],
    ],
    'no_options' => 'Nessuna opzione definita.',
    'no_variants' => 'Nessuna variante creata.',
    'sku_auto_hint' => 'assegnato automaticamente',

    'datanorm_oversized' => ':count numero articolo supera i 15 caratteri ed è escluso dall\'esportazione DATANORM.|:count numeri articolo superano i 15 caratteri e sono esclusi dall\'esportazione DATANORM.',

    'discount_group' => [
        'title' => 'Gruppi di sconto di vendita',
        'hint' => 'Condizioni standard dell\'organizzazione per le esportazioni DATANORM con prezzi di listino: i destinatari calcolano listino − sconto = netto. I prezzi per cliente passano dal DATPREIS B2B.',
        'empty' => 'Nessun gruppo di sconto ancora.',
        'confirm_delete' => 'Eliminare questo gruppo di sconto? Le assegnazioni degli articoli verranno rimosse.',
        'kind' => ['discount' => 'Sconto (%)', 'factor' => 'Fattore', 'surcharge' => 'Maggiorazione (%)'],
        'col' => ['code' => 'Codice', 'kind' => 'Tipo', 'value' => 'Valore', 'label' => 'Denominazione', 'articles' => 'Articoli'],
        'action' => ['add' => 'Crea', 'delete' => 'Elimina'],
        'flash' => ['created' => 'Gruppo di sconto creato.', 'deleted' => 'Gruppo di sconto eliminato.', 'override_saved' => 'Deroga cliente salvata.', 'override_deleted' => 'Deroga cliente eliminata.'],
        'override' => [
            'title' => 'Deroghe cliente',
            'hint' => 'Tassi specifici per cliente e gruppo di sconto — applicati nel DATPREIS B2B del cliente; un custom_price dell\'articolo prevale.',
            'customer' => 'Cliente',
            'empty' => 'Nessuna deroga cliente ancora.',
        ],
    ],

    'action' => [
        'create' => 'Crea articolo',
        'export_datanorm' => 'Esportazione DATANORM',
        'export_datanorm_v5_list' => 'DATANORM 5 — PV come prezzo di listino',
        'export_datanorm_v5_net' => 'DATANORM 5 — PV come prezzo netto',
        'export_datanorm_v4_list' => 'DATANORM 4 — PV come prezzo di listino',
        'export_datpreis_title' => 'File prezzi (DATPREIS)',
        'export_datpreis_v5' => 'DATPREIS 5 — PV attuali',
        'export_datpreis_v4' => 'DATPREIS 4 — PV attuali',
        'export_datpreis_since' => 'DATPREIS 5 — modifiche degli ultimi 30 giorni',
        'export_datpreis_custom' => 'DATPREIS da data',
        'edit' => 'Modifica articolo',
        'retire' => 'Disattiva',
        'add_option' => 'Aggiungi opzione',
        'add_value' => 'Valore',
        'add_variant' => 'Crea variante',
        'add_unit' => 'Aggiungi unità',
    ],

    'field' => [
        'sku' => 'Numero articolo (SKU)',
        'type' => 'Tipo articolo',
        'status' => 'Stato',
        'base_unit' => 'Unità base',
        'category' => 'Gruppo merceologico',
        'category_hint' => 'Per i report e l\'esportazione DATANORM (file WRG).',
        'subcategory' => 'Sottogruppo merceologico',
        'sales_discount_group' => 'Gruppo di sconto di vendita',
        'sales_discount_group_hint' => 'Per le esportazioni DATANORM con prezzi di listino (file RAB).',
        'assembly_minutes' => 'Tempo di montaggio (minuti per unità)',
        'assembly_minutes_hint' => 'Tempo di lavoro calcolato; compilato dai record ARBA durante l\'adozione DATANORM.',
        'copper_weight' => 'Peso del rame (kg per unità)',
        'copper_weight_hint' => 'Per la maggiorazione rame a prezzo giornaliero (DEL) e i record Z dell\'export DATANORM.',
        'copper_base_price' => 'Base rame nel prezzo (€ per 100 kg)',
        'copper_base_price_hint' => 'Base DEL già inclusa nel prezzo di vendita (metodo tedesco).',
        'gtin' => 'GTIN',
        'default_purchase_price' => 'Prezzo d’acquisto (predefinito)',
        'default_sale_price' => 'Prezzo di vendita (predefinito)',
        'currency' => 'Valuta',
        'code' => 'Codice',
        'label' => 'Etichetta',
        'option_name' => 'Nome opzione',
        'combination' => 'Combinazione',
        'sale_price' => 'Prezzo di vendita',
        'unit_kind' => 'Tipo',
        'factor_to_base' => 'Fattore verso unità base',
        'external_id' => 'ID esterno',
        'sync_status' => 'Stato sincronizzazione',
    ],

    'group' => [
        'pricing' => 'Prezzi',
        'flags' => 'Proprietà',
    ],

    'flag' => [
        'stockable' => 'Stoccabile',
        'purchasable' => 'Acquistabile',
        'sellable' => 'Vendibile',
        'manufacturable' => 'Producibile',
        'batch_required' => 'Tracciato per lotto',
        'serial_required' => 'Tracciato per numero di serie',
        'shelf_life_required' => 'Scadenza richiesta',
    ],

    'type' => [
        'raw' => 'Materia prima',
        'consumable' => 'Materiale di consumo',
        'merchandise' => 'Merce',
        'semifinished' => 'Semilavorato',
        'finished' => 'Prodotto finito',
        'service' => 'Servizio',
    ],

    'status' => [
        'draft' => 'Bozza',
        'active' => 'Attivo',
        'retired' => 'Disattivato',
    ],

    'unit_kind' => [
        'base' => 'Base',
        'purchase' => 'Acquisto',
        'sale' => 'Vendita',
        'packaging' => 'Imballaggio',
    ],

    'confirm' => [
        'retire' => 'Disattivare davvero questo articolo? Anche le varianti verranno disattivate.',
        'delete' => 'Eliminare definitivamente questo articolo? Solo le bozze senza riferimenti sono eliminabili.',
    ],

    'tiers' => [
        'title' => 'Prezzi scaglionati',
        'hint' => 'Dalla quantità indicata il prezzo scaglionato sostituisce il PV standard; viaggia come record Z nell\'export DATANORM.',
        'min_qty' => 'Da quantità',
        'unit_price' => 'Prezzo unitario',
        'empty' => 'Nessun prezzo scaglionato.',
        'action' => ['add' => 'Aggiungi scaglione'],
        'flash' => ['saved' => 'Prezzo scaglionato salvato.', 'deleted' => 'Prezzo scaglionato eliminato.'],
    ],
    'flash' => [
        'datanorm_empty' => 'Nessun articolo esportabile (attivo e vendibile) disponibile.',
        'created' => 'Articolo creato.',
        'updated' => 'Articolo aggiornato.',
        'deleted' => 'Articolo eliminato.',
        'retired' => 'Articolo disattivato.',
        'delete_blocked' => 'Impossibile eliminare l’articolo: solo le bozze senza riferimenti sono eliminabili. Disattivarlo invece.',
        'option_added' => 'Opzione aggiunta.',
        'value_added' => 'Valore opzione aggiunto.',
        'unit_added' => 'Unità aggiunta.',
        'variant_added' => 'Variante creata.',
        'variant_retired' => 'Variante disattivata.',
    ],
];
