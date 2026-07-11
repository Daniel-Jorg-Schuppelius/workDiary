<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : applications.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Bewerbungs-/Ausschreibungsmodul (Feature 068): veränderliche Fristen sind
 * KONFIGURATION, kein Code (Flexibilitätsplan P1) — eine Gesetzes- oder
 * Praxisänderung ist ein Konfigurations-, kein Release-Vorgang.
 */
return [
    // Löschvormerkung abgelehnter/zurückgezogener Bewerbungen in Monaten:
    // AGG § 15 Abs. 4 (2 Monate) + ArbGG § 61b (3 Monate) → Praxisstandard
    // 4–6 Monate nach Verfahrensende (Feature 068, Rechtsrahmen 2026-07).
    'rejected_retention_months' => (int) env('APPLICATIONS_REJECTED_RETENTION_MONTHS', 6),

    // Talentpool nur mit ausdrücklicher, widerruflicher Einwilligung
    // (Art. 6 Abs. 1 lit. a DSGVO), Praxis-Befristung 1–2 Jahre.
    'talent_pool_months' => (int) env('APPLICATIONS_TALENT_POOL_MONTHS', 18),
];
