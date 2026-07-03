<?php

return [
    'asset' => [
        'defect-severity' => [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ],
        'defect-status' => [
            'open' => 'Abierto',
            'inRepair' => 'En reparación',
            'resolved' => 'Resuelto',
            'writtenOff' => 'Dado de baja',
        ],
    ],
    'room_requirement_kind' => [
        'hygieneLevel' => 'Nivel de higiene',
        'specialCleaning' => 'Limpieza especial',
        'accessRestriction' => 'Restricción de acceso',
        'itInventory' => 'Inventario de TI',
        'technicalInspection' => 'Inspección técnica',
        'operatorDuty' => 'Obligación del operador',
        'other' => 'Otro',
    ],
    'event' => [
        'type' => [
            'training' => 'Formación',
            'workshop' => 'Taller',
            'conference' => 'Conferencia',
            'meeting' => 'Reunión',
            'internal_briefing' => 'Reunión informativa interna',
            'external_visit' => 'Visita externa',
        ],
        'status' => [
            'planned' => 'Planificado',
            'confirmed' => 'Confirmado',
            'in_progress' => 'En curso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ],
        'visibility' => [
            'internal' => 'Interno',
            'external' => 'Externo',
            'public' => 'Público',
        ],
        'participant' => [
            'role' => [
                'organizer' => 'Organizador',
                'trainer' => 'Formador',
                'attendee' => 'Asistente',
                'optional' => 'Opcional',
            ],
            'status' => [
                'invited' => 'Invitado',
                'accepted' => 'Aceptado',
                'declined' => 'Rechazado',
                'attended' => 'Asistió',
                'no_show' => 'No se presentó',
            ],
        ],
        'reminder' => [
            'channel' => [
                'mail' => 'Correo electrónico',
                'webpush' => 'Push',
                'database' => 'En la app',
            ],
        ],
    ],
    'vehicle' => [
        'type' => [
            'car' => 'Coche',
            'van' => 'Furgoneta',
            'truck' => 'Camión',
            'bicycle' => 'Bicicleta',
            'other' => 'Otro',
        ],
        'propulsion' => [
            'diesel' => 'Diésel',
            'petrol' => 'Gasolina',
            'gas' => 'Gas',
            'hybrid' => 'Híbrido',
            'electric' => 'Eléctrico',
            'muscle' => 'Tracción humana',
            'other' => 'Otro',
        ],
        'ownership' => [
            'owned' => 'En propiedad',
            'leased' => 'Leasing',
            'rental' => 'Alquiler',
        ],
    ],
    'diary' => [
        'dispatch_status' => [
            'unplanned' => 'Sin planificar',
            'planned' => 'Planificado',
            'confirmed' => 'Confirmado',
            'enRoute' => 'En ruta',
            'done' => 'Completado',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Certificado inicial',
            'follow_up' => 'Certificado de continuación',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Borrador',
            'planned' => 'Planificado',
            'in_progress' => 'En curso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Administración',
            'training' => 'Formación',
            'meeting' => 'Reunión',
            'internal' => 'Interno',
            'travel' => 'Viaje',
            'break' => 'Pausa',
            'absence' => 'Ausencia',
            'standby' => 'Disponibilidad',
            'other' => 'Otro',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Vacaciones',
            'sick' => 'Enfermedad',
            'special' => 'Permiso especial',
            'unpaid' => 'No pagado',
        ],
        'status' => [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Activo',
            'paused' => 'En pausa',
            'archived' => 'Archivado',
        ],
    ],
    'task' => [
        'status' => [
            'open' => 'Abierto',
            'in_progress' => 'En curso',
            'done' => 'Hecho',
        ],
        'priority' => [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Borrador',
            'submitted' => 'Enviado',
            'signed' => 'Firmado',
            'locked' => 'Bloqueado',
        ],
        'kind' => [
            'project' => 'Proyecto',
            'personal_day' => 'Día personal',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Trabajo',
            'travel' => 'Viaje',
            'standby' => 'Disponibilidad',
        ],
    ],
    'expense' => [
        'status' => [
            'draft' => 'Borrador',
            'pending' => 'Enviado',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
            'reimbursed' => 'Reembolsado',
            'invoiced' => 'Facturado',
        ],
        'payment_method' => [
            'private_paid' => 'Pagado de forma privada',
            'company_card' => 'Tarjeta de empresa',
            'cash' => 'Caja',
            'bank_transfer' => 'Transferencia bancaria',
        ],
    ],
    'per_diem' => [
        'day_kind' => [
            'departure_day' => 'Día de salida',
            'full_day' => 'Día completo de viaje',
            'return_day' => 'Día de regreso',
            'single_day' => 'Viaje de un día',
        ],
        'trip_status' => [
            'draft' => 'Borrador',
            'converted' => 'Convertido en gasto',
            'cancelled' => 'Cancelado',
        ],
    ],
    'notification' => [
        'event' => [
            'openIssue' => [
                'assigned' => 'Punto abierto asignado',
                'dueSoon' => 'Punto abierto vence pronto',
                'overdue' => 'Punto abierto vencido',
            ],
            'communication' => [
                'followupDueSoon' => 'Seguimiento vence pronto',
                'followupOverdue' => 'Seguimiento vencido',
            ],
            'document' => [
                'expiringSoon' => 'Documento caduca pronto',
                'expired' => 'Documento caducado',
            ],
            'timeCorrection' => [
                'requested' => 'Solicitud de corrección horaria enviada',
                'decided' => 'Solicitud de corrección horaria decidida',
            ],
            'monthClosure' => [
                'submitted' => 'Cierre mensual enviado',
            ],
            'isms' => [
                'certificateExpiring' => 'Certificado ISMS próximo a vencer',
                'correctiveActionOverdue' => 'Acción correctiva del SGSI vencida',
                'riskReviewDue' => 'Revisión de riesgo del SGSI pendiente',
                'vulnerabilityOverdue' => 'Vulnerabilidad ISMS vencida',
                'incidentCritical' => 'Incidente de seguridad ISMS crítico',
                'supplierReviewOverdue' => 'Revisión de proveedor del SGSI vencida',
            ],
            'sla' => [
                'atRisk' => 'Plazo de SLA en riesgo',
                'breached' => 'Plazo de SLA incumplido',
            ],
            'asset' => [
                'returnOverdue' => 'Devolución de activo vencida',
            ],
            'safety' => [
                'criticalEvent' => 'Evento de seguridad crítico',
            ],
            'qualification' => [
                'expiring' => 'Cualificación próxima a caducar',
            ],
            'shiftExchange' => [
                'requested' => 'Cambio de turno solicitado',
                'decided' => 'Cambio de turno decidido',
            ],
            'customer' => [
                'queryRaised' => 'El cliente ha planteado una consulta',
            ],
        ],
        'channel' => [
            'inApp' => 'En la aplicación',
            'mail' => 'Correo electrónico',
            'push' => 'Push',
        ],
    ],

    'customer-query' => [
        'status' => [
            'open' => 'Abierta',
            'answered' => 'Respondida',
            'closed' => 'Cerrada',
        ],
    ],

    'shift' => [
        'availability_kind' => [
            'available' => 'Disponible',
            'unavailable' => 'No disponible',
            'preferred' => 'Preferido',
        ],
        'preference' => [
            'want' => 'Deseo',
            'avoid' => 'Aversión',
        ],
        'exchange_status' => [
            'requested' => 'Solicitado',
            'accepted' => 'Aceptado',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Retirado',
        ],
    ],

    'sla' => [
        'status' => [
            'none' => 'Sin SLA',
            'met' => 'SLA cumplido',
            'onTrack' => 'SLA en plazo',
            'atRisk' => 'SLA en riesgo',
            'breached' => 'SLA incumplido',
        ],
        'violationKind' => [
            'responseTime' => 'Tiempo de respuesta',
            'resolutionTime' => 'Tiempo de resolución',
        ],
    ],

    'safety' => [
        'kind' => [
            'accident' => 'Accidente',
            'nearMiss' => 'Cuasiaccidente',
            'hazard' => 'Peligro',
            'defect' => 'Defecto',
        ],
        'severity' => [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ],
        'status' => [
            'reported' => 'Notificado',
            'investigating' => 'En investigación',
            'measuresDefined' => 'Medidas definidas',
            'closed' => 'Cerrado',
        ],
    ],

    'open-issue' => [
        'status' => [
            'open' => 'Abierto',
            'inProgress' => 'En curso',
            'blocked' => 'Bloqueado',
            'done' => 'Hecho',
            'wontDo' => 'No se hará',
            'reopened' => 'Reabierto',
        ],
        'severity' => [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ],
        'source' => [
            'manual' => 'Manual',
            'protocolDefect' => 'Desde protocolo',
            'communicationFollowup' => 'Desde comunicación',
            'procedureDeviation' => 'Desde desviación de procedimiento',
            'customerRejection' => 'Rechazo del cliente',
        ],
        'visibility' => [
            'internal' => 'Interno',
            'customer' => 'Visible para el cliente',
        ],
    ],
    'communication' => [
        'type' => [
            'call' => 'Llamada telefónica',
            'email' => 'Correo electrónico',
            'meeting' => 'Reunión presencial',
            'videocall' => 'Videoconferencia',
            'chat' => 'Chat / mensajería',
            'internal' => 'Consulta interna',
            'decision' => 'Decisión',
            'letter' => 'Carta / fax',
            'other' => 'Otro',
        ],
        'direction' => [
            'inbound' => 'Entrante',
            'outbound' => 'Saliente',
            'internal' => 'Interna',
        ],
        'visibility' => [
            'internal' => 'Interna',
            'customer' => 'Visible para el cliente',
        ],
        'party' => [
            'internal' => 'Interno',
            'customer' => 'Cliente',
            'thirdParty' => 'Terceros',
        ],
    ],
    'knowledge' => [
        'status' => [
            'draft' => 'Borrador',
            'published' => 'Publicado',
            'archived' => 'Archivado',
        ],
        'visibility' => [
            'internal' => 'Interno (toda la organización)',
            'team' => 'Limitado al equipo',
        ],
    ],
    'form' => [
        'template_status' => [
            'draft' => 'Borrador',
            'active' => 'Activa',
            'archived' => 'Archivada',
        ],
        'field_type' => [
            'text' => 'Texto',
            'textarea' => 'Texto multilínea',
            'number' => 'Número',
            'date' => 'Fecha',
            'select' => 'Selección',
            'checkbox' => 'Casilla de verificación',
        ],
    ],
    'document' => [
        'type' => [
            'contract' => 'Contrato',
            'testReport' => 'Informe de prueba',
            'certificate' => 'Certificado',
            'manual' => 'Manual',
            'datasheet' => 'Ficha técnica',
            'manufacturerDoc' => 'Documento del fabricante',
            'permit' => 'Permiso',
            'insurance' => 'Seguro',
            'other' => 'Otro',
        ],
        'status' => [
            'draft' => 'Borrador',
            'active' => 'Activo',
            'expired' => 'Vencido',
            'archived' => 'Archivado',
        ],
    ],
    'protocol' => [
        'status' => [
            'draft' => 'Borrador',
            'in_review' => 'En revisión',
            'signed' => 'Firmado',
            'archived' => 'Archivado',
            'superseded' => 'Reemplazado',
        ],
        'type' => [
            'acceptance' => 'Aceptación',
            'service' => 'Aviso de servicio',
            'maintenance' => 'Mantenimiento',
            'handover' => 'Entrega',
            'defect' => 'Informe de defectos',
            'inspection' => 'Inspección',
            'siteVisit' => 'Visita in situ',
            'other' => 'Otro',
        ],
        'visibility' => [
            'internal' => 'Interno',
            'customer' => 'Visible para el cliente',
        ],
        'item-result' => [
            'ok' => 'OK',
            'notok' => 'No OK',
            'n_a' => 'No aplicable',
            'open' => 'Abierto',
        ],
        'signature-role' => [
            'customer' => 'Cliente',
            'contractor' => 'Contratista',
            'witness' => 'Testigo',
        ],
        'signature-method' => [
            'onscreen' => 'Firma en pantalla',
            'portal' => 'Portal del cliente',
            'emailLink' => 'Enlace de correo electrónico',
            'paper' => 'Papel',
        ],
        'item-type' => [
            'group' => 'Sección',
            'text' => 'Texto libre',
            'boolean' => 'Elemento sí/no',
            'choice' => 'Selección única',
            'multichoice' => 'Selección múltiple',
            'number' => 'Medición / número',
            'range' => 'Rango objetivo',
            'date' => 'Fecha',
            'datetime' => 'Fecha y hora',
            'signature' => 'Firma',
            'photo' => 'Foto obligatoria',
            'file' => 'Documento obligatorio',
            'defect' => 'Defecto',
            'measurement.timestamped' => 'Serie de mediciones',
            'procedure_step' => 'Paso de procedimiento',
            'signoff_internal' => 'Aprobación interna',
        ],
        'item-photo-phase' => [
            'before' => 'Antes',
            'after' => 'Después',
            'detail' => 'Detalle',
            'defect' => 'Defecto',
            'reference' => 'Referencia',
        ],
    ],
    'procedure' => [
        'risk-level' => [
            'low' => 'Bajo',
            'normal' => 'Normal',
            'high' => 'Alto',
            'critical' => 'Crítico',
        ],
        'step-type' => [
            'confirm' => 'Confirmación',
            'text' => 'Texto',
            'number' => 'Número/medición',
            'choice' => 'Elección',
            'photo' => 'Foto',
            'file' => 'Archivo',
            'backup' => 'Registro de copia de seguridad',
            'signature' => 'Firma',
            'material' => 'Entrada de material',
            'dienstmittel' => 'Equipo de servicio',
            'freigabe' => 'Aprobación (doble control)',
            'messreihe' => 'Serie de mediciones',
            'link_protocol' => 'Vincular protocolo',
            'link_test' => 'Vincular prueba',
            'wait' => 'Tiempo de espera',
        ],
        'proof-type' => [
            'backup' => 'Copia de seguridad',
            'file' => 'Archivo',
            'photo' => 'Foto',
            'measure' => 'Medición',
            'signature' => 'Firma',
        ],
        'run-status' => [
            'open' => 'Abierto',
            'inProgress' => 'En curso',
            'blocked' => 'Bloqueado',
            'completed' => 'Completado',
            'aborted' => 'Cancelado',
        ],
        'step-run-status' => [
            'pending' => 'Pendiente',
            'done' => 'Hecho',
            'n_a' => 'No aplicable',
            'failed' => 'Fallido',
            'deviated' => 'Desviación',
            'blocked' => 'Bloqueado',
        ],
        'backup-scope' => [
            'config' => 'Configuración',
            'database' => 'Base de datos',
            'fullSystem' => 'Sistema completo',
            'customScript' => 'Script personalizado',
        ],
        'backup-storage-target' => [
            'attachment' => 'Adjunto',
            'external' => 'Almacenamiento externo',
        ],
        'backup-verify-method' => [
            'checksum' => 'Comparación de suma de verificación',
            'restoreCheck' => 'Prueba de restauración',
            'managerConfirmation' => 'Confirmación de la dirección',
        ],
        'deviation-type' => [
            'not_applicable' => 'No aplicable',
            'not_possible' => 'No posible',
            'partial' => 'Cumplido parcialmente',
            'alternative_method' => 'Método alternativo',
            'failed_check' => 'Lectura fuera de tolerancia',
            'material_substitute' => 'Sustituto de material',
            'safety_block' => 'Interrupción por seguridad',
            'customer_decline' => 'Cliente rechazó',
        ],
        'deviation-severity' => [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ],
        'deviation-proposed-action' => [
            'none' => 'Sin acción de seguimiento',
            'open_issue' => 'Punto abierto',
            'new_diary_entry' => 'Nueva orden',
            'requalify' => 'Ejecutar de nuevo',
            'escalate' => 'Escalación',
        ],
    ],
    'duty_plan' => [
        'status' => [
            'draft' => 'Borrador',
            'published' => 'Publicado',
        ],
    ],
    'export' => [
        'entity' => [
            'customers' => 'Clientes',
            'projects' => 'Proyectos',
            'users' => 'Usuarios',
            'materials' => 'Materiales',
            'scheduled_shifts' => 'Turnos planificados',
            'tours' => 'Rutas',
        ],
        'format' => [
            'csv' => 'CSV',
            'xlsx' => 'XLSX',
        ],
        'state' => [
            'preparing' => 'En preparación',
            'ready' => 'Listo',
            'failed' => 'Fallido',
        ],
    ],
    'isms' => [
        'security-incident-category' => [
            'malware' => 'Malware',
            'phishing' => 'Phishing',
            'dataLoss' => 'Pérdida de datos',
            'unauthorizedAccess' => 'Acceso no autorizado',
            'serviceOutage' => 'Interrupción del servicio',
            'misconfiguration' => 'Configuración incorrecta',
            'physical' => 'Incidente físico',
            'other' => 'Otro',
        ],
        'security-incident-status' => [
            'reported' => 'Notificado',
            'triage' => 'Triaje',
            'contained' => 'Contenido',
            'eradicated' => 'Erradicado',
            'recovered' => 'Recuperado',
            'closed' => 'Cerrado',
        ],
        'incident-severity' => [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ],
        'vulnerability-status' => [
            'open' => 'Abierta',
            'underReview' => 'En revisión',
            'mitigating' => 'En mitigación',
            'resolved' => 'Resuelta',
            'accepted' => 'Aceptada',
            'notAffected' => 'No afectado',
        ],
        'exploitability' => [
            'unknown' => 'Desconocida',
            'underInvestigation' => 'En investigación',
            'exploitable' => 'Explotable',
            'notExploitable' => 'No explotable',
        ],
        'vulnerability-source' => [
            'manual' => 'Manual',
            'advisoryImport' => 'Importación de aviso',
        ],
        'supplier-assessment-status' => [
            'draft' => 'Borrador',
            'assessed' => 'Evaluado',
            'approved' => 'Aprobado',
            'flagged' => 'Marcado',
        ],
        'advisory-format' => [
            'csaf' => 'CSAF',
            'vex' => 'VEX',
        ],
        'audit-package-status' => [
            'draft' => 'Borrador',
            'finalized' => 'Finalizado',
        ],
        'audit-kind' => [
            'internal' => 'Interna',
            'external' => 'Externa',
            'supplier' => 'Proveedor',
        ],
        'audit-status' => [
            'planned' => 'Planificada',
            'inPreparation' => 'En preparación',
            'inProgress' => 'En curso',
            'reportIssued' => 'Informe emitido',
            'closed' => 'Cerrada',
        ],
        'finding-kind' => [
            'nonconformityMajor' => 'No conformidad mayor',
            'nonconformityMinor' => 'No conformidad menor',
            'observation' => 'Observación',
            'improvement' => 'Oportunidad de mejora',
        ],
        'finding-status' => [
            'open' => 'Abierto',
            'inCorrection' => 'En corrección',
            'effectivenessCheck' => 'Verificación de eficacia',
            'closed' => 'Cerrado',
        ],
        'corrective-action-status' => [
            'open' => 'Abierta',
            'inProgress' => 'En curso',
            'done' => 'Implementada',
            'effective' => 'Eficaz',
            'ineffective' => 'Ineficaz',
        ],
        'review-status' => [
            'draft' => 'Borrador',
            'approved' => 'Aprobada',
        ],
        'assessment-kind' => [
            'gross' => 'Bruto',
            'net' => 'Neto',
            'target' => 'Objetivo',
        ],
        'assessment-status' => [
            'draft' => 'Borrador',
            'approved' => 'Aprobada',
        ],
        'risk-category' => [
            'organizational' => 'Organizativo',
            'technical' => 'Técnico',
            'physical' => 'Físico',
            'personnel' => 'Personal',
            'supplier' => 'Proveedor',
        ],
        'risk-treatment' => [
            'avoid' => 'Evitar',
            'mitigate' => 'Mitigar',
            'transfer' => 'Transferir',
            'accept' => 'Aceptar',
        ],
        'risk-status' => [
            'identified' => 'Identificado',
            'analyzed' => 'Analizado',
            'treated' => 'Tratado',
            'accepted' => 'Aceptado',
            'closed' => 'Cerrado',
        ],
        'requirement-source' => [
            'catalog' => 'Catálogo de referencia',
            'custom' => 'Requisito propio',
        ],
        'control-implementation-status' => [
            'open' => 'Abierto',
            'partial' => 'Parcialmente implantado',
            'implemented' => 'Implantado',
            'notApplicable' => 'No aplicable',
        ],
        'software-category' => [
            'os' => 'Sistema operativo',
            'application' => 'Aplicación',
            'service' => 'Servicio',
            'library' => 'Biblioteca',
            'other' => 'Otro',
        ],
        'support-status' => [
            'supported' => 'Con soporte',
            'extendedSupport' => 'Soporte extendido',
            'endOfLife' => 'Fin de vida',
            'unknown' => 'Desconocido',
        ],
        'norm-conformity-status' => [
            'notAssessed' => 'No evaluado',
            'gapAnalysisDone' => 'Análisis de brechas realizado',
            'inProgress' => 'En implementación',
            'internallyAuditReady' => 'Listo para auditoría interna',
            'externalAuditPlanned' => 'Auditoría externa planificada',
            'certified' => 'Certificado',
            'certificateSuspended' => 'Certificado suspendido',
            'certificateExpired' => 'Certificado caducado',
        ],
    ],
    'surcharge' => [
        'kind' => [
            'night' => 'Noche',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo',
            'holiday' => 'Día festivo',
            'custom' => 'Personalizado',
        ],
    ],
    'finance' => [
        'billing-mode' => [
            'workdiary' => 'WorkDiary (local)',
            'lexoffice' => 'Lexoffice dirige',
            'datev' => 'DATEV dirige',
        ],
        'transfer-channel' => [
            'time' => 'Servicios/tiempo',
            'material' => 'Productos/material',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'file' => 'Exportación de archivo',
        ],
        'transfer-status' => [
            'draft' => 'Borrador',
            'confirmed' => 'Confirmado',
            'transferred' => 'Traspasado',
            'failed' => 'Fallido',
            'voided' => 'Anulado',
        ],
        'chart-of-accounts' => [
            'skr03' => 'SKR03',
            'skr04' => 'SKR04',
        ],
        'datev-batch-status' => [
            'draft' => 'Borrador',
            'exported' => 'Exportado',
        ],
        // Conciliación de pagos (Feature 045, prioridad 3).
        'bank-statement-format' => [
            'camt053' => 'CAMT.053',
            'mt940' => 'MT940',
        ],
        'transaction-direction' => [
            'credit' => 'Entrada',
            'debit' => 'Salida',
        ],
        'balance-check' => [
            'ok' => 'Cadena de saldos coherente',
            'mismatch' => 'Diferencia de saldo',
            'unknown' => 'Saldos incompletos',
        ],
        'match-status' => [
            'unmatched' => 'Abierto',
            'suggested' => 'Sugerencias',
            'matched' => 'Asignado',
            'ignored' => 'Apartado',
            'unassignable' => 'No asignable',
            'duplicate' => 'Duplicado',
        ],
        'allocation-kind' => [
            'payment' => 'Pago',
            'partial' => 'Pago parcial',
            'overpayment' => 'Sobrepago',
            'reimbursement' => 'Reembolso',
        ],
    ],

    // Cierre diario (MVP-015, docs/tagesabschluss.md §3/§5).
    'dayClosure' => [
        'status' => [
            'open' => 'Abierto',
            'closed' => 'Cerrado',
            'correction' => 'En corrección',
            'locked' => 'Bloqueado',
        ],
    ],
    'dayCorrection' => [
        'status' => [
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
        ],
    ],

    // Resultado de la prueba de restauración (Feature 017).
    'backup' => [
        'restore-test-result' => [
            'passed' => 'Superada',
            'partial' => 'Con condiciones',
            'failed' => 'Fallida',
        ],
    ],
];
