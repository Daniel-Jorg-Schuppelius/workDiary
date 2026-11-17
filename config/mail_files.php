<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail_files.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Abgelegte Mail-Dateien (.eml/.msg, Feature 204): Grenzen des Mail-Lesers.
 * Bewusst nicht in config/uploads.php — jeder Schlüssel dort erscheint als
 * Organisationseinstellung im Upload-Reiter.
 */

return [
    // Größte angenommene Mail-Datei in KB — bewusst kleiner als die allgemeine
    // Anhangsgrenze möglich; wirksam ist das Minimum mit uploads.attachment_kb bzw.
    // der Organisationseinstellung. Der Leser hält die ganze Datei im Speicher.
    'max_kb' => 25600,
    // Anhänge über alle Ebenen (eingebettete Mails eingeschlossen).
    'max_attachments' => 100,
    // Gesamtgröße der Anhänge über alle Ebenen in KB (höchstens die Dateigröße).
    'max_attachment_kb' => 25600,
    // Tiefe eingebetteter Mails (Mail in Mail in Mail).
    'max_depth' => 2,
];
