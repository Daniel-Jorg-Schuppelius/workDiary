<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : privacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'retention_area' => [
        'audit_logs' => 'Registro de auditoría',
        'exports' => 'Exportaciones de nómina y tiempos',
        'gobd_financial' => 'Datos con relevancia fiscal',
        'claims' => 'Reclamaciones (cerradas)',
        'leads' => 'Leads (no convertidos)',
        'applications' => 'Candidaturas (rechazadas/retiradas)',
        'privacy_requests' => 'Solicitudes de afectados (cerradas)',
        'cti_calls' => 'Metadatos de llamadas CTI',
        'idea_maps' => 'Mapas de ideas (papelera)',
        'dictations' => 'Dictados de voz (transcripciones)',
        'problem_reports' => 'Informes de problemas (cerrados)',
        'driver_license_checks' => 'Controles del permiso de conducir',
        'documents_invoice' => 'Facturas (DMS)',
        'passenger_rides' => 'Expedientes de trayectos (datos de lugar y pasajeros)',
        'employee_records' => 'Datos maestros de personal (empleados que causaron baja)',
        'personnel_files' => 'Expedientes de personal (empleados que causaron baja)',
        'customer_master' => 'Datos maestros de clientes (sin operaciones)',
        'customer_intakes' => 'Entradas de clientes (rechazadas o retiradas)',
        'time_records' => 'Datos brutos de jornada (asistencias)',
        'location_points' => 'Datos brutos de ubicación (puntos GPS)',
        'documents_general' => 'Documentos (sin relevancia fiscal ni mercantil)',
        'learning_records' => 'Plataforma de aprendizaje (inscripciones, tiempo de aprendizaje, intentos)',
        'learning_certificates' => 'Certificados de la plataforma de aprendizaje (seudonimización)',
    ],

    'requirement' => [
        'avv_required' => 'DPA con el encargado del tratamiento',
        'avv_current' => 'DPA vigente (no vencido)',
        'gvv_required' => 'Acuerdo de corresponsabilidad con el corresponsable',
        'dpia_required' => 'EIPD cuando se requiere una EIPD',
        'tom_assigned' => 'TOM por actividad de tratamiento',
        'tom_proof_current' => 'Pruebas de TOM vigentes (no vencidas)',
    ],
    // Datenkategorien der Datenschutz-Übersicht (Schlüssel == config/privacy.php categories.*.code).
    'category' => [
        'employees' => ['label' => 'Empleados', 'retention' => 'hasta el fin del contrato', 'delete_path' => 'Administrador de la organización → Miembros'],
        'working_time' => ['label' => 'Tiempo de trabajo', 'retention' => '10 años (GoBD)', 'delete_path' => 'bloqueado tras el cierre, no se puede eliminar'],
        'absences' => ['label' => 'Ausencias retribuidas', 'retention' => 'según convenio/ley', 'delete_path' => 'Administrador de la organización tras el plazo'],
        'diary' => ['label' => 'Libro de pedidos', 'retention' => '5 años (configurable)', 'delete_path' => 'Administrador de la organización'],
        'tours' => ['label' => 'Rutas / ubicaciones', 'retention' => '2 años (propuesta)', 'delete_path' => 'borrado automático'],
        'expenses' => ['label' => 'Gastos / gastos de viaje', 'retention' => '10 años (GoBD)', 'delete_path' => 'bloqueado, archivado'],
        'customers' => ['label' => 'Maestro de clientes', 'retention' => 'hasta el fin de la relación comercial + plazo', 'delete_path' => 'Administrador de la organización'],
        'attachments' => ['label' => 'Adjuntos', 'retention' => 'con el registro principal', 'delete_path' => 'junto con el registro principal'],
        'signatures' => ['label' => 'Firmas', 'retention' => 'como el pedido', 'delete_path' => 'junto con el pedido'],
        'qualifications' => ['label' => 'Cualificaciones', 'retention' => 'hasta el fin del contrato', 'delete_path' => 'Administrador de la organización'],
        'push' => ['label' => 'Suscripciones push', 'retention' => 'al darse de baja', 'delete_path' => 'automáticamente al cerrar sesión/limpieza'],
        'audit' => ['label' => 'Registro de auditoría', 'retention' => '24 meses (propuesta)', 'delete_path' => 'tarea de rotación del sistema'],
    ],
    'retention_years' => ':years años',
    // Befunde der Lückenanalyse (ComplianceAnalysisService, gespeichert in der Sprache der Organisation).
    'gap' => [
        'avv_missing' => 'Encargado del tratamiento «:name» sin contrato de encargo',
        'avv_expiring' => 'El contrato de encargo «:name» vence o ha vencido',
        'gvv_missing' => 'Corresponsable «:name» sin acuerdo de corresponsabilidad',
        'dpia_missing' => '«:name» requiere una EIPD pero no tiene una EIPD concluida',
        'tom_missing' => '«:name» sin medidas técnicas y organizativas asignadas',
        'tom_proof_expiring' => 'Las pruebas de medidas técnicas y organizativas vencen o han vencido: :names',
    ],
];
