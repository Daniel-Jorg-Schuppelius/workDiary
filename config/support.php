<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : support.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Kontakt für kostenpflichtige Zusatzmodule
    |--------------------------------------------------------------------------
    |
    | Zentrale Bezugs-/Freischaltadresse für optionale, kostenpflichtige Module
    | (z. B. php-financial-formats: DATEV-Export, Bankimport). Wird über den
    | :contact-Platzhalter in die „nicht aktiviert"-Hinweise eingesetzt
    | (s. App\Services\Finance\FinancialFormatsSupport::unavailableMessage()).
    | EINE Stelle für die Adresse — Übersetzungen bleiben unverändert.
    */
    'module_contact' => env('SUPPORT_MODULE_CONTACT', 'info@workdiary.org'),
];
