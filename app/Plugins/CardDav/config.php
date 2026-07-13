<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * CardDAV-Plugin (Bauturbo A9, MVP-329). Die Anbindung (Basis-URL, Zugangsdaten
 * verschlüsselt, gewähltes Adressbuch, Sync-Token) liegt PRO ORGANISATION in
 * `carddav_connections` und wird über das Admin-Panel gepflegt. ENV dient nur
 * als globaler Aktivierungs-Fallback für Tests/Konsole.
 *
 * Protokoll: RFC 6352 (CardDAV) rein lesend über mstilkerich/carddavclient
 * (RFC-6764-Discovery, RFC-6578-sync-collection mit ETag-Fallback) +
 * sabre/vobject fürs vCard-Parsing.
 */

return [
    'enabled' => env('CARDDAV_ENABLED', false),
];
