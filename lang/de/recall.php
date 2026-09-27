<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rückrufaktionen (MVP-921/922).
return [
    'title' => 'Rückrufaktionen',
    'nav' => 'Rückrufaktionen',
    'subtitle' => 'Rückruf je Artikelvariante: betroffene Auslieferungen und Kunden ermitteln, Lagerbestand sperren, Stand je Kunde führen.',
    'empty' => 'Keine Rückrufaktionen.',
    'items_none' => 'Keine betroffenen Auslieferungen.',
    'yes' => 'ja',
    'no' => 'nein',
    'kpi' => [
        'active' => 'Aktive Rückrufe',
    ],
    'filter' => [
        'all_status' => 'Alle Status',
    ],
    'field' => [
        'number' => 'Nummer',
        'title' => 'Bezeichnung',
        'variant' => 'Artikelvariante',
        'kind' => 'Anlass',
        'open_items' => 'Offen / betroffen',
        'status' => 'Status',
        'reason' => 'Grund und Maßnahme',
        'customer_message' => 'Nachricht an Kunden',
        'manufacturing_orders' => 'Fertigungsaufträge',
        'delivered_from' => 'Geliefert ab',
        'delivered_until' => 'Geliefert bis',
        'serial_numbers' => 'Seriennummern',
        'is_blocking_stock' => 'Lagerbestand der Eingrenzung sperren',
        'activated_at' => 'Aktiviert am',
        'delivered_at' => 'Geliefert am',
        'customer' => 'Kunde',
        'quantity' => 'Menge',
        'serial' => 'Seriennummer',
        'actions' => 'Aktionen',
        'claim' => 'Reklamation',
        'sent_at' => 'Versendet am',
        'recipient' => 'Empfänger',
    ],
    'hint' => [
        'customer_message' => 'Wird im Kundenportal und im Anschreiben verwendet.',
        'scope' => 'Leere Angaben grenzen nicht ein; alle gesetzten Angaben gelten zusammen.',
        'list' => 'Durch Komma oder je Zeile getrennt.',
        'claim' => 'Reklamation mit RMA für den Rücklauf eröffnen.',
    ],
    'section' => [
        'recall' => 'Rückruf',
        'scope' => 'Eingrenzung',
        'preview' => 'Vorschau der Betroffenen',
        'items' => 'Betroffene Auslieferungen',
        'dispatches' => 'Versandnachweise',
    ],
    'preview' => [
        'summary' => ':deliveries Auslieferungen betroffen, :stock Seriennummern im Lager',
        'none' => 'Keine Auslieferung in dieser Eingrenzung.',
    ],
    'action' => [
        'create' => 'Rückruf anlegen',
        'show' => 'Anzeigen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'notify' => 'Kunden anschreiben',
        'claim' => 'Reklamation',
    ],
    'dialog' => [
        'create' => 'Rückrufaktion anlegen',
        'edit' => 'Rückrufaktion bearbeiten',
    ],
    'transition' => [
        'active' => 'Aktivieren',
        'completed' => 'Abschließen',
        'cancelled' => 'Abbrechen',
    ],
    'confirm' => [
        'active' => 'Rückruf aktivieren? Die betroffenen Auslieferungen werden festgeschrieben und der Lagerbestand der Eingrenzung gesperrt.',
        'completed' => 'Rückruf abschließen?',
        'cancelled' => 'Rückruf abbrechen? Die Sperren dieses Rückrufs werden aufgehoben.',
        'notify' => 'Alle Kunden mit offenen Positionen per E-Mail anschreiben?',
    ],
    'item_transition' => [
        'notified' => 'Informiert',
        'returned' => 'Zurück',
        'resolved' => 'Erledigt',
    ],
    'status' => [
        'draft' => 'Entwurf',
        'active' => 'Aktiv',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Abgebrochen',
    ],
    'item_status' => [
        'open' => 'Offen',
        'notified' => 'Informiert',
        'returned' => 'Zurück',
        'resolved' => 'Erledigt',
    ],
    'kind' => [
        'safety' => 'Sicherheit',
        'quality' => 'Qualität',
        'regulatory' => 'Behördliche Vorgabe',
    ],
    'error' => [
        'not_draft' => 'Nur Entwürfe lassen sich ändern.',
        'not_active' => 'Anschreiben ist nur bei aktiven Rückrufen möglich.',
    ],
    'flash' => [
        'created' => 'Rückrufaktion :number angelegt.',
        'saved' => 'Rückrufaktion gespeichert.',
        'status' => 'Status: :status.',
        'item' => 'Stand gespeichert.',
        'notified' => ':count Kunden angeschrieben.',
        'without_email' => 'Ohne gültige E-Mail, bitte anders informieren: :customers',
        'claim' => 'Reklamation :number für den Rücklauf eröffnet.',
    ],
    'stats' => [
        'return_rate' => 'Rücklaufquote',
    ],
    'dispatch' => [
        'queued' => 'In Warteschlange',
        'sent' => 'Versendet',
        'failed' => 'Fehlgeschlagen',
        'none' => 'Noch keine Anschreiben versendet.',
    ],
    'mail' => [
        'subject' => 'Rückruf: :title (:number)',
        'body' => "Guten Tag :name,\n\nwir rufen folgendes Produkt zurück: :product.\n\n:message",
        'default_message' => 'Bitte verwenden Sie das Produkt nicht weiter und nehmen Sie Kontakt mit uns auf; wir besprechen Rücksendung oder Austausch.',
        'serials' => 'Betroffene Seriennummern: :serials',
    ],
    'claim' => [
        'title' => 'Rückruf :number: :title',
    ],
    'portal' => [
        'subject' => 'Rückruf: :title (:product)',
    ],
    // Behördenmeldung (MVP-945).
    'authority' => [
        'title' => 'Behördenmeldung',
        'save' => 'Speichern',
        'pdf' => 'Meldebogen',
        'pdf_title' => 'Meldebogen Rückruf',
        'pdf_note' => 'Zusammenstellung der Angaben für die Meldung an die Marktüberwachung; die Meldung selbst erfolgt im Portal der zuständigen Behörde.',
        'section' => [
            'product' => 'Produkt',
            'hazard' => 'Gefahr und Maßnahme',
            'scope' => 'Umfang',
            'authority' => 'Behörde',
        ],
        'field' => [
            'product' => 'Produkt',
            'gtin' => 'GTIN',
            'batches' => 'Seriennummern',
            'delivered' => 'Lieferzeitraum',
            'hazard_kind' => 'Gefahrenart',
            'hazard_description' => 'Beschreibung der Gefahr',
            'risk_level' => 'Risikostufe',
            'measure' => 'Maßnahme',
            'countries' => 'Vertriebsländer',
            'units' => 'Betroffene Einheiten',
            'customers' => 'Betroffene Kunden',
            'returned' => 'Rücklauf',
            'activated_at' => 'Rückruf seit',
            'authority_name' => 'Behörde',
            'authority_reference' => 'Aktenzeichen',
            'authority_reported_on' => 'Gemeldet am',
            'contact' => 'Ansprechperson',
            'contact_name' => 'Ansprechperson',
            'contact_email' => 'E-Mail der Ansprechperson',
        ],
        'hint' => [
            'hazard_kind' => 'z. B. Brand, Stromschlag, Verletzung, chemisch',
            'countries' => 'Ländercodes, durch Komma getrennt (DE, AT, …)',
        ],
        'risk' => [
            'low' => 'gering',
            'medium' => 'mittel',
            'high' => 'hoch',
            'serious' => 'ernst',
        ],
        'measure' => [
            'withdrawal' => 'Rücknahme vom Markt',
            'recall' => 'Rückruf beim Endkunden',
            'warning' => 'Warnhinweis',
            'destruction' => 'Vernichtung',
        ],
        'flash' => [
            'saved' => 'Angaben gespeichert.',
        ],
    ],
];
