<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : procurement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Beschaffung/Einkauf.
return [
    // Pflichtnachweise von Subunternehmern (Feature 117, MVP-606): Sperrt ein
    // fehlender oder abgelaufener Nachweis die Beauftragung? Standard AUS —
    // eine harte Sperre ab Werk legt Betriebe still, die ihre Nachweise noch
    // nicht erfasst haben. Sie muss eingeschaltet werden.
    'credential_blocking' => env('PROCUREMENT_CREDENTIAL_BLOCKING', false),

    /*
     * FTP ohne TLS zulassen (Vorgabe: nein). Ohne TLS gehen Benutzername und
     * Passwort des Lieferantenkatalogs im Klartext ueber die Leitung; der
     * Schalter existiert nur fuer Altbestaende, die kein FTPS koennen
     * (Sicherheitsaudit 2026-09-13).
     */
    'ftp_allow_plaintext' => env('PROCUREMENT_FTP_ALLOW_PLAINTEXT', false),
];
