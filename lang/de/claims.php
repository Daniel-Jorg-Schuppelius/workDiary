<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Auffällige Muster (Serienfehler, Chargen)',
        'hint' => 'Gruppen mit mindestens :threshold Reklamationen im Zeitraum. Hinweis, keine Entscheidung.',
        'none' => 'Keine auffälligen Muster im Zeitraum.',
        'rule_label' => 'Regel',
        'group' => 'Gruppe',
        'cases' => 'Fälle',
        'count' => 'Anzahl',
        'rule' => [
            'lot' => 'Charge',
            'article_defect' => 'Artikel × Mangelart',
            'article_cause' => 'Artikel × Ursache',
            'supplier_defect' => 'Lieferant × Mangelart',
            'entry_type_cause' => 'Auftragsart × Ursache',
        ],
        'notify_title' => 'Auffälliges Reklamationsmuster: :label',
        'notify_message' => ':count Reklamationen in :days Tagen (:rule).',
    ],
    // Lagerbezug der Rückläufer (MVP-1576).
    'rma' => [
        'group_return' => 'Rücksendung',
        'group_receipt' => 'Wareneingang',
        'group_stock' => 'Lagerbezug',
        'hint_stock' => 'Mit Lager, Variante und Menge bucht der Wareneingang den Bestand in die Quarantäne. Ohne Variante wird der Rückläufer nur dokumentiert.',
        'hint_warehouse' => 'Für die Bestandsbuchung nötig, sobald eine Variante gewählt ist.',
        'hint_serial' => 'Die Auswahl legt Variante und Menge 1 fest.',
        'option' => ['no_stock' => '— ohne Lagerartikel —'],
        'without_stock' => 'ohne Lagerbezug — keine Bestandsbuchung',
        'no_stock_module' => 'Das Lager-Modul ist nicht aktiv. Der Rückläufer wird ohne Bestandsbuchung erfasst.',
        'disposition_books' => 'Die Entscheidung bucht den Bestand aus der Quarantäne um oder aus.',
        'disposition_documents' => 'Dieser Rückläufer hat keinen Lagerbezug. Die Entscheidung wird ohne Bestandsbuchung erfasst.',
        'flash' => [
            'received_booked' => 'Wareneingang in die Quarantäne (:state) gebucht.',
            'received_unbooked' => 'Wareneingang ohne Bestandsbuchung erfasst (kein Lagerartikel oder Lager-Modul nicht aktiv).',
            'disposition_booked' => 'Verwendungsentscheidung gebucht.',
            'disposition_unbooked' => 'Verwendungsentscheidung ohne Bestandsbuchung erfasst.',
        ],
        'error' => [
            'serial_variant' => 'Die Seriennummer gehört zu einer anderen Artikelvariante.',
            'serial_qty' => 'Ein Rückläufer mit Seriennummer hat die Menge 1.',
            'lot_variant' => 'Die Charge gehört zu einer anderen Artikelvariante.',
        ],
    ],
    // Bezugsobjekte, Einstieg, Finanzfolge und Regress der Akte (MVP-1577).
    'form' => [
        'title_edit' => 'Reklamation bearbeiten',
        'lot_article_mismatch' => 'Die Charge gehört zu einem anderen Artikel.',
        'group_links' => 'Betroffene Objekte',
        'hint_links' => 'Verknüpfen Sie, worauf sich die Reklamation bezieht. Mit gewähltem Kunden zeigen die Listen nur dessen Objekte.',
        'hint_serial' => 'Eine im Lager bekannte Seriennummer wird der Akte zugeordnet.',
        'serial_linked' => 'Seriennummer ist im Lager bekannt und verknüpft.',
    ],
    'open' => [
        'action' => 'Reklamation eröffnen',
    ],
    'financial' => [
        'hint_invoice_local' => 'Gutschrift, Storno, Minderung und Korrektur entstehen aus dieser Rechnung. Storno setzt eine ausgestellte offene, Gutschrift eine bezahlte Rechnung voraus.',
        'hint_invoice_external' => 'Die Rechnungen dieses Kunden führt ein externes System. Der Korrekturbeleg entsteht dort, die Belegnummer tragen Sie hier nach.',
        'source_invoice' => 'zu Rechnung :number',
        'error' => [
            'invoice_required' => 'Wählen Sie die Rechnung, aus der der Korrekturbeleg entstehen soll.',
            'not_cancellable' => 'Rechnung :number lässt sich nicht stornieren. Storno setzt eine ausgestellte, unbezahlte Rechnung voraus.',
            'not_creditable' => 'Zu Rechnung :number lässt sich keine Gutschrift erstellen. Sie muss bezahlt sein und darf noch keine Korrektur haben.',
            'not_rejectable' => 'Nur vorgeschlagene Folgen lassen sich ablehnen.',
        ],
        'reject' => 'Ablehnen',
        'reject_confirm' => 'Vorschlag ablehnen? Er endet ohne Beleg; ein neuer Vorschlag bleibt möglich.',
        'flash' => [
            'rejected' => 'Vorschlag abgelehnt.',
        ],
    ],
    'recourse' => [
        'group_claim' => 'Anspruch',
        'group_reference' => 'Bezug',
        'hint_due_days' => 'Ohne Angabe gelten :days Tage ab dem Einreichen.',
        'hint_terms' => 'Zum Beispiel Garantiedauer, Ausschlüsse oder Rügefrist laut Liefervertrag.',
        'field_incoming_invoice' => 'Eingangsrechnung',
        'due_days' => 'Antwortfrist: :days Tage ab Einreichen',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Retourenlabel',
        'create' => 'Retourenlabel erstellen',
        'download' => 'Label herunterladen',
        'created' => 'Retourenlabel erstellt (Sendung :tracking).',
        'no_address' => 'Für das Retourenlabel fehlt die Anschrift des Kunden (Straße, PLZ, Ort).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'list' => [
            'claim' => 'Reklamation',
            'empty' => 'Noch keine Rücksendungen angemeldet.',
            'rma' => 'Rücksendenummer',
            'status' => 'Status',
            'title' => 'Meine Rücksendungen',
        ],
        'capability' => 'Rücksendung anmelden',
        'nav' => 'Rücksendung anmelden',
        'title' => 'Rücksendung anmelden',
        'intro' => 'Wählen Sie die Lieferung oder das Objekt, beschreiben Sie den Grund und fügen Sie bei Bedarf Fotos bei. Sie erhalten eine Rücksendenummer; ein Retourenlabel stellen wir bei Bedarf bereit.',
        'empty' => 'Für Ihr Konto liegen keine Lieferungen oder Objekte vor.',
        'submit' => 'Rücksendung anmelden',
        'label' => 'Retourenlabel herunterladen',
        'field' => [
            'delivery' => 'Lieferung',
            'asset' => 'Objekt',
            'serial_no' => 'Seriennummer',
            'quantity' => 'Menge',
            'title' => 'Kurzbeschreibung',
            'description' => 'Grund der Rücksendung',
            'photos' => 'Fotos oder Belege (höchstens 5)',
        ],
        'flash' => [
            'submitted' => 'Rücksendung angemeldet: Reklamation :number, Rücksendenummer :rma.',
        ],
        'error' => [
            'subject' => 'Bitte wählen Sie eine Lieferung oder ein Objekt.',
            'serial' => 'Diese Seriennummer gehört nicht zur gewählten Lieferung.',
        ],
    ],
    // Nachreichungen aus dem Kundenportal.
    'portal_note' => [
        'history' => 'Ihre Nachreichungen',
        'notification_title' => 'Nachreichung zu Reklamation :number',
    ],
    'action' => [
        'follow_up_order' => 'Folgeauftrag',
        'follow_up_none' => '— kein Folgeauftrag —',
    ],
];
