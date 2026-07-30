<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : security.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Sicherheit',
    ],

    'subtitle' => 'Read-only Überblick sicherheitsrelevanter Zustände: aktive Sitzungen, API-Tokens, externe Integrationen, letzte Exporte und Supportzugriffe.',

    'scope' => [
        'label' => 'Geltungsbereich',
        'platform' => 'Plattformweit',
    ],

    'privacy_notice' => 'Diese Seite zeigt ausschließlich Metadaten. Es werden niemals Token-Werte, Hashes, Secrets, Passwörter oder Sitzungsinhalte angezeigt. Alle Daten bleiben lokal.',

    'deferred_notice' => 'Die automatisierten Lösch- und Aufbewahrungsläufe sind nicht Teil dieser Übersicht und folgen in einem späteren Schritt (Feature 016, „Später").',

    'section' => [
        'advisories' => 'Sicherheitslage der Abhängigkeiten',
        'sessions' => 'Aktive Sitzungen',
        'tokens' => 'API-Tokens',
        'integrations' => 'Externe Integrationen',
        'exports' => 'Letzte Exporte',
        'support_access' => 'Letzte Supportzugriffe',
        'two_factor' => 'Zwei-Faktor-Authentifizierung',
        'encryption' => 'Verschlüsselung (at-rest)',
    ],

    'field' => [
        'severity' => 'Schweregrad',
        'package' => 'Paket',
        'advisory' => 'Advisory',
        'fixed_in' => 'Behoben in',
        'statement' => 'Bewertung (VEX)',
        'statement_placeholder' => 'z. B. nicht ausnutzbar — Funktion nicht in Verwendung',
        'last_pull' => 'Letzter Abruf',
        'user' => 'Benutzer',
        'guest' => 'Ohne Anmeldung',
        'ip' => 'IP-Adresse',
        'user_agent' => 'User-Agent',
        'last_activity' => 'Letzte Aktivität',
        'sessions_total' => 'Sitzungen gesamt',
        'sessions_active' => 'Davon aktiv (< 2 h)',
        'token_name' => 'Name',
        'abilities' => 'Berechtigungen',
        'last_used_at' => 'Zuletzt genutzt',
        'expires_at' => 'Läuft ab',
        'created_at' => 'Erstellt',
        'tokens_total' => 'Tokens gesamt',
        'plugins_active' => 'Aktive Plugins',
        'external_references' => 'Externe Referenzen',
        'export_kind' => 'Art',
        'export_subject' => 'Gegenstand',
        'format' => 'Format',
        'status' => 'Status',
        'rows' => 'Datensätze',
        'event' => 'Ereignis',
        'subject' => 'Gegenstand',
        'users_total' => 'Benutzer gesamt',
        'users_with_2fa' => 'Mit aktiver 2FA',
        'credentials' => 'Bestätigte Faktoren',
        'coverage' => 'Abdeckung',
        'encrypted_fields' => 'Verschlüsselte Felder',
        'table' => 'Tabelle',
        'fields' => 'Felder',
    ],

    'export' => [
        'kind' => [
            'data_transfer' => 'Datentransfer',
            'time' => 'Zeitexport',
        ],
    ],

    'status' => [
        'active' => 'aktiv',
        'inactive' => 'inaktiv',
        'app_key_set' => 'APP_KEY gesetzt',
        'app_key_missing' => 'APP_KEY fehlt',
    ],

    'hint' => [
        'advisories' => 'Quelle: OSV.dev für composer.lock/package-lock.json — täglicher Abruf (security:advisories-pull); Bewertung (VEX) manuell.',
        'sessions_driver' => 'Sitzungstreiber „:driver" — keine Datenbank-Übersicht möglich. Nur der Treiber „database" liefert eine Sitzungsliste.',
        'tokens_no_secret' => 'Es werden nur Metadaten angezeigt — niemals der Token-Wert oder dessen Hash.',
        'support_access' => 'Quelle: Audit-Log, Ereignis-Präfix „support." (siehe Supportzugriff-Grundsätze).',
        'two_factor' => 'Reine Zählung bestätigter Faktoren — es werden keine Secrets gelesen.',
        'encryption' => 'Diese Felder werden über „php artisan :command" verschlüsselt. Die Verschlüsselung hängt am APP_KEY.',
    ],

    'empty' => [
        'advisories' => 'Keine offenen Sicherheitshinweise.',
        'sessions' => 'Keine Sitzungen gefunden.',
        'tokens' => 'Keine aktiven API-Tokens.',
        'integrations' => 'Keine aktiven externen Integrationen.',
        'exports' => 'Noch keine Exporte erfasst.',
        'support_access' => 'Keine Supportzugriffe protokolliert.',
    ],

    'generated_at' => 'Erzeugt: :at',
    'action' => [
        'pull_advisories' => 'Jetzt abrufen',
    ],

    // Massenangriff-Eskalation ins Krisenmodul (Feature 097, MVP-449).
    'crisis' => [
        'mass_attack_title' => 'Massenangriff erkannt (:event)',
        'mass_attack_description' => ':count Ereignisse vom Typ :event in :window Minuten (Limit :limit). Zugänge prüfen, Sperren setzen, Ursache dokumentieren.',
    ],
];
