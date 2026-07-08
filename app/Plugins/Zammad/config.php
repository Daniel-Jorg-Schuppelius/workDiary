<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Zammad-Plugin (Feature 060, MVP-129). Die eigentliche Anbindung
 * (Basis-URL, API-Token verschlüsselt, Queue→Projekt-Zuordnung) liegt PRO
 * ORGANISATION in `zammad_connections` und wird über das Admin-Panel gepflegt.
 * ENV dient nur als globaler Aktivierungs-Fallback für Tests/Konsole.
 *
 * Zammad-API: Token-Auth `Authorization: Token token=…`, REST v1
 * (`/api/v1/tickets`); Webhook signiert per `X-Hub-Signature: sha1=HMAC(body)`
 * mit einem je Anbindung hinterlegten Shared-Secret.
 */

return [
    'enabled' => env('ZAMMAD_ENABLED', false),
];
