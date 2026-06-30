<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'webhook' => [
        'title' => [
            'index' => 'Webhooks',
            'subtitle' => 'Ausgehende Ereignis-Benachrichtigungen an externe Systeme.',
            'help' => 'Wie funktionieren Webhooks?',
            'help_text' => 'Ein Webhook sendet bei den abonnierten Ereignissen eine signierte JSON-Nutzlast per HTTPS-POST an deine URL. Die Signatur (HMAC-SHA256 über Zeitstempel und Body) liegt im Header X-WorkDiary-Signature; prüfe sie mit dem Signing-Key. Nach mehreren Fehlversuchen wird der Endpunkt automatisch deaktiviert.',
            'create' => 'Webhook anlegen',
            'edit' => 'Webhook bearbeiten',
            'empty' => 'Noch keine Webhooks angelegt.',
        ],
        'field' => [
            'basics' => 'Grunddaten',
            'label' => 'Bezeichnung',
            'label_placeholder' => 'z. B. ERP-Integration',
            'url' => 'Ziel-URL',
            'url_help' => 'HTTPS-Endpunkt, der die POST-Anfrage empfängt.',
            'events' => 'Abonnierte Ereignisse',
            'events_help' => 'Nur ausgewählte Ereignisse lösen einen Versand aus.',
            'security' => 'Sicherheit & Status',
            'signing_secret' => 'Signing-Key',
            'endpoint_active' => 'Endpunkt aktiv',
            'status' => 'Status',
            'active' => 'Aktiv',
            'inactive' => 'Inaktiv',
            'auto_disabled' => 'Auto-deaktiviert',
            'auto_disabled_help' => 'Nach zu vielen Fehlversuchen automatisch deaktiviert. Beim Speichern als aktiv wird der Endpunkt reaktiviert.',
            'last_deliveries' => 'Letzte Zustellungen',
            'no_deliveries' => 'Noch keine Zustellungen.',
        ],
        'action' => [
            'create' => 'Anlegen',
            'edit' => 'Bearbeiten',
            'save' => 'Speichern',
            'delete' => 'Löschen',
            'delete_confirm' => 'Diesen Webhook wirklich löschen? Bestehende Zustellprotokolle bleiben erhalten.',
            'rotate_secret' => 'Signing-Key rotieren',
            'test' => 'Test-Event senden',
        ],
        'secret' => [
            'shown_once' => 'Signing-Key – nur jetzt sichtbar',
            'shown_once_help' => 'Kopiere den Schlüssel jetzt. Aus Sicherheitsgründen wird er nie wieder im Klartext angezeigt.',
            'rotate_help' => 'Der Klartext-Schlüssel wird nur einmal bei Anlage/Rotation angezeigt.',
            'rotate_confirm' => 'Neuen Signing-Key erzeugen? Der alte Schlüssel wird sofort ungültig.',
        ],
        'flash' => [
            'created' => 'Webhook angelegt.',
            'updated' => 'Webhook aktualisiert.',
            'deleted' => 'Webhook gelöscht.',
            'secret_rotated' => 'Signing-Key rotiert.',
            'test_sent' => 'Test-Event in die Warteschlange gestellt.',
        ],
        'event' => [
            'openIssue.assigned' => 'Offener Punkt zugewiesen',
            'openIssue.overdue' => 'Offener Punkt überfällig',
            'safetyEvent.reported' => 'Sicherheitsereignis gemeldet',
            'isms.incidentCritical' => 'Kritischer ISMS-Sicherheitsvorfall',
            'timeCorrection.requested' => 'Arbeitszeit-Korrektur beantragt',
            'monthClosure.submitted' => 'Monatsabschluss eingereicht',
            'sla.breached' => 'SLA-Frist verletzt',
            'document.expired' => 'Dokument abgelaufen',
        ],
        'delivery_status' => [
            'pending' => 'Ausstehend',
            'success' => 'Erfolgreich',
            'failed' => 'Fehlgeschlagen',
        ],
    ],
    'external_type' => [
        'client' => 'Kunde',
        'client_id' => 'Kunden-ID',
        'contact' => 'Kontakt',
        'delivery_note' => 'Lieferschein',
        'dunning' => 'Mahnung',
        'entry' => 'Eintrag',
        'foreign_client' => 'Fremdkunde',
        'invoice' => 'Rechnung',
        'order_confirmation' => 'Auftragsbestätigung',
        'project' => 'Projekt',
        'project_id' => 'Projekt-ID',
        'pushed_entry' => 'Übertragener Eintrag',
        'quotation' => 'Angebot',
        'session' => 'Sitzung',
        'user' => 'Benutzer',
        'voucher' => 'Beleg',
        'work_package' => 'Arbeitspaket',
        'anydesk_id' => 'AnyDesk-ID',
        'teamviewer_id' => 'TeamViewer-ID',
    ],
];
