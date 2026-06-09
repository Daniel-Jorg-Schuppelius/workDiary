<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dataprotection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Modul-KEK (base64 32 Byte), GETRENNT von APP_KEY. Wrappt die per-Fall-DEKs
    // der Betroffenenanfragen/Vorfaelle (Crypto-Shredding).
    'key' => env('DATAPROTECTION_KEY'),

    // Bearbeitungsfrist Betroffenenanfragen (DSGVO Art. 12 Abs. 3: ein Monat).
    'dsr_deadline_days' => (int) env('DATAPROTECTION_DSR_DEADLINE_DAYS', 30),

    // Vorlauf (Tage) fuer Fristen-Erinnerungen.
    'dsr_reminder_lead_days' => (int) env('DATAPROTECTION_DSR_REMINDER_LEAD_DAYS', 7),

    // Standard-Reviewzyklus fuer Verarbeitungstaetigkeiten (Monate).
    'review_cycle_months' => (int) env('DATAPROTECTION_REVIEW_CYCLE_MONTHS', 12),
];
