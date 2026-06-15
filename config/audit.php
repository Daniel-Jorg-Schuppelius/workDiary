<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : audit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    | Aufbewahrungsfrist der revisionssicheren Audit-Daten in Jahren.
    | GoBD: steuerrelevante Aufzeichnungen i. d. R. 10 Jahre. Die Frist ist
    | eine MINDEST-Aufbewahrung: Audit-Tabellen sind append-only, es gibt
    | bewusst KEIN automatisches Löschen. Der Wert wird nur dokumentarisch
    | (Export-Manifest) und für Aufbewahrungs-Hinweise genutzt.
    */
    'retention_years' => (int) env('AUDIT_RETENTION_YEARS', 10),

    /*
    | Die revisionssicheren Hash-Ketten (Tabelle => Modell). audit:verify und
    | audit:export iterieren über diese Liste.
    */
    'chains' => [
        'audit_logs' => App\Models\AuditLog::class,
        'organization_audit_logs' => App\Models\OrganizationAuditLog::class,
        'whistleblowing_case_events' => App\Models\Whistleblowing\CaseEvent::class,
        'privacy_request_events' => App\Models\Privacy\RequestEvent::class,
        'privacy_incident_events' => App\Models\Privacy\IncidentEvent::class,
        'billing_transfer_events' => App\Models\Finance\BillingTransferEvent::class,
        'payment_reconciliation_events' => App\Models\Finance\PaymentReconciliationEvent::class,
        'datev_booking_events' => App\Models\Finance\DatevBookingEvent::class,
    ],
];
