<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : lead_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Expediente de admisión con paquetes de verificación (Feature 200, MVP-1641–1644, MVP-1675).
return [
    'title' => 'Expediente de admisión',
    'title_for' => 'Expediente de admisión: :name',
    'index_title' => 'Admisiones',
    'index_subtitle' => 'Expedientes de admisión de los leads, desde la consulta hasta el alta del cliente.',
    'back_to_lead' => 'Al lead',
    'revision' => 'Versión :revision',
    'superseded' => 'Sustituida',
    'stages' => 'Fases',
    'current_stage' => 'Fase actual',

    'action' => [
        'open' => 'Abrir admisión',
        'decide' => 'Decidir',
        'refresh' => 'Actualizar',
        'pdf' => 'PDF',
        'archive' => 'Archivar como documento',
        'move_to' => 'Siguiente: :stage',
        'save' => 'Guardar',
        'confirm_second_review' => 'Confirmar aceptación',
        'return_second_review' => 'Devolver',
    ],
    'confirm' => [
        'refresh' => '¿Crear una nueva versión del expediente? Los datos se trasladan y pueden revisarse y completarse.',
        'archive' => '¿Archivar el PDF sin datos de identidad ni de salud como documento?',
        'second_review' => '¿Confirmar la aceptación como segunda persona?',
    ],
    'hint' => [
        'restricted' => ':count datos de este expediente requieren un permiso propio y están ocultos.',
        'restricted_answered' => ':kind: :count rellenados.',
        'no_packages' => 'No hay paquetes de verificación activados. Los expedientes solo contienen el formulario de entrevista; los leads se convierten directamente como hasta ahora.',
    ],

    'form' => [
        'section_base' => 'Consulta y entrevista',
        'request' => 'Solicitud',
        'services' => 'Servicios deseados',
        'services_help' => 'Al convertir se convierte en el alcance de servicios del nuevo cliente.',
        'start_on' => 'Inicio deseado',
        'interview_notes' => 'Notas de la entrevista',
        'section_traits' => 'Características de plazos: :rule_set',
        'traits_help' => 'Al convertir pasan a ser características de plazos del nuevo cliente.',
        'section_documents' => 'Documentos: :package',
        'documents_help' => 'Marque en cuanto el documento esté disponible; archive el fichero abajo, en documentos.',
    ],

    'field' => [
        'stage' => 'Fase',
        'decision' => 'Decisión',
        'decision_reason' => 'Motivo',
        'decided_at' => 'Decidido el',
        'decided_by' => 'Decidido por',
        'second_review' => 'Segunda revisión',
        'protocol' => 'Nota',
        'packages' => 'Paquetes de verificación',
        'refresh_due_on' => 'Actualización pendiente',
        'retention_until' => 'Conservar hasta',
        'customer' => 'Cliente',
        'relationship_ended_on' => 'Fin de la relación comercial',
    ],

    'documents' => [
        'title' => 'Documentos',
        'upload' => 'Archivar',
        'upload_for' => 'Fichero para «:document»',
    ],

    'decision' => [
        'title' => 'Decisión',
        'legend' => 'Aceptar o rechazar la admisión',
        'open' => 'Aún sin decidir.',
        'missing' => 'Falta antes de la aceptación: :count',
        'blocks_conversion' => 'Hay un paquete de verificación con obligación de decisión activo: el lead solo se convierte en cliente tras la aceptación.',
        'reason_hint' => 'El motivo queda en el expediente y en el PDF.',
        'second_review_pending' => 'Aceptado — falta la segunda revisión de otra persona.',
        'return_reason' => 'Motivo de la devolución',
    ],

    'packages' => [
        'title' => 'Paquetes de verificación y plazos',
        'none' => 'Ninguno',
    ],
    'versions' => [
        'title' => 'Versiones',
    ],
    'retention' => [
        'open' => 'corre desde el fin de la relación comercial',
    ],

    'settings' => [
        'title' => 'Ajustes de las admisiones',
        'submit' => 'Guardar',
        'packages' => 'Paquetes de verificación',
        'none_available' => 'No hay ningún paquete de verificación disponible.',
        'packages_hint' => 'Los paquetes activados se aplican a los expedientes nuevos. Un paquete con obligación de decisión bloquea la conversión sin aceptación.',
        'refresh' => 'Actualización por nivel de riesgo GwG (meses)',
        'refresh_for' => 'Nivel de riesgo :level',
        'refresh_hint' => 'Por defecto: anual. El plazo comienza con la aceptación.',
        'second_review' => 'Segunda revisión',
        'second_review_label' => 'Hacer confirmar la aceptación por una segunda persona',
        'second_review_hint' => 'Por defecto: desactivado. La segunda persona necesita el permiso de decisión y no puede haber aceptado ella misma.',
    ],

    'relationship' => [
        'title' => 'Fin de la relación comercial',
        'submit' => 'Guardar',
        'legend' => 'Relación comercial',
        'hint' => 'Desde el cierre de este año natural corre la conservación de los expedientes de admisión (GwG: cinco años, § 8 apdo. 4 GwG). Déjelo vacío mientras continúe.',
        'ongoing' => 'vigente',
    ],

    'cockpit' => [
        'none' => 'Aún no hay expediente de admisión. Paquetes activos: :packages',
        'refresh_due' => 'Actualización :date',
        'retention' => 'conservar hasta :date',
    ],

    'filter' => [
        'status' => 'Selección',
        'status_open' => 'Admisiones abiertas',
        'status_refresh' => 'Actualización pendiente',
        'status_all' => 'Todas',
        'stage' => 'Fase',
        'all_stages' => 'Todas las fases',
    ],
    'empty' => [
        'title' => 'No hay admisiones',
        'message' => 'Los expedientes de admisión se abren en el lead.',
    ],
    'column' => [
        'lead' => 'Lead',
        'stage' => 'Fase',
        'packages' => 'Paquetes de verificación',
        'decision' => 'Decisión',
        'refresh_due_on' => 'Actualización',
        'updated_at' => 'Modificado',
    ],

    'widget' => [
        'title' => 'Admisiones',
        'description' => 'Expedientes de admisión abiertos por fase, desde la consulta hasta el alta.',
        'open_list' => 'Todas las admisiones',
        'empty' => 'Ahora mismo no hay ninguna admisión abierta.',
    ],

    'flash' => [
        'opened' => 'Expediente de admisión abierto.',
        'saved' => 'Formulario guardado.',
        'stage' => 'Fase: :stage',
        'decided' => 'Decidido: :decision',
        'second_review_confirmed' => 'Aceptación confirmada.',
        'second_review_returned' => 'Aceptación devuelta — el formulario vuelve a estar abierto.',
        'document_added' => 'Documento archivado.',
        'refreshed' => 'Versión :revision creada.',
        'archived' => 'Archivado como documento: :file',
        'settings_saved' => 'Ajustes de las admisiones guardados.',
        'relationship_saved' => 'Guardado; conservación de :count expedientes recalculada.',
    ],

    'error' => [
        'anonymized' => 'El lead está anonimizado.',
        'stage_by_decision' => 'El alta, «dado de alta» y «rechazado» resultan de la decisión o de la conversión.',
        'closed' => 'El expediente está decidido o sustituido por una versión más reciente.',
        'reason_required' => 'Indique el motivo de la decisión.',
        'no_second_review' => 'Esta aceptación no espera una segunda revisión.',
        'same_person' => 'La segunda revisión la realiza una persona distinta de la que aceptó.',
        'refresh_needs_acceptance' => 'Solo se actualiza la versión aceptada más reciente.',
        'unknown_document' => 'Este documento no pertenece al expediente.',
        'restricted' => 'Falta el permiso propio para este documento.',
        'no_carrier' => 'No se ha encontrado cliente ni lead para archivar.',
        'restricted_missing' => 'Falta un dato con permiso propio (:right).',
    ],

    'block' => [
        'no_intake' => 'Hay un paquete de verificación con obligación de decisión activo: abra primero el expediente de admisión y acepte la admisión.',
        'not_accepted' => 'La admisión aún no está aceptada — solo después el lead pasa a ser cliente.',
        'second_review' => 'La aceptación espera la segunda revisión.',
        'rejected' => 'La admisión fue rechazada; el lead no se convierte.',
    ],

    'transfer' => [
        'service_note' => 'Tomado del expediente de admisión.',
        'traits_incomplete' => 'Las características de plazos «:rule_set» del expediente están incompletas y no se han trasladado — complételas en el cliente.',
    ],

    'notification' => [
        'refresh' => [
            'title' => ':count expediente(s) de admisión por actualizar',
            'message' => 'Pendiente desde :date: :names',
        ],
    ],

    'work' => [
        'decide' => 'Decidir admisión: :name',
        'second_review' => 'Segunda revisión: :name',
        'refresh' => 'Actualizar admisión: :name',
    ],

    'pdf' => [
        'file_prefix' => 'admision',
        'customer' => 'Cliente',
        'restricted' => 'restringido (:kind)',
    ],

    'vat_id' => [
        'invalid' => 'El NIF-IVA es formalmente inválido (estructura o dígito de control).',
    ],

    'dsar' => [
        'title' => 'Expedientes de admisión',
        'list_title' => 'Expediente de admisión, versión :revision',
        'column' => [
            'topic' => 'Dato',
            'value' => 'Contenido',
        ],
        'legal_basis' => 'Base jurídica de la conservación',
        'legal_basis_value' => 'Art. 6, apdo. 1, letra c RGPD en relación con :basis',
        'restricted' => 'existente — salida solo con el permiso «:right»',
    ],

    'demo' => [
        'request' => 'Contabilidad continua, nóminas de tres empleados y cuentas anuales a partir del mes que viene.',
        'notes' => 'Primera reunión en el despacho; los documentos del asesor anterior llegarán por subida.',
        'purpose' => 'Asesoría fiscal: contabilidad, nóminas y cuentas anuales de la sociedad.',
    ],

    'gwg' => [
        'label' => 'Ley alemana contra el blanqueo de capitales (GwG)',
        'protocol_title' => 'Identificación y nota de riesgo: :name',
        'protocol_description' => 'Del expediente de admisión (versión :revision). Nivel de riesgo: :risk. Motivo: :reason',
        'document' => [
            'id_copy' => 'Copia del documento de identidad o del extracto del registro (§ 8 apdo. 2 GwG)',
        ],
        'section' => [
            'identification' => 'GwG: Identificación de la parte contratante (§ 11 GwG)',
            'verification' => 'GwG: Comprobación de la identidad (§ 12 GwG)',
            'beneficial_owners' => 'GwG: Titulares reales (§ 3 GwG)',
            'pep' => 'GwG: Persona con responsabilidad pública (§ 15 apdo. 3 n.º 1 GwG)',
            'purpose' => 'GwG: Propósito y naturaleza de la relación comercial (§ 10 apdo. 1 n.º 3 GwG)',
            'risk' => 'GwG: Evaluación del riesgo (§§ 14, 15 GwG)',
        ],
        'field' => [
            'party_kind' => 'La parte contratante es',
            'first_name' => 'Nombre',
            'last_name' => 'Apellidos',
            'birth_place' => 'Lugar de nacimiento',
            'birth_date' => 'Fecha de nacimiento',
            'nationality' => 'Nacionalidad',
            'home_address' => 'Domicilio',
            'company_name' => 'Razón social, nombre o denominación',
            'legal_form' => 'Forma jurídica',
            'register_number' => 'Número de registro',
            'seat_address' => 'Dirección del domicilio social o sede principal',
            'representatives' => 'Miembros del órgano de representación o representantes legales',
            'verification_method' => 'Comprobado mediante',
            'id_document_kind' => 'Tipo de documento',
            'id_document_number' => 'Número del documento',
            'id_issuer' => 'Autoridad expedidora',
            'verified_on' => 'Comprobado el',
            'beneficial_owners' => 'Titulares reales',
            'transparency_register_checked' => 'Consulta del registro de transparencia realizada',
            'transparency_register_on' => 'Registro de transparencia: consultado el',
            'transparency_register_reference' => 'Registro de transparencia: extracto o referencia',
            'pep_status' => 'Persona con responsabilidad pública',
            'pep_details' => 'Datos sobre el cargo o la relación',
            'purpose' => 'Propósito y naturaleza prevista de la relación comercial',
            'suspicion_checked' => 'Indicios de blanqueo de capitales o financiación del terrorismo comprobados',
            'lower_risk_factors' => 'Factores de menor riesgo (anexo 1 GwG)',
            'higher_risk_factors' => 'Factores de mayor riesgo (anexo 2 GwG)',
            'risk_level' => 'Nivel de riesgo',
            'risk_reason' => 'Motivo del nivel de riesgo',
        ],
        'help' => [
            'party_kind' => 'Persona física: § 11 apdo. 4 n.º 1 GwG; persona jurídica o sociedad de personas: n.º 2.',
            'verification_method' => 'Con documento de identidad o extracto del registro: anotar tipo, número y autoridad expedidora y archivar una copia (§ 8 apdo. 2 GwG).',
            'id_document_number' => 'Se guarda cifrado y solo es visible con el permiso para datos de identidad.',
            'beneficial_owners' => 'Nombre, tipo y alcance de la participación o del control; en una persona física que actúa por cuenta propia, ella misma.',
            'transparency_register_checked' => 'Prueba del registro o extracto del registro de transparencia (§ 11 apdo. 5, § 12 apdo. 3 GwG).',
            'pep_status' => 'A una persona con responsabilidad pública se le aplican medidas reforzadas — el nivel de riesgo es entonces alto.',
            'suspicion_checked' => 'El expediente solo deja constancia de la comprobación; la aplicación no envía comunicaciones a la FIU.',
            'lower_risk_factors' => 'Factores no exhaustivos según el anexo 1 GwG.',
            'higher_risk_factors' => 'Factores no exhaustivos según el anexo 2 GwG.',
            'risk_level' => 'Bajo: diligencia simplificada (§ 14), normal: general (§ 10), alto: reforzada (§ 15). Determina el plazo de actualización.',
            'risk_reason' => 'Obligatorio con nivel bajo y alto (§ 8 apdo. 1 frase 1 n.º 2 GwG).',
        ],
        'option' => [
            'party_kind' => [
                'natural' => 'Persona física',
                'legal' => 'Persona jurídica o sociedad de personas',
            ],
            'verification_method' => [
                'id_document' => 'Documento oficial con fotografía (DNI, pasaporte)',
                'eid' => 'Identificación electrónica (eID)',
                'qes' => 'Firma electrónica cualificada',
                'register_extract' => 'Extracto del registro mercantil o de cooperativas',
                'formation_documents' => 'Documentos de constitución',
                'own_register_check' => 'Consulta propia documentada del registro',
            ],
            'pep_status' => [
                'no' => 'No',
                'pep' => 'Sí, con responsabilidad pública',
                'family' => 'Familiar de una persona con responsabilidad pública',
                'associate' => 'Allegado conocido',
            ],
            'lower_risk_factors' => [
                'listed_company' => 'Sociedad cotizada con obligaciones de información',
                'public_body' => 'Administración o empresa pública',
                'low_risk_area' => 'Sede o domicilio en una zona de menor riesgo',
                'low_risk_product' => 'Servicio de bajo riesgo',
            ],
            'higher_risk_factors' => [
                'unusual_circumstances' => 'Circunstancias inusuales de la relación comercial',
                'high_risk_area' => 'Sede o domicilio en una zona de alto riesgo',
                'asset_holding_vehicle' => 'Sociedad de gestión de patrimonio privado',
                'nominee_shareholders' => 'Accionistas fiduciarios o acciones al portador',
                'cash_intensive' => 'Empresa con uso intensivo de efectivo',
                'complex_structure' => 'Estructura de propiedad inusualmente compleja',
                'no_personal_contact' => 'Sin contacto personal ni medidas de seguridad',
                'unknown_third_party_payments' => 'Pagos de terceros desconocidos o no vinculados',
                'high_risk_goods' => 'Comercio de petróleo, armas, metales preciosos, tabaco, bienes culturales o especies protegidas',
            ],
        ],
        'error' => [
            'pep_requires_high' => 'A una persona con responsabilidad pública se le aplican medidas reforzadas: elija el nivel de riesgo «alto» (§ 15 apdo. 3 n.º 1 GwG).',
            'copy_missing' => 'Falta la copia del documento (§ 8 apdo. 2 GwG).',
        ],
    ],
];
