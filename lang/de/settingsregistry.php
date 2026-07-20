<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settingsregistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Einstellungen (Registry)',
        'subtitle' => 'Registrierte System- und Organisationseinstellungen — mit Effektivwert, Herkunft und Rollback.',
        'help_text' => 'Nur in der Registry deklarierte Schlüssel sind hier änderbar; Validierung, Sensibilität und Auditierung sind je Schlüssel festgelegt. Infrastrukturwerte (APP_KEY, Datenbank, Mail-Transport) erscheinen hier bewusst nicht.',
    ],
    'scopes' => [
        'system' => 'System (Betreiber)',
        'organization' => 'Organisation',
        'user' => 'Benutzer',
    ],
    'sources' => [
        'organization' => 'Org-Override',
        'system' => 'System-Override',
        'config' => 'Konfigurationsdatei',
        'default' => 'Standardwert',
    ],
    'field' => [
        'search' => 'Schlüssel suchen…',
        'sensitive' => 'Sensibel',
        'sensitive_placeholder' => 'Neuen Wert eingeben (aktueller Wert verborgen)',
        'affects' => 'Betrifft',
    ],
    'action' => [
        'save' => 'Speichern',
        'reset' => 'Auf Default',
        'history' => 'Verlauf',
        'export' => 'Export (JSON)',
    ],
    'empty' => [
        'title' => 'Keine Einstellungen gefunden',
        'message' => 'Für diesen Scope bzw. Suchbegriff sind keine Registry-Schlüssel vorhanden.',
        'history' => 'Noch keine Änderungen protokolliert.',
    ],
    'flash' => [
        'saved' => 'Einstellung :key gespeichert.',
        'reset' => 'Einstellung :key auf den Default zurückgesetzt.',
    ],
];
