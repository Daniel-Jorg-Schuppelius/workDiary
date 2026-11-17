<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Steuerfall der Übergabe (MVP-1712)
    'tax_case' => [
        'not_taxable_outside_third_country' => 'Lexoffice kennt für nicht im Inland steuerbare Leistungen nur „Dienstleistungen an Drittländer“. Liegt der Leistungsort in der EU (etwa ein Grundstück im EU-Ausland) oder in mehreren Gebieten, gibt es dort keine passende Steuerart — bitte in workDiary abrechnen oder den Beleg in Lexoffice von Hand erfassen.',
        'contact_required' => 'Für „:case“ verlangt Lexoffice einen dort angelegten Kontakt; der Kunde ist noch nicht mit Lexoffice verknüpft. Bitte zuerst den Kontakt übertragen.',
        'time_voucher' => 'Für den Steuerfall „:case“ kennt die Beleg-Schnittstelle von Lexoffice keine Buchungskategorie (etwa § 13b im Inland oder steuerfrei nach § 4 UStG). Bitte über die Faktura-Übergabe an Lexoffice übertragen — dort geht die passende Steuerart mit.',
        'time_voucher_mixed' => 'Die Zeiten ergeben mehrere Steuerfälle (:case); ein Beleg trägt nur einen. Bitte die Zeiten getrennt abrechnen, etwa über die Faktura-Übergabe je Steuerfall.',
        'time_voucher_currency' => 'Ein Lexoffice-Beleg führt Beträge nur in Euro, der Kunde rechnet in :currency ab. Bitte in workDiary abrechnen oder den Beleg in Lexoffice von Hand erfassen.',
    ],
    'error' => [
        'lexoffice_contact_missing' => 'Kein Lexoffice-Kontakt für den Kunden — bitte zuerst den Kontakt synchronisieren.',
        'lexoffice_delivery_no_customer' => 'Auslieferung ohne Kunde kann nicht als Lieferschein übergeben werden.',
        'lexoffice_delivery_not_linked' => 'Mit dieser Auslieferung ist kein Lexoffice-Lieferschein verknüpft.',
        'lexoffice_dunning_not_invoice' => 'Eine Mahnung kann nur zu einer Rechnung erstellt werden.',
        'lexoffice_not_configured' => 'Lexoffice ist für diese Organisation nicht konfiguriert (API-Key fehlt).',
        'lexoffice_outcome_unclear' => 'Ausgang der Lexoffice-Übergabe unklar (Zeitüberschreitung nach dem Senden) — nicht blind wiederholen; der nächste Lauf sucht den Entwurf über den Quellmarker.',
        'contact_billing_address' => 'Der Kontakt „:customer“ braucht in Lexoffice genau eine Rechnungsadresse, hat aber :count. Bitte die Adresse dort korrigieren und die Übergabe wiederholen.',
        'lexoffice_oc_no_customer' => 'Fertigungsauftrag ohne Kunde kann nicht als Auftragsbestätigung übergeben werden.',
        'lexoffice_oc_not_linked' => 'Mit diesem Fertigungsauftrag ist keine Lexoffice-Auftragsbestätigung verknüpft.',
        'lexoffice_quote_no_customer' => 'Fertigungsauftrag ohne Kunde kann nicht als Angebot übergeben werden.',
        'lexoffice_document_no_customer' => 'Ein Beleg ohne Kunde lässt sich nicht an Lexoffice übertragen.',
        'lexoffice_quote_not_linked' => 'Mit diesem Fertigungsauftrag ist kein Lexoffice-Angebot verknüpft.',
    ],
    'lexoffice' => [
        'introduction' => 'Unsere Lieferungen/Leistungen stellen wir Ihnen wie folgt in Rechnung.',
        'delivery_title' => 'Lieferschein',
        'transfer_marker' => 'Übergabenachweis :marker',
    ],
    'vouchers' => [
        'sync_now' => 'Belege jetzt abgleichen',
        'sync_confirm' => "Belege jetzt mit Lexoffice abgleichen? Der Lauf holt neue und geänderte Belege im Hintergrund; der tägliche Vollabgleich bleibt davon unberührt.",
        'sync_hint' => "Holt neue und geänderte Lexoffice-Belege im Hintergrund — manueller Gegenpart zum geplanten Beleg-Sync.",
    ],
];
