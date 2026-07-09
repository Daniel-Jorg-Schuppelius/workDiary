<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : eol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * End-of-Life-Daten der Laufzeitkomponenten (Feature 041, MVP-057).
 * Wird je Release gepflegt (Quelle: php.net supported-versions).
 * Der ExpiryScanner meldet Komponenten, deren Support-Ende innerhalb
 * des Vorlaufs (operations.expiry.eol_lead_days) liegt oder erreicht ist.
 */

return [
    'php' => [
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
        '8.5' => '2029-12-31',
    ],
];
