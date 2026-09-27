<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : damage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Schadensfälle (MVP-919/920).
return [
    'title' => 'Siniestros',
    'subtitle' => 'Siniestros y casos de seguro de alquiler, leasing, reclamaciones y vehículos — con liquidación, franquicia e historial.',
    'case' => 'Siniestro',
    'empty' => 'No hay siniestros — se crean desde el expediente (alquiler, leasing, reclamación, vehículo).',
    'nav' => [
        'section' => 'Siniestros y retiradas',
        'cases' => 'Siniestros',
    ],
    'kpi' => [
        'open' => 'Casos abiertos',
        'open_estimate' => 'Estimado (abiertos)',
        'settled' => 'Liquidado',
    ],
    'filter' => [
        'all_status' => 'Todos los estados',
        'all_subjects' => 'Todos los expedientes',
        'all_kinds' => 'Todos los tipos',
    ],
    'field' => [
        'number' => 'Número',
        'title' => 'Denominación',
        'subject' => 'Expediente relacionado',
        'kind' => 'Tipo de siniestro',
        'status' => 'Estado',
        'estimated_amount' => 'Daño estimado',
        'settled_amount' => 'Importe liquidado',
        'deductible_amount' => 'Franquicia',
        'net_recovery' => 'Reembolso tras franquicia',
        'currency' => 'Moneda',
        'occurred_at' => 'Fecha del siniestro',
        'reported_at' => 'Notificado el',
        'insurer_name' => 'Aseguradora',
        'policy_number' => 'Número de póliza',
        'claim_number' => 'Número de siniestro',
        'responsible_user_id' => 'Responsable',
        'description' => 'Descripción de los hechos',
    ],
    'section' => [
        'case' => 'Siniestro',
        'insurance' => 'Seguro',
        'amounts' => 'Importes',
        'status' => 'Cambiar estado',
        'journal' => 'Historial',
    ],
    'action' => [
        'show' => 'Mostrar',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'open' => 'Crear siniestro',
        'report' => 'Notificar daño',
    ],
    'dialog' => [
        'create' => 'Notificar daño',
        'edit' => 'Editar siniestro',
    ],
    'card' => [
        'title' => 'Siniestros',
        'none' => 'No hay siniestros.',
    ],
    'status' => [
        'reported' => 'Notificado',
        'submitted' => 'Presentado a la aseguradora',
        'in_review' => 'En revisión',
        'settled' => 'Liquidado',
        'rejected' => 'Rechazado',
        'closed' => 'Cerrado',
    ],
    'transition' => [
        'submitted' => 'Presentar a la aseguradora',
        'in_review' => 'Marcar en revisión',
        'settled' => 'Registrar liquidación',
        'rejected' => 'Registrar rechazo',
        'closed' => 'Cerrar',
    ],
    'kind' => [
        'property' => 'Daño material',
        'liability' => 'Responsabilidad civil',
        'theft' => 'Robo/pérdida',
        'vehicle' => 'Daño al vehículo',
        'transport' => 'Daño de transporte',
        'other' => 'Otro',
    ],
    'error' => [
        'settled_amount_required' => 'Para «liquidado» falta el importe liquidado.',
    ],
    'flash' => [
        'opened' => 'Siniestro :number creado.',
        'saved' => 'Siniestro guardado.',
        'status' => 'Estado: :status.',
    ],
    'subject_type' => [
        'rental_cases' => 'Alquiler',
        'asset_finance_contracts' => 'Contrato de leasing/financiación',
        'claim_cases' => 'Reclamación',
        'vehicles' => 'Vehículo',
    ],
];
