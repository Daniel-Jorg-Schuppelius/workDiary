<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : surveys.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Umfragen/NPS: Betreiber-Fristen, die bisher nur als Code-Default existierten
 * (Vollscan 2026-08-23, J14).
 */
return [
    // Ermüdungsschutz: derselbe Empfänger bekommt innerhalb von n Tagen keine
    // zweite Einladung.
    'fatigue_days' => (int) env('SURVEYS_FATIGUE_DAYS', 90),
];
