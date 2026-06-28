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
    'title' => 'Artikel',
    'subtitle' => 'Kanonischer Artikelstamm des Mandanten (Produkte, Material, Leistungen).',
    'empty' => 'Noch keine Artikel angelegt.',
    'variants' => 'Varianten',
    'options' => 'Optionen',
    'units' => 'Einheiten',
    'external_mappings' => 'Externe Zuordnungen',
    'supplies' => [
        'title' => 'Bezugsquellen',
        'supplier' => 'Lieferant',
        'sku' => 'Lief.-Art.-Nr.',
        'price' => 'EK-Preis',
        'lead_time' => 'Lieferzeit',
        'moq' => 'Mindestmenge',
        'days' => 'Tage',
        'preferred' => 'Bevorzugt',
        'recommended' => 'Empfohlen',
        'set_preferred' => 'Als bevorzugt setzen',
        'flash' => ['preferred_set' => 'Bevorzugte Bezugsquelle gesetzt.'],
    ],
    'no_options' => 'Keine Optionen definiert.',
    'no_variants' => 'Keine Varianten angelegt.',
    'sku_auto_hint' => 'wird automatisch vergeben',

    'action' => [
        'create' => 'Artikel anlegen',
        'edit' => 'Artikel bearbeiten',
        'retire' => 'Stilllegen',
        'add_option' => 'Option hinzufügen',
        'add_value' => 'Wert',
        'add_variant' => 'Variante anlegen',
        'add_unit' => 'Einheit hinzufügen',
    ],

    'field' => [
        'sku' => 'Artikelnummer (SKU)',
        'type' => 'Artikelart',
        'status' => 'Status',
        'base_unit' => 'Basiseinheit',
        'gtin' => 'GTIN',
        'default_purchase_price' => 'Einkaufspreis (Standard)',
        'default_sale_price' => 'Verkaufspreis (Standard)',
        'currency' => 'Währung',
        'code' => 'Code',
        'label' => 'Bezeichnung',
        'option_name' => 'Optionsname',
        'combination' => 'Kombination',
        'sale_price' => 'Verkaufspreis',
        'unit_kind' => 'Art',
        'factor_to_base' => 'Faktor zur Basiseinheit',
        'external_id' => 'Externe ID',
        'sync_status' => 'Sync-Status',
    ],

    'group' => [
        'pricing' => 'Preise',
        'flags' => 'Eigenschaften',
    ],

    'flag' => [
        'stockable' => 'Lagerfähig',
        'purchasable' => 'Einkaufbar',
        'sellable' => 'Verkaufbar',
        'manufacturable' => 'Herstellbar',
        'batch_required' => 'Chargenpflichtig',
        'serial_required' => 'Seriennummernpflichtig',
        'shelf_life_required' => 'Mindesthaltbarkeit erforderlich',
    ],

    'type' => [
        'raw' => 'Rohstoff',
        'consumable' => 'Verbrauchsmaterial',
        'merchandise' => 'Handelsware',
        'semifinished' => 'Halbfabrikat',
        'finished' => 'Fertigerzeugnis',
        'service' => 'Leistung',
    ],

    'status' => [
        'draft' => 'Entwurf',
        'active' => 'Aktiv',
        'retired' => 'Stillgelegt',
    ],

    'unit_kind' => [
        'base' => 'Basis',
        'purchase' => 'Einkauf',
        'sale' => 'Verkauf',
        'packaging' => 'Verpackung',
    ],

    'confirm' => [
        'retire' => 'Artikel wirklich stilllegen? Varianten werden ebenfalls stillgelegt.',
        'delete' => 'Artikel endgültig löschen? Nur referenzlose Entwürfe sind löschbar.',
    ],

    'flash' => [
        'created' => 'Artikel angelegt.',
        'updated' => 'Artikel aktualisiert.',
        'deleted' => 'Artikel gelöscht.',
        'retired' => 'Artikel stillgelegt.',
        'delete_blocked' => 'Artikel kann nicht gelöscht werden: nur referenzlose Entwürfe sind löschbar. Bitte stattdessen stilllegen.',
        'option_added' => 'Option hinzugefügt.',
        'value_added' => 'Optionswert hinzugefügt.',
        'unit_added' => 'Einheit hinzugefügt.',
        'variant_added' => 'Variante angelegt.',
        'variant_retired' => 'Variante stillgelegt.',
    ],
];
