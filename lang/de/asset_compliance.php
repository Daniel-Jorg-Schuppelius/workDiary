<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset_compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfwesen: Bearbeiten, Deaktivieren, Storno, Roh-Messwerte (MVP-1575).
return [
    'action' => [
        'deactivate' => 'Deaktivieren',
        'activate' => 'Aktivieren',
    ],
    'state' => [
        'inactive' => 'deaktiviert',
    ],
    'profile' => [
        'edit' => 'Prüfprofil bearbeiten',
        'updated' => 'Prüfprofil gespeichert.',
        'activated' => 'Prüfprofil aktiviert.',
        'deactivated' => 'Prüfprofil deaktiviert.',
        'deactivate_confirm' => 'Prüfprofil deaktivieren? Es steht dann für neue Zuweisungen nicht mehr zur Wahl; bestehende Prüfpflichten und Protokolle bleiben.',
        'inactive_title' => 'Deaktivierte Organisationsprofile',
        'interval_hint' => 'Gilt für alle Pflichten ohne eigenes Intervall, ab der nächsten Prüfung.',
    ],
    'requirement' => [
        'edit' => 'Anforderung bearbeiten',
        'edit_hint' => 'Geänderte Grenzwerte gelten für neue Prüfungen; alte Protokolle behalten ihre damaligen Grenzwerte.',
        'updated' => 'Anforderung gespeichert.',
        'activated' => 'Anforderung aktiviert.',
        'deactivated' => 'Anforderung deaktiviert.',
    ],
    'assignment' => [
        'edit' => 'Prüfpflicht bearbeiten',
        'updated' => 'Prüfpflicht gespeichert.',
        'activated' => 'Prüfpflicht wieder aufgenommen.',
        'deactivated' => 'Prüfpflicht deaktiviert.',
        'deactivate_confirm' => 'Prüfpflicht deaktivieren? Offene Prüftermine werden storniert, die Überfälligkeitssperre dieser Pflicht endet; Protokolle bleiben.',
        'interval_override' => 'Intervall für dieses Asset (Monate)',
        'interval_override_hint' => 'Leer = Intervall des Prüfprofils (:months Monate).',
        'next_due_on' => 'Nächste Fälligkeit',
        'next_due_hint' => 'Leer = bei geändertem Intervall ab der letzten Prüfung neu rechnen.',
        'inactive_title' => 'Deaktivierte Prüfpflichten',
        'show_active' => 'Aktive anzeigen',
        'show_inactive' => 'Deaktivierte (:count)',
        'block_released' => 'Prüfpflicht geändert oder deaktiviert.',
    ],
    'schedule' => [
        'cancel_title' => 'Prüftermin stornieren',
        'cancel_action' => 'Stornieren',
        'cancel_hint' => 'Der Termin bleibt mit Grund in der Liste (Filter „Storniert“). Die Prüfpflicht selbst läuft weiter.',
        'cancelled' => 'Prüftermin storniert.',
        'none_for_status' => 'Keine Prüftermine mit diesem Status.',
        'cancelled_by_deactivation' => 'Prüfpflicht deaktiviert.',
        'cancelled_by_decommission' => 'Asset außer Betrieb genommen.',
        'error' => [
            'not_cancellable' => 'Dieser Prüftermin ist bereits durchgeführt oder storniert.',
        ],
    ],
    'measurement' => [
        'title' => 'Roh-Messwerte',
        'hint' => 'Freie Messreihe ohne Grenzwertprüfung; Zeilen ohne Wert werden nicht gespeichert.',
        'label' => 'Messpunkt',
        'value' => 'Messwert',
    ],
    'protocol' => [
        'title' => 'Prüfprotokolle (letzte 20)',
        'performed_at' => 'Durchgeführt',
        'values' => 'Ergebnisse und Messwerte',
        'out_of_limit' => 'außerhalb',
        'empty' => 'Noch keine Prüfung dokumentiert.',
    ],
];
