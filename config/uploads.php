<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : uploads.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Per-feature upload limits (in kilobytes, matching Laravel's
 * `max:` validation rule unit).
 */

return [
    'csv_import_kb' => (int) env('UPLOAD_CSV_IMPORT_KB', 10240),
    'customer_attachment_kb' => (int) env('UPLOAD_CUSTOMER_ATTACHMENT_KB', 10240),
];
