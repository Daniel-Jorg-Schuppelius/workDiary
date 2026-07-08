<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * WebDAV-Plugin (Feature 058, MVP-127). Die Ablage (Collection-URL,
 * Zugangsdaten verschlüsselt, Ordnerregeln) liegt PRO ORGANISATION in
 * `webdav_connections` und wird über das Admin-Panel gepflegt. ENV dient nur als
 * globaler Aktivierungs-Fallback für Tests/Konsole.
 *
 * Zustellung über die generische Integrations-Outbox (Idempotenz, Retry,
 * Konflikt-in-Inbox); Protokoll: WebDAV über HTTP, Basic-Auth (App-Passwort).
 */

return [
    'enabled' => env('WEBDAV_ENABLED', false),
];
