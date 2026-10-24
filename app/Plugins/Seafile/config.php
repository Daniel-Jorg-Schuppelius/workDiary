<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Seafile als Quelle des Cloud-Dokumenteingangs und als Backupziel (Feature
 * 177, Phase 158). Web-API unter <Adresse>/api2 mit Konto-Token
 * (`Authorization: Token …`). Der Eingang liest Adresse und Token je
 * Organisation aus den Plugin-Einstellungen; das systemweite Backupziel führt
 * eigene Zugangsdaten an seiner Verbindung.
 */
return [
    'enabled' => env('SEAFILE_ENABLED', false),

    // Ordner je Seite des Eingangslaufs (je Ordner ein Abruf).
    'scan_folder_budget' => (int) env('SEAFILE_SCAN_FOLDER_BUDGET', 50),
    // Über so vielen Dateien je Zyklus entfällt der Abgleich gelöschter Dateien.
    'max_reconcile_files' => (int) env('SEAFILE_MAX_RECONCILE_FILES', 5000),
    // Backupziel im eigenen Netz: Entscheidung des Betreibers, nicht eines Mandanten.
    'allow_private_backup_targets' => (bool) env('SEAFILE_ALLOW_PRIVATE_BACKUP_TARGETS', false),
];
