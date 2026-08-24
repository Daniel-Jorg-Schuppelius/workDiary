<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sales.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Vertrieb (Feature 091): Betreiber-Fristen, die bisher nur als Code-Default
 * existierten (Vollscan 2026-08-23, J14).
 */
return [
    // Nicht konvertierte Leads n Monate nach dem letzten Kontakt anonymisieren
    // (Retention-Bereich "leads", config/retention.php).
    'lead_retention_months' => (int) env('SALES_LEAD_RETENTION_MONTHS', 6),
];
