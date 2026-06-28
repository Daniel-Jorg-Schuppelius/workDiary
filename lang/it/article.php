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
        'flash' => ['preferred_set' => 'Fonte di approvvigionamento preferita impostata.'],
    ],
    'no_options' => 'Nessuna opzione definita.',
    'no_variants' => 'Nessuna variante creata.',
    'sku_auto_hint' => 'assegnato automaticamente',

    'action' => [
        'create' => 'Crea articolo',
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
