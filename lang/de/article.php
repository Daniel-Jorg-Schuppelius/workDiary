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
    'tabs' => [
        'master' => 'Stammdaten',
    ],
    // Nachkalkulation je Artikel (Feature 047, MVP-715).
    'costing' => [
        'title' => 'Nachkalkulation',
        'subtitle' => 'Plan/Ist-Material, Plan/Ist-Zeit und Stückkosten über die im Zeitraum abgeschlossenen Fertigungsaufträge.',
        'per_order' => 'Je Fertigungsauftrag',
        'sum' => 'Summe',
        'empty' => 'Keine abgeschlossenen Fertigungsaufträge im Zeitraum.',
        'open_order' => 'Fertigungsauftrag öffnen',
        'note' => 'Materialkosten aus Verbrauchsbuchungen (sonst Menge × Stammkosten), Lohn aus zugeordneten Zeitbuchungen × internem Stundensatz. Ausschussquote über alle Rückmeldungen des Artikels.',
        'kpi' => [
            'orders' => 'Aufträge',
            'unit_cost_avg' => 'Ø Stückkosten',
            'unit_cost_range' => 'min :min · max :max',
            'material' => 'Ist-Material',
            'planned' => 'Plan: :value',
            'deviation' => 'Materialabweichung',
            'minutes' => 'Ist-Minuten',
            'scrap_rate' => 'Ausschussquote',
            'scrap_hint' => ':scrap von :produced produziert',
        ],
        'col' => [
            'order' => 'Auftrag',
            'completed_at' => 'Abgeschlossen',
            'planned_material' => 'Plan-Material',
            'actual_material' => 'Ist-Material',
            'labor' => 'Lohn',
            'total' => 'Gesamt',
            'minutes' => 'Min. Ist / Plan',
            'good' => 'Gutmenge',
            'scrap' => 'Ausschuss',
            'unit_cost' => 'Stückkosten',
            'deviation' => 'Abw. %',
        ],
    ],
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

    'datanorm_oversized' => ':count Artikelnummer ist länger als 15 Zeichen und fällt aus dem DATANORM-Export.|:count Artikelnummern sind länger als 15 Zeichen und fallen aus dem DATANORM-Export.',

    'discount_group' => [
        'title' => 'Verkaufs-Rabattgruppen',
        'hint' => 'Org-weite Standard-Konditionen für den DATANORM-Export mit Listenpreisen: Empfänger rechnen Liste − Rabatt = Netto. Kundenindividuelle Preise laufen über den B2B-DATPREIS.',
        'empty' => 'Noch keine Rabattgruppen angelegt.',
        'confirm_delete' => 'Diese Rabattgruppe löschen? Artikel-Zuordnungen werden entfernt.',
        'kind' => ['discount' => 'Rabatt (%)', 'factor' => 'Faktor', 'surcharge' => 'Zuschlag (%)'],
        'col' => ['code' => 'Code', 'kind' => 'Art', 'value' => 'Wert', 'label' => 'Bezeichnung', 'articles' => 'Artikel'],
        'action' => ['add' => 'Anlegen', 'delete' => 'Löschen'],
        'flash' => ['created' => 'Rabattgruppe angelegt.', 'deleted' => 'Rabattgruppe gelöscht.', 'override_saved' => 'Kunden-Override gespeichert.', 'override_deleted' => 'Kunden-Override gelöscht.'],
        'override' => [
            'title' => 'Kunden-Overrides',
            'hint' => 'Kundenindividuelle Sätze je Rabattgruppe — wirken im kundenindividuellen B2B-DATPREIS; ein Artikel-custom_price bleibt stärker.',
            'customer' => 'Kunde',
            'empty' => 'Keine Kunden-Overrides angelegt.',
        ],
    ],

    'action' => [
        'create' => 'Artikel anlegen',
        'export_datanorm' => 'DATANORM-Export',
        'export_datanorm_v5_list' => 'DATANORM 5 — VK als Listenpreis',
        'export_datanorm_v5_net' => 'DATANORM 5 — VK als Nettopreis',
        'export_datanorm_v4_list' => 'DATANORM 4 — VK als Listenpreis',
        'export_datpreis_title' => 'Preisdatei (DATPREIS)',
        'export_datpreis_v5' => 'DATPREIS 5 — aktueller VK',
        'export_datpreis_v4' => 'DATPREIS 4 — aktueller VK',
        'export_datpreis_since' => 'DATPREIS 5 — Änderungen der letzten 30 Tage',
        'export_datpreis_custom' => 'DATPREIS seit Datum',
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
        'category' => 'Warengruppe',
        'category_hint' => 'Für Auswertungen und den DATANORM-Export (WRG-Datei).',
        'subcategory' => 'Unterwarengruppe',
        'sales_discount_group' => 'Verkaufs-Rabattgruppe',
        'sales_discount_group_hint' => 'Für den DATANORM-Export mit Listenpreisen (RAB-Datei).',
        'assembly_minutes' => 'Montagezeit (Minuten je Einheit)',
        'assembly_minutes_hint' => 'Kalkulatorische Arbeitszeit; wird bei der DATANORM-Übernahme aus ARBA-Sätzen gefüllt.',
        'copper_weight' => 'Kupfergewicht (kg je Einheit)',
        'copper_weight_hint' => 'Für den Tagespreis-Kupferzuschlag (DEL) und die Z-Sätze im DATANORM-Export.',
        'copper_base_price' => 'Kupferbasis im Preis (€ je 100 kg)',
        'copper_base_price_hint' => 'DEL-Basis, die im Verkaufspreis bereits enthalten ist (deutsche Methode).',
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

    'tiers' => [
        'title' => 'Staffelpreise',
        'hint' => 'Ab der Von-Menge ersetzt der Staffelpreis den Standard-VK; reist als Z-Satz im DATANORM-Export mit.',
        'min_qty' => 'Ab Menge',
        'unit_price' => 'Stückpreis',
        'empty' => 'Keine Staffelpreise gepflegt.',
        'action' => ['add' => 'Staffel hinzufügen'],
        'flash' => ['saved' => 'Staffelpreis gespeichert.', 'deleted' => 'Staffelpreis gelöscht.'],
    ],
    'flash' => [
        'datanorm_empty' => 'Keine exportierbaren Artikel (aktiv und verkäuflich) vorhanden.',
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
