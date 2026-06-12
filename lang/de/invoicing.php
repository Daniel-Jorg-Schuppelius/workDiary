<?php

return [
    'service' => 'Leistung',
    'service_on' => 'Leistung am :date',
    'hourly_rate' => 'Stundensatz',
    'unit_hour' => 'h',
    'unit_piece' => 'St.',
    'tax_rate' => 'MwSt-Satz',
    'currency' => 'Währung',
    'totals' => [
        'net' => 'Netto',
        'tax' => 'Steuer',
        'gross' => 'Brutto',
    ],

    // E-Rechnung (Feature 045, Abschnitt 8): XRechnung (UBL 2.1, EN 16931).
    'buyer_reference' => 'Leitweg-ID / Käuferreferenz (BT-10)',
    'buyer_reference_hint' => 'Pflichtangabe für die XRechnung (E-Rechnung): bei Behörden die Leitweg-ID, sonst eine vom Kunden vorgegebene Referenz.',
    'einvoice' => [
        'button' => 'XRechnung',
        'button_title' => 'XRechnung (UBL 2.1, EN 16931) herunterladen',
        'error_intro' => 'XRechnung kann nicht erzeugt werden:',
        'payment_terms' => 'Zahlbar innerhalb von :days Tagen ohne Abzug.',
        'exemption_small_business' => 'Keine Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).',
        'error' => [
            'status' => 'Die Rechnung muss gestellt oder bezahlt sein.',
            'no_items' => 'Die Rechnung enthält keine Positionen.',
            'missing_buyer_reference' => 'Beim Kunden fehlt die Leitweg-ID/Käuferreferenz (BT-10).',
            'missing_seller_field' => 'Verkäuferangabe fehlt: :field (Organisations-Einstellungen → Rechnungen).',
            'missing_tax_id' => 'Weder USt-IdNr. noch Steuernummer in den Organisations-Einstellungen hinterlegt.',
            'missing_iban' => 'IBAN für die SEPA-Überweisung fehlt in den Organisations-Einstellungen.',
            'missing_tax_rate' => 'Die Rechnung trägt keinen Steuersatz.',
            'totals_mismatch' => 'Die Rechnungssummen sind inkonsistent (Positionen, Zwischensumme, Steuer, Gesamt).',
        ],
        'warning' => [
            'missing_seller_contact' => 'Verkäufer-Kontakt unvollständig (Name, Telefon, E-Mail) — die XRechnung verlangt vollständige Kontaktangaben (BR-DE-2).',
            'missing_bic' => 'BIC fehlt (für SEPA-Überweisung empfohlen).',
            'buyer_address_incomplete' => 'Kundenanschrift unvollständig (Straße/PLZ/Ort).',
            'missing_buyer_email' => 'Kunden-E-Mail fehlt (elektronische Empfängeradresse BT-49).',
            'missing_due_date' => 'Fälligkeitsdatum fehlt — das Standard-Zahlungsziel wird verwendet.',
        ],
    ],
];
