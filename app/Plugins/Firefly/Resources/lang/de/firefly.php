<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : firefly.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Spiegelt Ein- und Ausgänge aus Firefly III und ordnet sie über die Belegnummer im Verwendungszweck Rechnungen bzw. Eingangsrechnungen zu. Gebucht wird in WorkDiary nichts, und nach Firefly III wird nichts geschrieben.',
    ],
    'page' => [
        'subtitle' => 'Kontobewegungen aus Firefly III, zugeordnet über Belegnummern im Verwendungszweck — nur lesend.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der Firefly-III-Installation, z. B. https://finanzen.example.com (ohne /api).',
        'access_token' => 'Personal Access Token',
        'access_token_help' => 'In Firefly III unter Optionen → Profil → OAuth einen Personal Access Token anlegen.',
        'window_days' => 'Zeitfenster (Tage)',
        'window_days_help' => 'Jeder Abgleich liest die Bewegungen dieses Zeitraums bis heute. 1 bis 365 Tage.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Installation im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
    'connection' => [
        'title' => 'Verbindung',
        'connected' => 'Verbunden',
        'not_connected' => 'Nicht verbunden',
        'verify' => 'Verbindung prüfen',
        'settings' => 'Zugangsdaten',
        'disconnect' => 'Trennen',
        'confirm_disconnect' => 'Verbindung zu Firefly III trennen? Der Abgleich ruht; gespiegelte Bewegungen bleiben bis zum nächsten vollständigen Lauf.',
        'sync_now' => 'Jetzt abgleichen',
        'not_configured' => 'Firefly III ist nicht eingerichtet: Hinterlegen Sie Adresse und Token in den Plugin-Einstellungen.',
        'privacy_note' => 'WorkDiary liest Ein- und Ausgänge mit Verwendungszweck und Gegenpartei und schreibt nichts nach Firefly III. Zahlungen bucht WorkDiary daraus nicht.',
        'instance' => 'Instanz',
        'account' => 'Konto',
        'window' => 'Zeitfenster',
        'window_value' => 'Bewegungen der letzten :days Tage',
        'last_sync' => 'Letzter Abgleich',
        'incomplete' => 'unvollständig',
        'last_error' => 'Letzter Fehler',
    ],
    'list' => [
        'search' => 'Suche',
        'search_placeholder' => 'Verwendungszweck, Gegenpartei, Belegnummer',
        'all_matches' => 'Alle Zuordnungen',
        'col' => [
            'date' => 'Datum',
            'kind' => 'Art',
            'counterparty' => 'Gegenpartei',
            'description' => 'Verwendungszweck',
            'match' => 'Zuordnung',
            'amount' => 'Betrag',
        ],
        'empty' => 'Noch keine Bewegungen',
    ],
    'search' => [
        'domain' => 'Firefly-III-Bewegungen',
    ],
    'flash' => [
        'not_configured' => 'Firefly III ist nicht eingerichtet: Adresse oder Token fehlen in den Plugin-Einstellungen.',
        'verify_failed' => 'Die Verbindung zu Firefly III ist fehlgeschlagen (:class).',
        'source_locked' => 'Die Plugin-Einstellungen zeigen auf eine andere Instanz. Solange Bewegungen der bisherigen gespiegelt sind, ist ein Wechsel nicht möglich.',
        'verified' => 'Firefly-III-Verbindung geprüft: :source_name.',
        'not_ready' => 'Prüfen Sie zuerst die Verbindung.',
        'sync_failed' => 'Der Abgleich ist fehlgeschlagen (:class).',
        'synced' => 'Abgleich: :read gelesen, :created neu, :updated aktualisiert, :matched zugeordnet, :removed entfernt.',
        'disconnected' => 'Firefly III getrennt.',
    ],
    'health' => [
        'not_configured' => 'Firefly III ist nicht eingerichtet (Adresse oder Token fehlt).',
        'not_connected' => 'Firefly-III-Verbindung noch nicht geprüft.',
    ],
];
