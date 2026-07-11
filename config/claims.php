<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Reklamation/Gewährleistung (Feature 072): Standardfristen. Org-Overrides
 * laufen über organizations.settings (Ebene 2 der Einstellungs-Ablage).
 */

return [
    // Standard-Bearbeitungsfrist neuer Fälle in Tagen (MVP-247).
    'default_due_days' => (int) env('CLAIMS_DEFAULT_DUE_DAYS', 14),

    // Standard-Antwortfrist beim Lieferantenregress in Tagen (MVP-253).
    'recourse_response_days' => (int) env('CLAIMS_RECOURSE_RESPONSE_DAYS', 14),
];
