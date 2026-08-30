<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : enums.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'billbee' => [
        'order_state' => [
            1 => 'Pedido',
            2 => 'Confirmado',
            3 => 'Pagado',
            4 => 'Enviado',
            5 => 'Reclamación',
            6 => 'Eliminado',
            7 => 'Completado',
            8 => 'Cancelado',
            9 => 'Archivado',
            11 => '1er recordatorio',
            12 => '2º recordatorio',
            13 => 'Empaquetado',
            14 => 'Ofertado',
            15 => 'Recordatorio de pago',
            16 => 'En fulfillment',
        ],
    ],
    'ai' => [
        'family' => ['llm' => 'Modelo de lenguaje (LLM)', 'translation' => 'Traducción'],
        'verb' => ['formulate' => 'Formular', 'summarize' => 'Resumir', 'classify' => 'Clasificar', 'explain' => 'Explicar', 'find' => 'Buscar', 'translate' => 'Traducir', 'extract' => 'Extraer'],
        'provider' => ['anthropic' => 'Anthropic Claude', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'azure_openai' => 'Azure OpenAI', 'openai_compatible' => 'Compatible con OpenAI (genérico)', 'ollama' => 'Ollama (local)', 'deepl' => 'DeepL', 'azure_translator' => 'Azure Translator', 'google_translate' => 'Google Cloud Translation', 'libretranslate' => 'LibreTranslate (local)', 'fake' => 'Proveedor de prueba'],
        'connection_status' => ['draft' => 'Borrador', 'active' => 'Activo', 'blocked' => 'Bloqueado'],
        'memory_type' => ['glossary' => 'Glosario', 'style_rule' => 'Regla de estilo', 'example' => 'Par de ejemplo'],
        'sensitivity' => ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'],
    ],
    'domain' => [
        'environment' => ['ote' => 'OT&E (prueba/piloto)', 'production' => 'Producción'],
        'connection_status' => ['draft' => 'Borrador', 'active' => 'Activo', 'blocked' => 'Bloqueado'],
        'sync_status' => ['current' => 'Actual', 'stale' => 'Obsoleto', 'pending' => 'Pendiente', 'conflict' => 'Conflicto', 'unknown' => 'Incierto'],
        'renewal_mode' => ['autorenew' => 'Renovación automática', 'autoexpire' => 'Expiración automática', 'autodelete' => 'Eliminación automática', 'renewonce' => 'Renovar una vez'],
        'command_status' => ['draft' => 'Borrador', 'approved' => 'Aprobado', 'pending' => 'Pendiente', 'confirmed' => 'Confirmado', 'failed' => 'Fallido', 'unknown' => 'Incierto', 'conflict' => 'Conflicto'],
        'capability_area' => ['authentication' => 'Autenticación', 'subuser' => 'Subusuario', 'domains' => 'Dominios', 'contacts' => 'Contactos', 'nameservers' => 'Servidores de nombres', 'dns' => 'Zonas DNS', 'events' => 'Eventos', 'renewal' => 'Renovación', 'transfer' => 'Transferencia', 'accounting' => 'Contabilidad', 'invoices' => 'Facturas'],
    ],
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
        'ownership' => [
            'org' => 'Organización',
            'customer' => 'Cliente',
            'external' => 'Externo',
        ],
    ],
    'classification' => [
        'requirement-phase' => [
            'onCreate' => 'Al crear',
            'beforeComplete' => 'Antes de completar',
            'beforeSign' => 'Antes de firmar',
        ],
        'requirement-severity' => [
            'hard' => 'Bloqueante',
            'soft' => 'Aviso',
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
                'waitlisted' => 'Lista de espera',
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
    'cloud_intake' => [
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
        ],
        'connection_status' => [
            'draft' => 'Borrador',
            'active' => 'Activa',
            'reauth_required' => 'Reautenticación necesaria',
            'blocked' => 'Bloqueada',
            'disabled' => 'Desactivada',
        ],
        'route_target' => [
            'incoming_invoice' => 'Facturas entrantes',
            'document' => 'Documento (DMS)',
            'b2b_order' => 'Pedido B2B (openTRANS)',
            'gaeb_package' => 'Documentos de licitación (paquete GAEB)',
        ],
        'item_status' => [
            'imported' => 'Importado',
            'inbox' => 'Bandeja',
            'rejected' => 'Rechazado',
            'duplicate' => 'Duplicado',
            'source_gone' => 'Origen eliminado',
        ],
    ],
    'product' => [
        'status' => [
            'active' => 'Activo',
            'phasing_out' => 'En retirada',
            'discontinued' => 'Descatalogado',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Activo',
            'paused' => 'En pausa',
            'archived' => 'Archivado',
        ],
    ],
    'access' => [
        'medium_status' => ['in_stock' => 'En almacén', 'issued' => 'Entregado', 'lost' => 'Perdido', 'blocked' => 'Bloqueado', 'retired' => 'Retirado'],
        'medium_type' => ['transponder' => 'Transpondedor', 'card' => 'Tarjeta', 'code' => 'Código'],
    ],
    'sales' => [
        'lead_status' => ['new' => 'Nuevo', 'contacted' => 'Contactado', 'qualified' => 'Cualificado', 'converted' => 'Convertido', 'discarded' => 'Descartado'],
        'lead_source' => ['referral' => 'Recomendación', 'web' => 'Web', 'trade_fair' => 'Feria', 'phone' => 'Teléfono', 'other' => 'Otro'],
    ],
    // Sicherheitseinbehalte (Feature 113, MVP-602).
    // Bürgschaftsregister (Feature 114, MVP-603).
    // Gewährleistungsfristen (Feature 115, MVP-604).
    'warranty_side' => [
        'owed' => 'Responsabilidad propia',
        'claimable' => 'Exigible (subcontrata)',
    ],
    'warranty_basis' => [
        'bgb_5y' => 'BGB, 5 años',
        'vob_4y' => 'VOB/B, 4 años',
        'custom' => 'Libremente pactado',
    ],
    'warranty_status' => [
        'open' => 'Abierto',
        'closed' => 'Cerrado',
        'claimed' => 'Reclamado',
    ],
    // Pflichtnachweise (Feature 117, MVP-606).
    'credential_status' => [
        'ok' => 'Completo',
        'expiring' => 'Por vencer',
        'missing' => 'Falta',
        'expired' => 'Caducado',
    ],
    'guarantee_direction' => [
        'issued' => 'Prestado',
        'received' => 'Recibido',
    ],
    'guarantee_kind' => [
        'performance' => 'Aval de cumplimiento',
        'warranty' => 'Aval de garantía',
        'advance_payment' => 'Aval de anticipo',
        'defects' => 'Aval por vicios',
    ],
    'guarantee_status' => [
        'active' => 'Activo',
        'returned' => 'Devuelto',
        'drawn' => 'Ejecutado',
        'expired' => 'Vencido',
    ],
    'payment_run_kind' => [
        'credit_transfer' => 'Transferencia agrupada',
        'direct_debit' => 'Adeudo agrupado',
    ],
    'payment_run_status' => [
        'draft' => 'Borrador',
        'released' => 'Aprobada',
        'exported' => 'Exportada',
        'cancelled' => 'Anulada',
    ],
    'sepa_mandate_kind' => [
        'one_off' => 'Puntual',
        'recurring' => 'Recurrente',
    ],
    'sepa_mandate_status' => [
        'active' => 'Activo',
        'revoked' => 'Revocado',
        'expired' => 'Caducado',
    ],
    'retention_base' => [
        'net' => 'Importe neto',
        'gross' => 'Importe bruto',
    ],
    'retention_kind' => [
        'warranty' => 'Retención de garantía',
        'performance' => 'Retención de cumplimiento',
    ],
    'retention_status' => [
        'open' => 'Abierta',
        'released' => 'Liberada',
        'secured' => 'Sustituida por un aval',
    ],
    'sync_command' => [
        'status' => ['applied' => 'Aplicado', 'duplicate' => 'Duplicado', 'conflict' => 'Conflicto', 'rejected' => 'Rechazado'],
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
            'crisis' => [
                'alert' => 'Alerta de crisis',
            ],
            'claim' => [
                'escalation' => 'Reclamación vencida',
            ],
            'rental' => [
                'returnOverdue' => 'Devolución de alquiler vencida',
                'requested' => 'Solicitud de alquiler recibida desde el portal',
            ],
            'assetFinance' => [
                'deadline' => 'Plazo de leasing vencido',
            ],
            'contract' => [
                'deadlineDue' => 'Plazo contractual vencido',
            ],
            'accounting' => [
                'recurringOverdue' => 'Operación recurrente vencida',
                'filingDue' => 'Plazo de declaración próximo',
            ],
            'invoice' => [
                'recurringDraft' => 'Borrador de factura desde plan de facturación',
            ],
            'fleet' => [
                'licenseCheckDue' => 'Control del permiso de conducir pendiente',
            ],
            'drivingTime' => [
                'violation' => 'Hallazgo de tiempos de conducción/descanso',
            ],
            'recruiting' => [
                'applicationReceived' => 'Candidatura pública recibida',
            ],
            'assetCompliance' => [
                'inspectionDue' => 'Inspección pendiente/vencida',
            ],
            'ticket' => [
                'assigned' => 'Ticket asignado',
                'customerReplied' => 'El cliente respondió',
                'waitingExpired' => 'Seguimiento de ticket vencido',
            ],
            'problem' => [
                'effectivenessDue' => 'Comprobación de eficacia de un problema pendiente',
            ],
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
            'overtime' => [
                'requested' => 'Solicitud de horas extra presentada',
                'decided' => 'Solicitud de horas extra decidida',
            ],
            'vacation' => [
                'requested' => 'Solicitud de vacaciones enviada',
                'decided' => 'Solicitud de vacaciones decidida',
            ],
            'attendance' => [
                'unclearCase' => 'Caso por aclarar (fichajes)',
            ],
            'monthClosure' => [
                'submitted' => 'Cierre mensual enviado',
                'decided' => 'Cierre mensual decidido',
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
                'quotaWarning' => 'Cuota de SLA casi agotada',
            ],
            'asset' => [
                'returnOverdue' => 'Devolución de activo vencida',
            ],
            'tender' => [
                'submissionDueSoon' => 'Plazo de oferta próximo',
                'submissionOverdue' => 'Plazo de oferta vencido',
                'bindingExpiring' => 'Plazo de vinculación expirando',
            ],
            'safety' => [
                'criticalEvent' => 'Evento de seguridad crítico',
                'assessmentReviewDue' => 'Evaluación de riesgos: revisión pendiente',
                'instructionDue' => 'Formación de seguridad a repetir',
                'checkupDue' => 'Reconocimiento médico laboral pendiente',
            ],
            'training' => [
                'due' => 'Formación obligatoria pendiente',
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
            'ideaMap' => [
                'shared' => 'Mapa de ideas compartido con usted',
            ],
            'shipment' => [
                'deliveryProblem' => 'Problema de entrega de un envío',
            ],
            'cti' => [
                'incomingCall' => 'Llamada entrante',
            ],
            'maintenance' => [
                'dueSoon' => 'Mantenimiento/inspección próximo',
                'overdue' => 'Mantenimiento/inspección vencido',
            ],
            'domain' => [
                'expiring' => 'Dominio por expirar / renovación fallida',
                'transferChanged' => 'Estado de transferencia de dominio cambiado',
                'syncFailed' => 'Sincronización de dominio fallida',
                'highRiskAction' => 'Acción de dominio de alto riesgo aprobada',
            ],
            'finance' => [
                'retentionReleaseDue' => 'Liberación de retención pendiente',
                'guaranteeExpiring' => 'Aval que vence',
                'guaranteeReturnDue' => 'Devolución de aval pendiente',
                'transferFailed' => 'Transferencia de facturación fallida',
                'bankImportFailed' => 'Importación bancaria fallida',
                'reconciliationReview' => 'Conciliación de pagos requiere revisión',
            ],
            'investment' => [
                'decisionDue' => 'Decisión de inversión pendiente',
                'decided' => 'Solicitud de inversión decidida',
            ],
            'inventory' => [
                'lotExpiring' => 'Lote por caducar (consumo preferente)',
            ],
            'operations' => [
                'backupOverdue' => 'Copia de seguridad atrasada',
                'backupFailed' => 'Copia de seguridad fallida',
                'restoreTestOverdue' => 'Prueba de restauración atrasada',
                'updateAvailable' => 'Actualización disponible',
                'updateSecurity' => 'Actualización de seguridad disponible',
                'licenseExpiring' => 'La licencia caduca pronto',
                'credentialExpiring' => 'La credencial/el token caduca pronto',
                'connectionFailing' => 'Conexión con fallos',
                'componentEol' => 'Componente sin soporte (EOL)',
                'pluginDisabled' => 'Plugin desactivado automáticamente',
                'schedulerOverdue' => 'Tarea programada atrasada',
                'queueDegraded' => 'Cola degradada',
                'maintenanceScheduled' => 'Ventana de mantenimiento anunciada',
                'problemReportReceived' => 'Nuevo informe de problema recibido',
                'cloudIntakeReauth' => 'Entrada en la nube: se requiere iniciar sesión',
                'cloudIntakeQuarantined' => 'Entrada en la nube: importaciones rechazadas',
            ],
            'quote' => [
                'followUpDue' => 'Presupuesto: seguimiento pendiente',
                'expiringWithoutReaction' => 'Presupuesto que vence sin reacción',
            ],
            'weather' => [
                'warning' => 'Aviso meteorológico para una intervención',
            ],
            'warranty' => [
                'expiring' => 'Garantía que vence',
                'subcontractorEndsFirst' => 'El plazo del subcontratista acaba antes que el propio',
            ],
            'supplier' => ['credentialExpiring' => 'Justificante obligatorio que vence'],
            'security' => [
                'integrity' => 'Integridad del código fuente',
                'threat' => 'Detección de ataques',
                'newDevice' => 'Inicio de sesión desde un dispositivo nuevo',
                'lockout' => 'Cuenta bloqueada temporalmente',
            ],
            'diary' => [
                'commentCreated' => 'Nuevo comentario en el libro de órdenes',
                'problem' => 'Entrada del libro con problema',
                'completed' => 'Entrada del libro completada',
                'attachmentAdded' => 'Nuevo adjunto en el libro de órdenes',
            ],
            'emergency' => ['assigned' => 'Servicio de urgencia asignado'],
            'timesheet' => ['signed' => 'Hoja de horas firmada'],
            'chat' => [
                'message' => 'Mensaje de chat',
                'reminder' => 'Recordatorio de chat',
            ],
        ],
        'channel' => [
            'inApp' => 'En la aplicación',
            'mail' => 'Correo electrónico',
            'push' => 'Push',
            'teams' => 'Microsoft Teams',
            'mattermost' => 'Mattermost',
            'calendar' => 'Calendario',
            'sms' => 'SMS',
        ],
        'sms_status' => [
            'sent' => 'Enviado',
            'delivered' => 'Entregado',
            'failed' => 'Fallido',
            'blocked' => 'No enviado',
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
            'off' => 'Día libre deseado',
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
        'quotaPeriod' => [
            'month' => 'Mes',
            'quarter' => 'Trimestre',
            'year' => 'Año',
        ],
    ],

    'training' => [
        'provider-kind' => [
            'internal' => 'Interno',
            'external' => 'Externo',
        ],
        'requirement-subject' => [
            'role' => 'Rol',
            'team' => 'Área de actividad (equipo)',
        ],
        'assignment-state' => [
            'fulfilled' => 'Cumplido',
            'planned' => 'Planificado',
            'due' => 'Pendiente',
            'overdue' => 'Vencido',
        ],
    ],

    'safety' => [
        'assessment-status' => [
            'draft' => 'Borrador',
            'inReview' => 'En revisión',
            'approved' => 'Aprobada',
            'archived' => 'Archivada',
        ],
        'checkup-kind' => [
            'mandatory' => 'Reconocimiento obligatorio',
            'offered' => 'Reconocimiento ofrecido',
            'requested' => 'Reconocimiento a petición',
        ],
        'signature-method' => [
            'confirmed' => 'Clic de confirmación',
            'drawn' => 'Firma (imagen)',
        ],
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
            'patrolDeviation' => 'De desviación de ronda',
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
            'photo' => 'Foto',
            'file' => 'Archivo',
            'signature' => 'Firma',
        ],
    ],
    // Digitale Personalakte (Feature 141, MVP-708) — bewusst ohne Gesundheitskategorie.
    'hr_document_category' => [
        'contract' => 'Contrato de trabajo',
        'amendment' => 'Modificación contractual / anexo',
        'certificate' => 'Certificado / constancia',
        'training' => 'Formación / cualificación',
        'warning' => 'Amonestación',
        'idDocument' => 'Documento de identidad / justificante',
        'payrollReference' => 'Documento de referencia de nómina',
        'other' => 'Otro',
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
            'invoice' => 'Factura',
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
    'compliance' => [
        'finding-status' => [
            'open' => 'Abierto',
            'acknowledged' => 'Confirmado',
            'resolved' => 'Resuelto',
            'accepted' => 'Aceptado',
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
            'oncall' => 'Guardia presencial',
            'standby' => 'Guardia localizada',
            'overtime' => 'Horas extra',
        ],
    ],
    // Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098).
    'billing' => [
        // Belegfluss (Feature 105, MVP-542)
        'direction' => [
            'outgoing' => 'Saliente',
            'incoming' => 'Entrante',
            'neutral' => 'Sin efecto monetario',
        ],
        'kind' => [
            'quote' => 'Presupuesto',
            'order_confirmation' => 'Confirmación de pedido',
            'delivery_note' => 'Albarán',
            'invoice' => 'Factura',
            'down_payment' => 'Factura de anticipo',
            'down_payment_deduction' => 'Deducción de anticipo',
            'credit_note' => 'Abono',
            'cancellation' => 'Anulación',
            'expense' => 'Gasto',
            'other' => 'Otro documento',
        ],
        'origin' => [
            'local' => 'Local',
            'lexoffice' => 'Lexoffice',
            'orgamax' => 'orgaMAX',
            'sevdesk' => 'sevDesk',
            'easybill' => 'easybill',
            'invoiceplane' => 'InvoicePlane',
            'jtl_wawi' => 'JTL-Wawi',
        ],
        'agreement-mode' => [
            'account' => 'Cuenta de cliente (sin factura)',
            'invoice' => 'Factura mensual',
            'retainer' => 'Cuota fija (Lexoffice)',
        ],
        'rate-day-type' => [
            'weekday' => 'Día laborable',
            'weekend' => 'Fin de semana',
        ],
        'account-payment-source' => [
            'manual' => 'Manual',
            'bank' => 'Banco',
            'import' => 'Importación',
            'lexoffice' => 'Lexoffice',
        ],
    ],
    'finance' => [
        // Versteuerungsart (Feature 125, MVP-679).
        'taxation-method' => [
            'debit' => 'Devengo',
            'credit' => 'Caja',
        ],
        // Steuerliche Meldepflichten (Feature 125, MVP-686).
        'filing-obligation-kind' => [
            'vat_advance' => 'Declaración periódica de IVA',
            'special_prepayment' => 'Pago anticipado especial',
            'recapitulative' => 'Estado recapitulativo',
            'annual_return' => 'Declaración anual de IVA',
        ],
        'filing-obligation-status' => [
            'open' => 'Abierto',
            'submitted' => 'Presentado',
            'not_required' => 'No necesario',
        ],
        // Voranmeldungszeitraum der Umsatzsteuer (Feature 125, MVP-684).
        'vat-filing-interval' => [
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
            'annual' => 'Solo declaración anual',
            'none' => 'Sin declaración periódica',
        ],
        // Zeilen der Anlage EÜR (Feature 125, MVP-680).
        'euer-category' => [
            'income' => 'Ingresos de explotación',
            'income_vat' => 'IVA repercutido cobrado',
            'private_use' => 'Uso privado',
            'expense' => 'Gastos de explotación',
            'depreciation' => 'Amortizaciones',
            'low_value_asset' => 'Bienes de escaso valor',
            'input_tax' => 'IVA soportado pagado',
            'paid_vat' => 'IVA pagado',
            'limited_deductible' => 'Deducción limitada',
            'not_deductible' => 'No deducible',
        ],
        // Wiederkehrende Vorgänge (Feature 125, MVP-675).
        'recurring-template-kind' => [
            'document_expectation' => 'Expectativa de documento',
            'posting_template' => 'Plantilla de asiento',
        ],
        'recurring-interval' => [
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
            'semi_annually' => 'Semestral',
            'annually' => 'Anual',
        ],
        'recurring-run-status' => [
            'expected' => 'Documento esperado',
            'draft_created' => 'Borrador creado',
            'fulfilled' => 'Cumplido',
            'blocked' => 'Bloqueado',
            'skipped' => 'Omitido',
        ],
        'recurring-template-status' => [
            'active' => 'Activa',
            'paused' => 'Pausada',
            'ended' => 'Finalizada',
        ],
        // Offene Posten (Feature 125, MVP-674).
        'open-item-direction' => [
            'receivable' => 'Derecho de cobro',
            'payable' => 'Obligación de pago',
        ],
        'open-item-status' => [
            'open' => 'Abierta',
            'partially_settled' => 'Parcialmente compensada',
            'settled' => 'Compensada',
            'disputed' => 'En disputa',
        ],
        'settlement-kind' => [
            'payment' => 'Pago',
            'discount' => 'Descuento',
            'retention' => 'Retención',
            'write_off' => 'Baja',
            'overpayment' => 'Exceso',
            'reversal' => 'Reversión',
        ],
        // Quellenadapter und Buchungsregeln (Feature 125, MVP-673).
        'posting-source-kind' => [
            'sales_invoice' => 'Factura emitida',
            'incoming_invoice' => 'Factura recibida',
            'expense' => 'Gasto',
            'cash_entry' => 'Libro de caja',
            'payment' => 'Pago',
            'depreciation' => 'Amortización',
        ],
        'posting-account-role' => [
            'receivable' => 'Cliente',
            'revenue' => 'Ingreso',
            'tax_output' => 'IVA repercutido',
            'payable' => 'Proveedor',
            'expense' => 'Gasto',
            'tax_input' => 'IVA soportado',
            'cash' => 'Caja',
            'employee_payable' => 'Deuda con empleados',
            'bank' => 'Banco',
            'discount' => 'Descuento',
            'fixed_asset' => 'Cuenta de activo',
            'depreciation' => 'Gasto por amortización',
        ],
        // Buchungskern (Feature 125, MVP-672).
        'balance-side' => [
            'debit' => 'Debe',
            'credit' => 'Haber',
        ],
        'account-type' => [
            'asset' => 'Activo',
            'liability' => 'Pasivo',
            'equity' => 'Patrimonio neto',
            'income' => 'Ingresos',
            'expense' => 'Gastos',
        ],
        'bwa-group' => [
            'revenue' => 'Ingresos por ventas',
            'inventory_change' => 'Variación de existencias / trabajos propios activados',
            'material' => 'Gastos de material',
            'other_operating_income' => 'Otros ingresos operativos',
            'personnel' => 'Gastos de personal',
            'premises' => 'Gastos de locales',
            'operating_taxes' => 'Impuestos operativos',
            'insurance_fees' => 'Seguros / cuotas',
            'vehicle' => 'Gastos de vehículos',
            'marketing_travel' => 'Gastos de publicidad / viajes',
            'goods_dispatch' => 'Gastos de expedición de mercancías',
            'depreciation' => 'Amortizaciones',
            'repairs' => 'Reparaciones / mantenimiento',
            'other_costs' => 'Otros gastos',
            'interest_expense' => 'Gastos por intereses',
            'neutral_expense' => 'Otros gastos neutros',
            'interest_income' => 'Ingresos por intereses',
            'neutral_income' => 'Otros ingresos neutros',
            'income_taxes' => 'Impuestos sobre beneficios',
        ],
        'accounting-entry-status' => [
            'draft' => 'Borrador',
            'ready' => 'Revisado',
            'posted' => 'Contabilizado',
            'reversed' => 'Anulado',
        ],
        'tax-code-direction' => [
            'output' => 'IVA repercutido',
            'input' => 'IVA soportado',
            'none' => 'Sin impuesto',
        ],
        // Lokale Buchhaltung (Feature 125, MVP-671).
        'accounting-sovereignty' => [
            'preaccounting' => 'Precontabilidad (sin libro mayor)',
            'local' => 'workDiary dirige',
            'external' => 'Sistema externo dirige',
        ],
        'profit-determination' => [
            'euer' => 'Estimación directa simplificada (criterio de caja)',
            'double_entry' => 'Contabilidad por partida doble',
        ],
        // Anlagenregister (Feature 133, MVP-698).
        'fixed-asset-status' => [
            'active' => 'Activo',
            'disposed' => 'Dado de baja',
        ],
        'depreciation-method' => [
            'linear' => 'Lineal',
        ],
        'accounting-period-status' => [
            'open' => 'Abierto',
            'soft_closed' => 'Cerrado provisionalmente',
            'closed' => 'Cerrado',
        ],
        'billing-mode' => [
            'workdiary' => 'WorkDiary (local)',
            'lexoffice' => 'Lexoffice dirige',
            'datev' => 'DATEV dirige',
            'orgamax' => 'orgaMAX lidera',
            'sevdesk' => 'sevDesk lidera',
            'easybill' => 'easybill lidera',
        ],
        'transfer-channel' => [
            'time' => 'Servicios/tiempo',
            'material' => 'Productos/material',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'orgamax' => 'orgaMAX (pedido)',
            'sevdesk' => 'sevDesk (borrador de factura)',
            'easybill' => 'easybill (borrador de factura)',
            'file' => 'Exportación de archivo',
        ],
        'transfer-status' => [
            'draft' => 'Borrador',
            'confirmed' => 'Confirmado',
            'transferred' => 'Traspasado',
            'failed' => 'Fallido',
            'voided' => 'Anulado',
            'cancelled' => 'Cancelado',
        ],
        'chart-of-accounts' => [
            'skr03' => 'SKR03',
            'skr04' => 'SKR04',
        ],
        // GoBD-Z3-Lauf (Feature 063, MVP-722).
        'gobd-export-status' => [
            'queued' => 'En cola',
            'running' => 'En curso',
            'ready' => 'Listo',
            'failed' => 'Fallido',
        ],
        'datev-batch-status' => [
            'draft' => 'Borrador',
            'exported' => 'Exportado',
        ],
        // Conciliación de pagos (Feature 045, prioridad 3).
        'bank-statement-format' => [
            'camt053' => 'CAMT.053',
            'mt940' => 'MT940',
            'ofx' => 'OFX',
            'qif' => 'QIF',
            'qxf' => 'QXF',
            'pain001' => 'PAIN.001',
            'pain008' => 'PAIN.008',
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
            'chargeback' => 'Devolución de adeudo',
            'skonto' => 'Descuento por pronto pago (reducción de ingresos)',
        ],
        'procedure-documentation-status' => [
            'draft' => 'Borrador',
            'published' => 'Publicada',
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
        // Destinos de copia de seguridad en la nube (función 017, fase 32).
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
            'webdav' => 'WebDAV (servidor propio)',
        ],
        'target_status' => [
            'draft' => 'Borrador',
            'active' => 'Activa',
            'reauth_required' => 'Requiere nuevo inicio de sesión',
            'blocked' => 'Bloqueada',
            'disabled' => 'Desactivada',
        ],
        'generation_status' => [
            'building' => 'En creación',
            'uploading' => 'Subiendo',
            'committed' => 'Completada',
            'verified' => 'Verificada',
            'verify_failed' => 'Verificación fallida',
            'failed' => 'Fallida',
        ],
        'retention_class' => [
            'daily' => 'Diaria',
            'weekly' => 'Semanal',
            'monthly' => 'Mensual',
        ],
        'restore-test-result' => [
            'passed' => 'Superada',
            'partial' => 'Con condiciones',
            'failed' => 'Fallida',
        ],
    ],

    // Acción al vencer un plan de mantenimiento (Feature 010 → Rango 43).
    'maintenance' => [
        'due_action' => [
            'none' => 'Solo aviso (sin registro)',
            'ticket' => 'Crear un ticket de servicio',
        ],
    ],

    'security' => [
        'integrity_check_status' => [
            'baseline' => 'Línea base creada',
            'ok' => 'Correcto',
            'deviation' => 'Desviación',
            'missing_baseline' => 'Sin línea base',
            'error' => 'Error',
        ],
    ],

    'passenger' => [
        'operation_mode' => [
            'taxi' => 'Servicio de taxi (§ 47 PBefG)',
            'rental_car' => 'Alquiler con conductor (§ 49 PBefG)',
            'pooled_on_demand' => 'Transporte a demanda agrupado (§ 50 PBefG)',
        ],
        'ride_status' => [
            'requested' => 'Solicitado',
            'accepted' => 'Aceptado',
            'assigned' => 'Asignado',
            'en_route_pickup' => 'En camino a la recogida',
            'waiting' => 'En espera',
            'occupied' => 'Ocupado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'no_show' => 'Pasajero ausente',
            'aborted' => 'Interrumpido',
        ],
        'price_kind' => [
            'tariff' => 'Tarifa',
            'fixed_price' => 'Precio fijo',
            'contract' => 'Precio contractual',
        ],
        'order_channel' => [
            'hail' => 'Parada a mano / estación',
            'phone' => 'Teléfono',
            'app' => 'Aplicación',
            'web' => 'Web',
            'mediator' => 'Central de despacho',
            'contract' => 'Contrato marco',
        ],
    ],
    'print' => [
        'order_status' => [
            'data_check' => 'Comprobación de datos',
            'approved' => 'Aprobado',
            'in_production' => 'En producción',
            'quality_check' => 'Control de calidad',
            'rework' => 'Retrabajo',
            'ready' => 'Listo para entrega',
            'issued' => 'Entregado',
            'cancelled' => 'Anulado',
        ],
        'preflight_status' => [
            'pending' => 'Pendiente',
            'passed' => 'Superado',
            'warnings' => 'Con advertencias',
            'failed' => 'Fallido',
            'overridden' => 'Anulado con motivo',
        ],
        'output_kind' => [
            'pickup' => 'Recogida',
            'shipping' => 'Envío',
            'counter' => 'Venta en mostrador',
        ],
    ],
    // Lernplattform (Feature 149)
    'learning' => [
        'booking-status' => [
            'requested' => 'Solicitada',
            'confirmed' => 'Confirmada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
        ],
        'submission-status' => [
            'draft' => 'Borrador',
            'submitted' => 'Entregado',
            'returned' => 'Devuelto',
            'graded' => 'Corregido',
        ],
        'question-kind' => [
            'single' => 'Opción única',
            'multiple' => 'Opción múltiple',
            'true_false' => 'Verdadero/falso',
            'short_text' => 'Texto corto',
            'cloze' => 'Rellenar huecos',
            'sort' => 'Ordenar',
            'matching' => 'Emparejar',
            'essay' => 'Ensayo',
            'hotspot' => 'Marcado en imagen',
            'matrix' => 'Asignación matricial',
        ],
        'feedback-mode' => [
            'immediate' => 'Inmediata',
            'end' => 'Al final',
            'none' => 'Ninguna',
        ],
        'block-kind' => [
            'heading' => 'Título',
            'text' => 'Texto',
            'callout' => 'Aviso',
            'checklist' => 'Lista de control',
            'image' => 'Imagen',
            'file' => 'Archivo',
            'video' => 'Vídeo',
            'embed' => 'Inserción',
            'knowledge' => 'Artículo de conocimiento',
        ],
        'enrollment-status' => [
            'assigned' => 'Asignado',
            'in_progress' => 'En curso',
            'completed' => 'Completado',
            'failed' => 'No superado',
            'expired' => 'Caducado',
            'cancelled' => 'Cancelado',
        ],
        'enrollment-source' => [
            'requirement' => 'Matriz obligatoria',
            'manual' => 'Manual',
            'self' => 'Autoinscripción',
            'booking' => 'Reserva',
            'rule' => 'Regla',
            'path' => 'Itinerario',
        ],
        'translation-status' => [
            'draft' => 'Borrador',
            'approved' => 'Aprobada',
        ],
        'progress-status' => [
            'open' => 'Abierto',
            'started' => 'Iniciado',
            'completed' => 'Completado',
        ],
        'course-status' => [
            'draft' => 'Borrador',
            'review' => 'En revisión',
            'released' => 'Publicado',
            'archived' => 'Archivado',
        ],
        'audience' => [
            'internal' => 'Interno',
            'external' => 'Participantes externos',
            'customer' => 'Clientes',
            'public' => 'Público',
        ],
        'access-kind' => [
            'open' => 'Abierto',
            'enrolled' => 'Inscrito',
            'bookable' => 'Reservable',
            'closed' => 'Cerrado',
        ],
        'unit-kind' => [
            'content' => 'Contenido',
            'quiz' => 'Examen',
            'assignment' => 'Tarea',
            'procedure' => 'Procedimiento',
            'event' => 'Cita',
            'scorm' => 'Paquete SCORM',
            'survey' => 'Encuesta',
            'external' => 'Contenido externo',
        ],
        'time-policy' => [
            'work_time_required' => 'Solo durante la jornada laboral',
            'always_counts' => 'Cuenta siempre como jornada laboral',
            'approval_required' => 'Fuera de jornada solo con aprobación',
            'voluntary_unpaid' => 'Voluntario, no remunerado',
        ],
        'instruction-suitability' => [
            'supplementary' => 'Solo complementario',
            'with_questions' => 'Con posibilidad de preguntas',
            'with_presence' => 'Con parte presencial',
        ],
    ],
    'media' => [
        'state' => [
            'pending' => 'En espera',
            'processing' => 'En proceso',
            'ready' => 'Listo',
            'failed' => 'Con errores',
        ],
        'rendition-kind' => [
            'video' => 'Versión de vídeo',
            'poster' => 'Imagen de vista previa',
            'subtitle' => 'Subtítulos',
        ],
        'subtitle-source' => [
            'manual' => 'manual',
            'machine' => 'automática',
        ],
    ],
];
