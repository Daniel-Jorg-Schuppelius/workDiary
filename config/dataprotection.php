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

    // Vorlauf (Tage), ab dem ein ablaufender Vertrag als „laeuft ab" gilt.
    'expiry_warning_days' => (int) env('DATAPROTECTION_EXPIRY_WARNING_DAYS', 30),

    /*
     * Compliance-/Lueckenanalyse: prueffaehige Anforderungen (Definition) und
     * Branchenprofile (welche Anforderungen je Gewerk erwartet werden). Die
     * eigentlichen Befunde werden regelbasiert aus den echten Daten ermittelt.
     */
    'compliance' => [
        'requirements' => [
            'avv_required' => ['label' => 'AVV mit Auftragsverarbeiter', 'category' => 'contracts'],
            'avv_current' => ['label' => 'AVV gültig (nicht abgelaufen)', 'category' => 'contracts'],
            'gvv_required' => ['label' => 'GVV mit gemeinsam Verantwortlichem', 'category' => 'contracts'],
            'dpia_required' => ['label' => 'DSFA bei DSFA-Bedarf', 'category' => 'assessment'],
            'tom_assigned' => ['label' => 'TOM je Verarbeitungstätigkeit', 'category' => 'security'],
        ],
        // Gewerk => erwartete Anforderungs-Keys (informativ; die Regeln laufen generisch).
        'profiles' => [
            'it_service' => ['avv_required', 'avv_current', 'gvv_required', 'dpia_required', 'tom_assigned'],
            'handwerk' => ['avv_required', 'avv_current', 'tom_assigned'],
            'pflege' => ['avv_required', 'avv_current', 'gvv_required', 'dpia_required', 'tom_assigned'],
            'facility' => ['avv_required', 'avv_current', 'tom_assigned'],
        ],
    ],
];
