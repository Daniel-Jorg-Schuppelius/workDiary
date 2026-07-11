<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : investments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Investitionsplanung (Feature 069): Schwellenwerte sind Konfiguration
 * (Flexibilitätsplan P1) mit Org-Override unter
 * settings.investments.approval_threshold.
 */
return [
    // Ab diesem Antragsbetrag (EUR) gilt das Vier-Augen-Prinzip:
    // zusätzliche Management-Freigabestufe (MVP-203).
    'approval_threshold' => (float) env('INVESTMENTS_APPROVAL_THRESHOLD', 10000),
];
