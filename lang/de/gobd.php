<?php

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
