<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Cloud-Dokumenteingang (Feature 080, MVP-356): Grenzen der Intake-Pipeline.
 * Archive, ausführbare Inhalte und passwortgeschützte Container sind im MVP
 * BLOCKIERT (Konzept §Sichere Übernahme) — die Blockliste prüft Endung UND
 * erkannten MIME-Typ der Quarantäne-Datei.
 */

return [
    // Maximale Dateigröße je Import (Bytes); Routen können enger begrenzen.
    'max_file_size' => (int) env('CLOUD_INTAKE_MAX_FILE_SIZE', 52_428_800), // 50 MB

    // Seiten-Budget je Lauf und Verbindung (Laufbudget, Konzept §Vorschau).
    'max_pages_per_run' => (int) env('CLOUD_INTAKE_MAX_PAGES', 10),

    // Blockierte Endungen (Archive/Executables/Skripte).
    'blocked_extensions' => [
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz',
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'ps1', 'sh', 'php', 'phar', 'js', 'jar', 'vbs', 'wsf',
        'apk', 'dmg', 'iso', 'img',
    ],

    // Blockierte MIME-Typen (Ergebnis der finfo-Prüfung in der Quarantäne).
    'blocked_mimes' => [
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        'application/x-tar', 'application/gzip', 'application/x-bzip2', 'application/x-xz',
        'application/x-dosexec', 'application/x-msdownload', 'application/x-executable',
        'application/x-sh', 'application/x-httpd-php', 'application/java-archive',
        'application/vnd.android.package-archive', 'application/x-iso9660-image',
    ],
];
