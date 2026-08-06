<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Materialkosten-Zuordnung & Gewinn (Umsatz − Materialkosten) an der Kundenakte.
return [
    'panel_title' => 'Materialkosten & Gewinn',
    'add_title' => 'Materialkosten zuordnen',
    'source' => 'Kostenquelle',
    'source_hint' => 'Einen Lexoffice-Einkaufsbeleg wählen oder einen freien Betrag erfassen.',
    'voucher' => 'Einkaufsbeleg',
    'voucher_hint' => 'Optional — Teilbetrag möglich; ein Beleg lässt sich auf mehrere Kunden aufteilen.',
    'manual_amount' => '— Freier Betrag —',
    'description' => 'Beschreibung',
    'description_hint' => 'Ohne Beleg Pflicht — benennt die Materialkosten.',
    'allocation' => 'Zuordnung',
    'amount' => 'Betrag',
    'amount_hint' => 'Dem Kunden zugeordneter (Teil-)Betrag.',
    'date' => 'Datum',
    'project' => 'Projekt',
    'project_hint' => 'Optional — für eine feinere Zuordnung.',
    'no_project' => '— Kein Projekt —',
    'source_lexoffice' => 'Lexoffice-Beleg',
    'revenue' => 'Umsatz (fakturiert)',
    'material_cost' => 'Materialkosten',
    'profit' => 'Gewinn (kalk.)',
    'margin' => 'Marge',
    'range_hint' => 'Werte im gewählten Zeitraum (:range).',
    'double_count_hint' => 'Betriebswirtschaftliche Sicht (ohne Gemeinkosten). Material entweder über Einkaufsbeleg ODER Lagerentnahme zuordnen — nicht beides für denselben Wareneinsatz.',
    'empty_hint' => 'Noch keine Materialkosten zugeordnet. Über „Materialkosten zuordnen" Belege oder freie Beträge dem Kunden zuweisen, um den Gewinn abzubilden.',
    'confirm_delete' => 'Diese Materialkosten-Zuordnung wirklich entfernen?',
    'delete' => 'Entfernen',
    'flash_saved' => 'Materialkosten zugeordnet.',
    'flash_deleted' => 'Materialkosten-Zuordnung entfernt.',
    'error_description_required' => 'Ohne Beleg bitte eine Beschreibung angeben.',
    'error_voucher_not_purchase' => 'Der gewählte Beleg ist kein Einkaufsbeleg.',
    'error_amount_over_voucher' => 'Der Betrag übersteigt den Belegbetrag.',
    'error_project_foreign' => 'Das Projekt gehört nicht zu diesem Kunden.',
    // Lagerentnahme -> Materialkosten
    'stock_title' => 'Aus Lager entnehmen',
    'stock_issue' => 'Entnehmen & buchen',
    'stock_source' => 'Lagerentnahme',
    'stock_hint' => 'Bewertung zum gleitenden Durchschnitt; die Entnahme mindert den Bestand und wird als Materialkosten gebucht.',
    'article' => 'Artikel',
    'warehouse' => 'Lagerort',
    'qty' => 'Menge',
    'qty_hint' => 'In Basiseinheit.',
    'choose' => '— Bitte wählen —',
    'source_stock' => 'Lager',
    'stock_item' => 'Lagerartikel',
    'flash_stock_issued' => 'Lagerentnahme gebucht und als Materialkosten zugeordnet.',
    'book_to_customer' => 'Materialkosten Kunde',
    'no_customer' => '— Kein Kunde —',
];
