<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'flash' => [
        'msgraph_connection_required' => 'Für ein Microsoft-365-Postfach muss zuerst der Mail-Versand im Microsoft-365-Plugin verbunden werden (Scope Mail.ReadWrite).',
    ],
    'error' => [
        'scope_missing' => 'Der Microsoft-365-Verbindung fehlt das Recht :scope. Bitte verbinden Sie den Mail-Versand im Microsoft-365-Plugin neu – das Exchange-Recht „Vollzugriff“ allein genügt nicht. Verbietet Ihr Tenant die Zustimmung durch Benutzer, muss ein Administrator vorher „Für Organisation freigeben“ erneut ausführen.',
    ],
    'transport' => [
        'msgraph' => 'Microsoft 365 (Graph)',
        'msgraph_hint' => 'Microsoft 365: nutzt die Graph-Mail-Verbindung der Organisation — IMAP-Zugangsdaten entfallen.',
    ],
];
