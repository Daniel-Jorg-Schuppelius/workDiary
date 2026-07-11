<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jtl_wawi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'JTL-Wawi',
    'intro' => 'Bindet JTL-Wawi als führende Warenwirtschaft an: Artikel- und Lagerprojektion, Bestände lesen und Bestandsbuchungen idempotent übergeben.',
    'beta_notice' => 'Die JTL-Wawi-API befindet sich im Beta-/Pilotprogramm. Nach dem offiziellen Release kann die Verfügbarkeit von der gebuchten JTL-Edition abhängen und kostenpflichtig werden.',

    'mode' => [
        'on_premise' => 'OnPremise',
        'cloud' => 'Cloud-Gateway',
    ],

    'status' => [
        'draft' => 'Entwurf',
        'pending_registration' => 'Registrierung ausstehend',
        'active' => 'Aktiv',
        'blocked' => 'Blockiert',
        'disconnected' => 'Getrennt',
    ],

    'field' => [
        'base_url' => 'Basis-URL der Wawi-API',
        'base_url_help' => 'z. B. https://wawi.example.local:5883/api/eazybusiness — die API-Instanz wird im JTL-Administrator angelegt.',
        'api_version' => 'API-Version',
        'detected_version' => 'Erkannte Wawi-Version',
        'company_id' => 'Firma (x-companyid)',
        'company_id_help' => 'Optional: Mandant/Firma innerhalb der Wawi.',
        'tenant_id' => 'Tenant-ID',
        'client_id' => 'Client-ID',
        'client_secret' => 'Client-Secret',
        'secret_keep' => '(unverändert — leer lassen)',
        'allow_private_network' => 'Private/interne Adressen ausdrücklich erlauben',
        'allow_private_network_help' => 'Eine OnPremise-Wawi steht typischerweise im eigenen Netz. Diese Freigabe wird auditiert und gilt nur für diese Verbindung.',
        'last_sync' => 'Letzte Synchronisation',
        'last_error' => 'Letzter Fehler',
    ],

    'stats' => [
        'linked_articles' => 'Zugeordnete Artikel',
        'open_inbox' => 'Offene Zuordnungsfälle',
    ],

    'scopes' => [
        'missing' => 'Fehlende Lese-Scopes: :scopes — die App-Freigabe in JTL-Wawi anpassen und die Registrierung erneut prüfen.',
        'missing_write' => 'Ohne Schreib-Scope (:scopes) bleibt die Bestandsübergabe deaktiviert.',
    ],

    'registration' => [
        'heading' => 'App-Registrierung',
        'explain' => 'In JTL-Wawi „Admin > App-Registrierung“ öffnen, dann hier die Registrierung starten. Der API-Schlüssel wird nach der Freigabe einmalig ausgegeben und verschlüsselt gespeichert.',
        'waiting' => 'Die Registrierung wartet auf die Freigabe in JTL-Wawi. Nach der Bestätigung hier den Status prüfen.',
    ],

    'connection' => [
        'heading' => 'Verbindung',
    ],

    'sync' => [
        'section' => 'Bereich',
        'counters' => 'Zähler',
        'warehouses' => 'Lager',
        'articles' => 'Artikel',
        'stocks' => 'Bestandsänderungen',
    ],

    'warehouses' => [
        'heading' => 'Lager-Zuordnung',
        'empty' => 'Noch keine JTL-Lager projiziert — zuerst synchronisieren.',
        'jtl' => 'JTL-Lager',
        'type' => 'Typ',
        'flags' => 'Merkmale',
        'local' => 'WorkDiary-Lager',
        'inactive' => 'inaktiv',
        'lock_shipment' => 'Versandsperre',
        'lock_availability' => 'Verfügbarkeitssperre',
        'unmapped' => '— nicht zugeordnet —',
    ],

    'inventory' => [
        'heading' => 'Bestandsführung',
        'explain' => 'Legt fest, welches System die Bestände führt. Der Wechsel zurück auf „lokal“ übernimmt die JTL-Bestände als Eröffnungs-Inventur.',
        'mode_local' => 'Lokal — WorkDiary führt die Bestände selbst.',
        'mode_external' => 'Extern — JTL-Wawi führt; WorkDiary liest und übergibt Buchungen.',
        'mode_read_only' => 'Nur lesen — JTL-Wawi führt; WorkDiary zeigt Bestände nur an.',
    ],

    'action' => [
        'save' => 'Speichern',
        'sync_now' => 'Jetzt synchronisieren',
        'disconnect' => 'Verbindung trennen',
        'start_registration' => 'Registrierung starten',
        'check_registration' => 'Freigabe prüfen',
        'map' => 'Zuordnen',
        'change_mode' => 'Modus wechseln',
    ],

    'confirm' => [
        'disconnect' => 'Verbindung wirklich trennen? Zuordnungen und Projektionen bleiben erhalten, die Zugangsdaten werden gelöscht.',
        'mode_change' => 'Bestandsführungs-Modus wirklich wechseln?',
    ],

    'flash' => [
        'saved' => 'Verbindung gespeichert.',
        'cloud_connected' => 'Cloud-Verbindung hergestellt und Token bezogen.',
        'cloud_failed' => 'Cloud-Verbindung fehlgeschlagen — Zugangsdaten und Tenant-ID prüfen.',
        'registration_started' => 'Registrierung gesendet — jetzt in JTL-Wawi freigeben.',
        'registration_failed' => 'Registrierung fehlgeschlagen.',
        'registration_pending' => 'Freigabe steht noch aus.',
        'registration_accepted' => 'Freigegeben — API-Schlüssel übernommen.',
        'registration_rejected' => 'Die Registrierung wurde in JTL-Wawi abgelehnt.',
        'not_active' => 'Die Verbindung ist nicht aktiv.',
        'sync_done' => 'Synchronisation abgeschlossen.',
        'sync_failed' => 'Synchronisation fehlgeschlagen (:reason).',
        'warehouse_mapped' => 'Lager-Zuordnung gespeichert.',
        'disconnected' => 'Verbindung getrennt.',
        'disconnect_blocked' => 'Trennung nicht möglich: zuerst die Bestandsführung auf „lokal“ umstellen.',
        'mode_unchanged' => 'Der Modus ist bereits aktiv.',
        'mode_needs_connection' => 'Für externe Bestandsführung wird eine aktive JTL-Verbindung benötigt.',
        'mode_needs_mapping' => 'Für externe Bestandsführung muss mindestens ein JTL-Lager zugeordnet sein.',
        'mode_changed' => 'Bestandsführungs-Modus geändert.',
        'mode_changed_with_takeover' => 'Modus geändert — :booked Eröffnungs-Korrekturen aus JTL übernommen.',
        'takeover_done' => 'Übernahme-Inventur abgeschlossen: :booked Korrekturen aus :pairs Paaren.',
        'takeover_failed' => 'Übernahme-Inventur fehlgeschlagen (:reason).',
    ],
];
