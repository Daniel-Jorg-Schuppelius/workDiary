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
        'flash' => ['preferred_set' => 'Fonte di approvvigionamento preferita impostata.', 'datanorm_empty' => 'Nessun articolo esportabile (attivo e vendibile) disponibile.'],
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
        'flash' => ['created' => 'Gruppo di sconto creato.', 'deleted' => 'Gruppo di sconto eliminato.'],
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

    'flash' => [
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
