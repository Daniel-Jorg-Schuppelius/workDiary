<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reports.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | CSV-Metadatenzeilen
    |--------------------------------------------------------------------------
    | Reports schreiben optional eine kurze Metadatensektion (#report:,
    | #generated:, #filter_hash:) als erste Zeilen einer CSV-Datei. Manche
    | Tools (Power Query etc.) interpretieren das nicht als Kommentar; in
    | dem Fall lässt sich die Sektion komplett deaktivieren.
    */
    'csv_meta_lines' => filter_var(env('REPORTS_CSV_META_LINES', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | CSV-Spaltentrennzeichen
    |--------------------------------------------------------------------------
    | Standard ist Semikolon (Excel-DE). Pro Org überschreibbar (TBD), bis
    | dahin global.
    */
    'csv_delimiter' => env('REPORTS_CSV_DELIMITER', ';'),
];
