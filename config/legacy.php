<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : legacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Anbindung an das Altsystem.
 *
 * Der frühere Schlüssel `fallback_admins` (LEGACY_FALLBACK_ADMINS, Default
 * `admin,administrator,chef`) ist am 2026-08-30 ersatzlos entfallen
 * (Sicherheitsscan S-01): Er verglich den frei editierbaren Anzeigenamen
 * gegen eine Namensliste und machte damit jeden Nutzer zum Org-Admin, der
 * sich entsprechend umbenannte. Legacy-Adminstatus kommt seitdem
 * ausschließlich aus der verknüpften Legacy-ID
 * ({@see \App\Legacy\Support\LegacyRoleResolver}). Ein gesetztes
 * LEGACY_FALLBACK_ADMINS in einer .env wird nicht mehr gelesen.
 */
return [
];
