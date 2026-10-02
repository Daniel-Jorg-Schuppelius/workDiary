<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'service' => 'Leistung',
    'service_on' => 'Leistung am :date',
    'hourly_rate' => 'Stundensatz',
    'unit_hour' => 'h',
    'unit_flat' => 'pausch.',
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
        'gaeb' => [
            'button' => 'GAEB (X89)',
            'button_title' => 'Rechnung als GAEB-Datei für Bau-Auftraggeber herunterladen',
        ],
        'zugferd' => [
            'button' => 'ZUGFeRD (PDF)',
            'button_title' => 'ZUGFeRD-PDF (PDF/A-3, EN 16931) herunterladen',
            'error_intro' => 'ZUGFeRD-PDF kann nicht erzeugt werden:',
            'unavailable' => 'ZUGFeRD-PDF-Erzeugung ist auf diesem System nicht verfügbar (php-pdf-toolkit fehlt).',
            'failed' => 'Die ZUGFeRD-PDF-Erzeugung ist fehlgeschlagen.',
        ],
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

    // Rechnungs-Vorschau im Erstell-Dialog (MVP-462).
    'source_times' => ':count Quell-Zeiteintrag anzeigen|:count Quell-Zeiteinträge anzeigen',
    'preview' => [
        'heading' => 'Vorschau:',
        'empty' => 'Für die gewählten Filter gibt es keine abrechenbaren Zeiten oder Anfahrten.',
        'entry_count' => ':count Eintrag|:count Einträge',
        'travel' => '+ :count Anfahrt(en)',
        'warning_late' => ':count Nachzügler: Leistungsdatum liegt in einem bereits abgerechneten Zeitraum.|:count Nachzügler: Leistungsdaten liegen in bereits abgerechneten Zeiträumen.',
        'column' => [
            'description' => 'Position',
            'duration' => 'Dauer',
            'rate' => 'Satz',
            'amount' => 'Betrag',
        ],
        'entries_heading' => 'Einzelne Zeiteinträge anzeigen/ausschließen',
        'exclude' => 'ausschließen',
        'exclude_hint' => 'Ausgeschlossene Einträge bleiben offen und erscheinen im nächsten Rechnungslauf wieder.',
    ],
    // Girocode/EPC-QR auf dem Rechnungs-PDF (Feature 111, MVP-600).
    'girocode' => [
        'alt' => 'Girocode zur Zahlung',
        'hint' => 'Mit der Banking-App scannen',
    ],
    // Belegkette (MVP-1057).
    'chain' => [
        'title' => 'Abzurechnen und nachzufassen',
        'description' => 'Was zwischen Angebot, Leistung und Rechnung noch aussteht — aus allen Modulen.',
        'open' => 'Öffnen',
        'empty_title' => 'Nichts offen',
        'empty' => 'Alle angenommenen Angebote sind abgerechnet, nichts ist zum Nachfassen fällig.',
        'nothing_here' => 'Nichts offen.',
        'more' => '… und :count weitere.',
        'quotes_to_invoice' => 'Angenommene Angebote ohne Rechnung',
        'quotes_follow_up' => 'Angebote nachfassen',
        'invoices_overdue' => 'Überfällige Rechnungen',
        'unbilled_time' => 'Abrechenbare Zeiten je Kunde',
        'boq_progress' => 'Leistungsstand über Abschlägen (LV)',
        'accepted_on' => 'angenommen am :date',
        'expired_on' => 'Bindefrist abgelaufen am :date',
        'follow_up_on' => 'Wiedervorlage am :date',
        'due_on' => 'fällig seit :date',
        'no_customer' => 'ohne Kunde',
        'unbilled_detail' => ':entries Einträge · :duration',
        'boq_detail' => 'Leistungsstand :progress %',
        'col' => [
            'document' => 'Beleg',
            'status' => 'Stand',
            'amount' => 'Betrag (netto)',
        ],
    ],
    // Gliederung von Angebot und Rechnung (MVP-1054).
    'line_kind' => [
        'item' => 'Position',
        'title' => 'Titel',
        'text' => 'Text',
        'alternative' => 'Alternative',
        'subtotal' => 'Summe Titel :number :title',
        'add_title' => 'Titel hinzufügen',
        'add_text' => 'Text hinzufügen',
        'add_alternative' => 'Alternative hinzufügen',
        'alternative_marker' => 'Alternativposition — zählt nur, wenn Sie sie wählen',
        'alternative_hint' => 'Wahlposition: zählt nicht zur Angebotssumme, bis der Kunde sie anstelle einer anderen Position wählt.',
        'position_hint' => 'Bestimmt die Reihenfolge; Titel nummerieren die folgenden Positionen.',
    ],
    // Arbeitskosten nach § 35a EStG (MVP-1053).
    'labour_costs' => [
        'disclosure' => [
            'off' => 'Nie ausweisen',
            'private_customers' => 'Bei Privatkunden ausweisen (ohne Firma und USt-IdNr.)',
            'always' => 'Immer ausweisen',
        ],
        'share' => 'Arbeitsanteil § 35a EStG (%)',
        'share_hint' => 'Anteil der Arbeits-, Maschinen- und Fahrtkosten an dieser Position. Material zählt nicht. Leer = nicht bestimmt.',
        'override' => 'Arbeitskosten nach § 35a EStG ausweisen',
        'override_hint' => 'Gilt nur für diesen Beleg; ohne Auswahl entscheidet die Einstellung der Organisation.',
        'override_default' => 'Nach Einstellung der Organisation (:rule)',
        'override_on' => 'Ausweisen',
        'override_off' => 'Nicht ausweisen',
        'pdf_line' => 'Im Rechnungsbetrag enthaltene Arbeits-, Maschinen- und Fahrtkosten (§ 35a EStG): :gross :currency, darin :tax :currency Umsatzsteuer.',
        'pdf_line_final' => 'In der Gesamtleistung enthaltene Arbeits-, Maschinen- und Fahrtkosten (§ 35a EStG), einschließlich der Abschlagsrechnungen: :gross :currency, darin :tax :currency Umsatzsteuer.',
        'pdf_line_quote' => 'Im Angebotsbetrag voraussichtlich enthaltene Arbeits-, Maschinen- und Fahrtkosten (§ 35a EStG): :gross :currency, darin :tax :currency Umsatzsteuer.',
        'einvoice_note' => 'Enthaltene Arbeits-, Maschinen- und Fahrtkosten nach § 35a EStG: :gross :currency brutto, darin :tax :currency Umsatzsteuer.',
        'undetermined' => ':count Position ohne bestimmten Arbeitsanteil|:count Positionen ohne bestimmten Arbeitsanteil',
    ],
    // Sicherheitseinbehalte § 17 VOB/B (Feature 113, MVP-602).
    'retention' => [
        'final_only' => 'Abschlags- und Teilrechnungen tragen keinen Sicherheitseinbehalt — er wird in der Schlussrechnung auf die Gesamtleistung berechnet.',
        'final_base_hint' => 'Schlussrechnung: Der Prozentsatz bezieht sich auf die Gesamtleistung vor Abzug der Abschläge; abgezogen wird vom Zahlbetrag.',
        'exceeds_after_settlement' => 'Die erfassten Sicherheitseinbehalte übersteigen den Zahlbetrag nach Anrechnung der Abschläge. Bitte Einbehalte anpassen.',
        'dialog_title' => 'Sicherheitseinbehalt hinterlegen',
        'submit' => 'Hinterlegen',
        'dialog_hint' => 'Der Einbehalt erscheint auf dem Beleg und wird aus dem offenen Posten herausgerechnet. Nach dem Ausstellen ist er nicht mehr änderbar.',
        'kind' => 'Art',
        'basis' => 'Bemessung',
        'basis_percent' => 'Prozentsatz der Rechnungssumme',
        'basis_amount' => 'Festbetrag',
        'base_kind' => 'Bemessungsgrundlage',
        'percent' => 'Prozentsatz',
        'amount' => 'Festbetrag',
        'due_on' => 'Zahlbar ab',
        'due_on_hint' => 'Ab diesem Tag ist der Einbehalt ein normaler offener Posten und wird wieder gemahnt.',
        'note' => 'Notiz',
        'heading' => 'Sicherheitseinbehalte',
        'action' => 'Einbehalt hinterlegen',
        'release' => 'Freigeben',
        'column_kind' => 'Art',
        'column_amount' => 'Betrag',
        'column_due' => 'Zahlbar ab',
        'column_status' => 'Status',
        'payable' => 'Zahlbetrag',
        'locked' => 'Sicherheitseinbehalte lassen sich nur am Rechnungsentwurf ändern — sie stehen auf dem Beleg und sind nach dem Ausstellen Teil des eingefrorenen Stands.',
        'needs_one_basis' => 'Bitte entweder einen Prozentsatz ODER einen Festbetrag angeben.',
        'no_total' => 'Der Beleg hat noch keine Summe, auf die sich ein Einbehalt beziehen könnte.',
        'amount_positive' => 'Der Einbehalt muss größer als null sein.',
        'exceeds_total' => 'Die Einbehalte übersteigen die Rechnungssumme.',
        'not_open' => 'Dieser Einbehalt ist nicht mehr offen.',
        'pdf_line' => 'abzüglich :basis :kind gem. § 17 VOB/B',
        'pdf_due' => 'zahlbar ab :date',
        'pdf_payable' => 'Zahlbetrag',
        'dunning_note' => 'abzüglich Sicherheitseinbehalt',
        'added' => 'Sicherheitseinbehalt hinterlegt.',
        'released' => 'Sicherheitseinbehalt freigegeben.',
    ],

    // Leistungszeitraum je Position (Feature 152, Review 2026-09-11).
    // Freie Rechnungen aus Artikeln, Material und Fertigung (Feature 160, MVP-856–859).
    'free' => [
        'title' => [
            'create' => 'Rechnung erstellen',
            'deliveries' => 'Auslieferungen übernehmen',
        ],
        'option' => [
            'manual' => 'Positionen selbst zusammenstellen (Artikel, Material, Fertigung)',
            'no_variant' => '— ohne Variante —',
        ],
        'field' => [
            'variant' => 'Variante',
            'delivery' => 'Auslieferung',
            'order' => 'Fertigungsauftrag',
            'delivered_on' => 'Geliefert am',
        ],
        'action' => [
            'attach_deliveries' => 'Auslieferung übernehmen',
            'open_order' => 'Fertigungsauftrag öffnen',
        ],
        'hint' => [
            'manual' => 'Kein Zeitraum, keine Zeiten: Der Entwurf startet leer; Positionen kommen aus Artikeln, Material, Freitext oder Fertigungsauslieferungen.',
            'empty_draft' => 'Fügen Sie Positionen hinzu oder übernehmen Sie eine Auslieferung. Ein leerer Entwurf lässt sich weder stellen noch versenden.',
            'variant' => 'Optional; die Variante belegt Preis und Artikelnummer vor.',
            'no_stock_movement' => 'Artikel- und Freitextpositionen buchen keinen Lagerbestand; eine Lieferung wird über Lager/Auslieferung erfasst.',
            'price_required' => 'Bewusst eingeben — auch 0,00 für eine Gratisposition.',
            'currency_mismatch' => 'Der Artikelpreis steht in einer anderen Währung als der Beleg; bitte den Preis in der Belegwährung eingeben.',
            'deliveries' => 'Gelieferte, noch nicht abgerechnete Auslieferungen des Kunden im Kopfzeilen-Zeitraum :from – :to; jede Auslieferung wird vollständig als eine Position übernommen.',
            'deliveries_empty' => 'Erzeugnisse ohne Auslieferung verkaufen Sie als freie Artikelposition.',
            'deliveries_rules' => 'Menge ist quellengebunden, der Verkaufspreis stammt aus der Auslieferung und bleibt im Entwurf änderbar. Entfernen der Position oder Verwerfen des Entwurfs gibt die Auslieferung frei; Lagerbestand bleibt unberührt.',
        ],
        'label' => [
            'from_order' => 'Fertigungsauftrag :number',
            'source_delivery' => 'Auslieferung vom :date · :order · gelieferte Menge :quantity',
            'reserved' => 'im Entwurf reserviert',
        ],
        'empty' => [
            'deliveries' => 'Keine offenen Auslieferungen.',
        ],
        'flash' => [
            'draft_created' => 'Rechnungsentwurf erstellt — fügen Sie jetzt Positionen hinzu.',
            'deliveries_attached' => ':count Auslieferung(en) übernommen.',
        ],
        'error' => [
            'empty' => 'Der Entwurf hat keine Positionen und kann weder gestellt noch versendet werden.',
            'variant_mismatch' => 'Die Variante gehört nicht zum gewählten Artikel.',
            'draft_only' => 'Auslieferungen lassen sich nur in einen normalen Rechnungsentwurf übernehmen.',
            'delivery_required' => 'Bitte mindestens eine Auslieferung wählen.',
            'delivery_foreign' => 'Die Auslieferung gehört nicht zu dieser Organisation.',
            'delivery_customer' => 'Die Auslieferung gehört zu einem anderen Kunden.',
            'delivery_external' => 'Die Auslieferung wird extern fakturiert.',
            'delivery_not_delivered' => 'Die Auslieferung ist noch nicht erfolgt.',
            'delivery_invoiced' => 'Die Auslieferung ist bereits abgerechnet.',
            'delivery_reserved' => 'Auslieferung „:name“ ist bereits im Entwurf :number reserviert.',
            'delivery_currency' => 'Die Auslieferung ist in :currency, der Beleg in :invoice — keine automatische Umrechnung.',
            'delivery_project' => 'Die Auslieferung gehört zu einem anderen Projekt.',
            'delivery_without_price' => 'Auslieferung „:name“ hat keinen Verkaufspreis — Preis am Artikel/der Variante pflegen oder als freie Position erfassen.',
        ],
    ],

    'item' => [
        'service_period' => 'Leistungszeitraum',
        'service_from' => 'Leistungszeitraum von',
        'service_to' => 'Leistungszeitraum bis',
    ],
    'service_rules' => [
        'title' => 'Abrechnungs-Regeln',
        'hint' => 'Pro Tätigkeitsart lässt sich festlegen, welcher Artikel bei Übergabe und Rechnungs-Export als Leistung verwendet wird — aus dem Artikelstamm oder einem angebundenen Buchhaltungsprogramm. Ohne Tätigkeitsart = Rückfall für alle Einträge. Unterprojekte erben Regeln vom übergeordneten Projekt, können sie aber überschreiben.',
    ],
];
