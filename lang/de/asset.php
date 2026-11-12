<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'lifecycle' => [
        'in_operation' => 'In Betrieb',
        'retired' => 'Ersetzt',
        'decommissioned' => 'Stillgelegt',
    ],
    'dossier' => [
        'title' => 'Objektakte',
        'back' => 'Zurück zum Asset',
        'generated_at' => 'Erstellt am',
        'lifecycle' => 'Lebenszyklus',
        'master_data' => 'Stammdaten',
        'health' => 'Zustand',
        'commissioned' => 'Inbetriebnahme',
        'decommissioned' => 'Außerbetriebnahme',
        'warranty' => 'Garantie bis',
        'warranty_expired' => 'abgelaufen',
        'in_service_days' => 'Einsatzdauer (Tage)',
        'room_requirements' => 'Raumbezogene Anforderungen',
        'maintenance' => 'Wartungen',
        'next_due' => 'Nächste Fälligkeit',
        'last_run' => 'Zuletzt durchgeführt',
        'due' => 'Fällig',
        'scheduled' => 'Geplant',
        'assignments' => 'Ausgaben / Rückgaben',
        'checked_out' => 'Ausgegeben',
        'assignee' => 'Empfänger',
        'returned' => 'Zurückgegeben',
        'open' => 'Offen',
        'defects' => 'Defekte / Sperren',
        'blocks' => 'Sperrt',
        'orders' => 'Aufträge',
        'timeline' => 'Lebenszyklus-Verlauf',
        'event' => [
            'asset.audit' => 'Asset-Ereignis',
            'order.linked' => 'Auftrag verknüpft',
            'protocol.linked' => 'Protokoll verknüpft',
            'material.linked' => 'Materialeinsatz verknüpft',
            'attachment.linked' => 'Anhang hinzugefügt',
            'assignment.checkedOut' => 'Ausgegeben',
            'assignment.returned' => 'Zurückgegeben',
            'defect.reported' => 'Defekt gemeldet',
            'defect.resolved' => 'Defekt behoben',
            'maintenance.completed' => 'Wartung durchgeführt',
            'unknown' => 'Ereignis',
        ],
    ],
    // Anlagen-Stückliste (Feature 118, MVP-607).
    'components' => [
        'title' => 'Stückliste',
        'stock_serial_none' => 'keine (Fremdteil)',
        'stock_serial_hint' => 'Nur Teile aus dem eigenen Lager haben eine.',
        'serial_no_hint' => 'Frei erfassbar; bei verknüpfter Lager-Seriennummer wird sie überschrieben.',
        'empty' => 'Noch kein Teil erfasst.',
        'saved' => 'Teil erfasst.',
        'replaced' => 'Teil ersetzt — das alte bleibt in der Historie.',
        'removed' => 'Teil ausgebaut.',
        'not_installed' => 'Dieses Teil ist nicht mehr verbaut.',
        'foreign_organization' => 'Materialverbrauch und Gerät gehören zu verschiedenen Organisationen.',
        'usage_of_other_asset' => 'Der Materialverbrauch gehört zu einem anderen Gerät.',
        'already_adopted' => 'Dieser Materialverbrauch steht schon in der Stückliste.',
        'adopted' => 'Material in die Stückliste übernommen.',
        'replace_hint' => '„:name" wird ausgebaut und bleibt mit Ausbaudatum in der Historie stehen.',
        'label_hint' => 'Für Fremdteile ohne eigenen Artikelstamm.',
        'interval_hint' => 'Wechselintervall in Monaten — daraus errechnet sich die Fälligkeit.',
        'action' => [
            'add' => 'Teil hinzufügen',
            'replace' => 'Ersetzen',
            'remove' => 'Ausbauen',
            'adopt' => 'Übernehmen',
        ],
        'adopt' => ['heading' => 'Material aus Einsätzen', 'hint' => 'Am Stundenzettel diesem Gerät zugeordnetes Material. „Übernehmen“ trägt es mit dem Einsatztag als Einbaudatum in die Stückliste ein.', 'column_date' => 'Einsatz'],
        'due' => ['heading' => 'Fällige Verschleißteile', 'hint' => 'Vorschlag für den nächsten Einsatz — was wirklich getauscht wird, entscheidet der Monteur.'],
        'history' => ['heading' => 'Historie (ausgebaute und ersetzte Teile)'],
        'column' => [
            'name' => 'Teil',
            'article' => 'Artikel',
            'label' => 'Bezeichnung (Freitext)',
            'position' => 'Einbauort',
            'quantity' => 'Menge',
            'unit' => 'Einheit',
            'serial_no' => 'Seriennummer',
            'stock_serial' => 'Lager-Seriennummer',
            'installed_on' => 'Eingebaut am',
            'removed_on' => 'Ausgebaut am',
            'due_on' => 'Wechsel fällig',
            'interval' => 'Intervall (Monate)',
            'status' => 'Status',
            'note' => 'Notiz',
        ],
        'status' => [
            'installed' => 'Verbaut',
            'removed' => 'Ausgebaut',
            'replaced' => 'Ersetzt',
        ],
    ],
    'label' => [
        'print' => 'Etikett mit QR',
    ],
    'nfc' => [
        'read' => 'NFC-Tag lesen',
        'write' => 'NFC-Tag beschreiben',
    ],
    // Zustand, Außerbetriebnahme, Eigentümerwechsel, Rücknahme (MVP-1574).
    'health' => [
        'label' => 'Zustand',
        'hint' => 'Abgeleitet: kritisch bei aktiver Sperre oder blockierendem Defekt, eingeschränkt bei offenem Defekt oder überfälliger Wartung/Prüfung.',
    ],
    'decommission' => [
        'title' => 'Asset außer Betrieb nehmen',
        'action' => 'Außer Betrieb nehmen',
        'intro' => 'Das Asset wird endgültig außer Betrieb genommen. Es lässt sich danach nicht mehr ausgeben, verleihen, reservieren oder einplanen; die Akte bleibt erhalten.',
        'date' => 'Außer Betrieb seit',
        'reason' => 'Grund',
        'since' => 'Außer Betrieb seit :date',
        'by_disposal' => 'Über einen Entsorgungsauftrag entsorgt.',
        'done' => 'Asset außer Betrieb genommen.',
        'plans' => 'Aktive Wartungspläne: :count — sie werden pausiert.',
        'duties' => 'Aktive Prüfpflichten: :count — sie ruhen, offene Prüftermine werden storniert.',
        'error' => [
            'checked_out' => 'Das Asset ist noch ausgegeben. Nehmen Sie es zuerst zurück.',
            'not_allowed' => 'Aus dem Status „:status“ lässt sich das Asset nicht außer Betrieb nehmen.',
        ],
    ],
    // Wiederinbetriebnahme (MVP-1628)
    'recommission' => [
        'title' => 'Asset wieder in Betrieb nehmen',
        'action' => 'Wieder in Betrieb nehmen',
        'intro' => 'Das Asset ist danach wieder aktiv: ausgeben, verleihen, reservieren und einplanen sind möglich. Pausierte Wartungspläne und ruhende Prüfpflichten bleiben aus — nehmen Sie sie einzeln wieder auf.',
        'reason' => 'Grund der Wiederinbetriebnahme',
        'done' => 'Asset wieder in Betrieb genommen.',
        'error' => [
            'not_decommissioned' => 'Nur ein außer Betrieb genommenes Asset lässt sich wieder in Betrieb nehmen.',
            'disposed' => 'Das Asset wurde über einen Entsorgungsauftrag entsorgt und bleibt außer Betrieb.',
        ],
    ],
    'ownership' => [
        'title' => 'Eigentümer wechseln',
        'action' => 'Eigentümer wechseln',
        'current' => 'Bisher: :owner',
        'owned_by' => 'Eigentum',
        'customer_hint' => 'Nur bei Eigentum „Kunde“; sonst wird die Kundenbindung entfernt.',
        'note' => 'Notiz zum Eigentümerwechsel',
        'note_hint' => 'Wird nur gespeichert, wenn sich der Kunde ändert; der Wechsel steht dann in der Objektakte.',
        'done' => 'Eigentümerwechsel verzeichnet.',
        'error' => [
            'unchanged' => 'Eigentum und Kunde sind unverändert.',
            'forbidden' => 'Ein anderer Kunde ist ein Eigentümerwechsel; dafür fehlt Ihnen das Recht.',
        ],
    ],
    'return' => [
        'title' => 'Asset zurücknehmen',
        'condition_in' => 'Zustand bei Rückgabe',
        'condition_placeholder' => 'z. B. vollständig, Gehäuse verkratzt',
    ],
    'timeline' => [
        'ownership_changed' => 'Eigentümer gewechselt',
        'health_changed' => 'Zustand geändert',
        'blocked' => 'Gesperrt',
        'unblocked' => 'Sperre aufgehoben',
    ],
    'block' => [
        'from_status' => 'Aus dem Status „:status“.',
        'status_left' => 'Status geändert.',
    ],
    'guard' => [
        'out_of_service' => ':asset ist nicht mehr im Betrieb (:status) und kann nicht verwendet werden.',
    ],
    // Anschaffung an der Asset-Akte (MVP-1606).
    'acquisition' => [
        'title' => 'Anschaffung',
        'supplier' => 'Bezogen von',
        'date' => 'Anschaffungsdatum',
        'cost' => 'Anschaffungskosten',
    ],
];
