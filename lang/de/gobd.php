<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gobd.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132).
 */

return [
    'title' => 'GoBD-Datenträgerüberlassung (Z3)',
    'subtitle' => 'Steuerrelevante Daten als GDPdU-Paket für die Betriebsprüfung (IDEA-lesbar).',
    'period' => 'Prüfungszeitraum',
    'sections' => 'Datenbereiche',
    'section' => [
        'invoices' => 'Ausgangsrechnungen',
        'invoice_items' => 'Rechnungspositionen',
        'customers' => 'Debitoren',
        'time_entries' => 'Zeitnachweise',
        'booking_batches' => 'Buchungsstapel',
        'booking_batch_items' => 'Buchungsstapel-Positionen',
        'payment_allocations' => 'Zahlungszuordnungen',
        'cash_entries' => 'Kassenbuch',
        'cash_daily_closings' => 'Kassenabschlüsse',
        'incoming_einvoices' => 'Eingangs-E-Rechnungen',
        'expenses' => 'Spesen',
    ],
    'preflight' => [
        'title' => 'Vorprüfung',
        'check' => 'Zeitraum prüfen',
        'records' => ':count Datensätze',
        'warnings' => 'Hinweise',
        'drafts' => ':count nicht festgeschriebene Rechnung(en) (Entwurf) im Zeitraum — steuerlich noch nicht final.',
        'draft_batches' => ':count nicht festgeschriebene(r) Buchungsstapel (Entwurf) im Zeitraum — fehlt im Buchungsstapel-Nachweis.',
        'empty_invoices' => 'Keine Ausgangsrechnungen im gewählten Zeitraum.',
    ],
    'export' => 'Z3-Paket herunterladen',
    'recent' => [
        'title' => 'Letzte Exporte',
        'package_hash' => 'Paket-Hash (SHA-256)',
        'records' => 'Datensätze',
        'created' => 'Erzeugt',
        'none' => 'Noch keine Exporte.',
    ],
    'encoding' => 'Zeichensatz der Datendateien',
];
