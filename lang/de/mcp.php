<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mcp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// MCP-Server für KI-Assistenten (MVP-1063/1064).
return [
    'error' => [
        'forbidden' => 'Kein Zugriff auf dieses Werkzeug.',
        'scope' => 'Dieses Token ist nicht für KI-Assistenten (MCP) freigegeben. Legen Sie ein Token mit dem Recht „KI-Assistent (MCP)“ an.',
        'not_found' => 'Nicht gefunden.',
        'invalid_number' => 'Position :position: Menge, Preis oder Steuersatz ist keine Zahl.',
        'conflicts' => 'Nicht verschoben — Konflikte: :list',
    ],
    'oauth' => [
        'title' => 'KI-Assistent verbinden',
        'intro' => ':client möchte im Namen von :user auf die Daten von :organization in workDiary zugreifen.',
        'scope' => [
            'read' => 'Lesen: Kunden, Projekte, Aufträge, Angebote, Rechnungen, offene Posten, Zeiten, Termine, Kennzahlen und Suche — mit Ihren Rechten.',
            'write' => 'Entwürfe anlegen: Angebots- und Rechnungsentwürfe, Kunden anlegen, Einsätze verschieben. Ausstellen und Versenden bleiben bei Ihnen.',
        ],
        'target' => 'Nach der Zustimmung geht der Zugang an',
        'untrusted_warning' => 'Dieses Ziel gehört zu keinem bekannten KI-Assistenten. Stimmen Sie nur zu, wenn Sie die Anbindung selbst gerade eingerichtet haben — sonst erhält ein Fremder Zugriff auf Ihre Daten.',
        'untrusted_confirm' => 'Ich habe die Anbindung an :target selbst eingerichtet.',
        'untrusted_required' => 'Bitte bestätigen Sie, dass Sie die Anbindung an dieses Ziel selbst eingerichtet haben.',
        'revoke_hint' => 'Den Zugriff widerrufen Sie jederzeit unter Profil → API-Tokens.',
        'approve' => 'Zugriff erlauben',
        'deny' => 'Ablehnen',
        'error' => [
            'title' => 'Verbindung nicht möglich',
            'client' => 'Unbekannter Client — bitte den Connector neu einrichten.',
            'redirect_uri' => 'Die Rücksprungadresse ist für diesen Client nicht registriert oder nicht zulässig (nur https oder lokale Adressen).',
            'response_type' => 'Nur der Antworttyp „code“ wird unterstützt.',
            'pkce' => 'PKCE mit S256 ist erforderlich.',
            'resource' => 'Die angefragte Ressource ist nicht dieser MCP-Server.',
            'scope' => 'Keine gültige Berechtigung angefragt (mcp:read, mcp:write).',
            'grant' => 'Code oder Refresh-Token ist ungültig, abgelaufen oder bereits verwendet.',
            'grant_type' => 'Dieser Grant-Typ wird nicht unterstützt.',
            'disabled' => 'Ihre Organisation hat KI-Assistenten (MCP) nicht freigegeben. Die Freigabe erteilt die Verwaltung in den Organisationseinstellungen.',
            'no_organization' => 'Ihr Konto gehört zu keiner Organisation.',
        ],
    ],
];
