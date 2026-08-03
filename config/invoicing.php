<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Defaults for invoice generation. Override per organization via
 * Organization::settings['invoicing'].
 */

return [
    /** Standard VAT/tax rate applied to new invoices, e.g. "19.00". */
    'default_tax_rate' => (string) env('INVOICING_DEFAULT_TAX_RATE', '19.00'),

    /** ISO-4217 fallback currency when a customer has none configured. */
    'default_currency' => (string) env('INVOICING_DEFAULT_CURRENCY', 'EUR'),

    /** Default unit for time-based invoice positions. */
    'time_unit' => (string) env('INVOICING_TIME_UNIT', 'h'),

    /*
     * Organisationsweiter Standard-Stundensatz (Erlös), letzte Stufe der
     * Satzhierarchie im RateCalculator. null = kein Fallback (Zeiten ohne
     * gepflegten Satz bleiben bei 0,00 €).
     */
    'default_hourly_rate' => env('INVOICING_DEFAULT_HOURLY_RATE') !== null
        ? (float) env('INVOICING_DEFAULT_HOURLY_RATE')
        : null,

    /*
     * Standardleistung der Organisation: Fremd-ID eines Artikels im
     * Faktura-System (aktuell nur Lexoffice führt einen Artikelkatalog).
     * Projekt-Abrechnungsregeln überschreiben sie.
     */
    'default_service_article' => env('INVOICING_DEFAULT_SERVICE_ARTICLE'),
    'default_service_plugin' => env('INVOICING_DEFAULT_SERVICE_PLUGIN', 'lexoffice'),

    /*
     * Vorlagen für die Rechnungstexte einer Faktura-Übergabe (MVP-491).
     * Platzhalter: :customer, :from, :to, :channel. Leer = der bisherige
     * Standardtext je Ziel (finance.<ziel>.introduction).
     */
    'transfer_intro_text' => null,
    'transfer_closing_text' => null,
];
