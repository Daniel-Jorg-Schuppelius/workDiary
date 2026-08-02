<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : disposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Expediente de eliminación (feature 100, MVP-469/470): lista, expediente,
// diálogos y PDF del justificante para el cliente. Labels de enum y mensajes
// backend inline en el código.
return [
    'eyebrow' => 'Eliminación',

    'index' => [
        'title' => 'Expedientes de eliminación',
        'subtitle' => 'Recogida, lista de equipos, tratamiento de soportes de datos y justificantes del gestor de residuos — trazable hasta el justificante del cliente.',
        'empty' => 'No hay expedientes de eliminación — crear el primero mediante el diálogo.',
        'kpi' => [
            'open' => 'Expedientes abiertos',
            'hazardous_open' => 'Abiertos con residuos peligrosos',
            'completed_year' => 'Cerrados (año en curso)',
        ],
        'filter' => [
            'hazardous_only' => 'solo peligrosos',
        ],
        'col' => [
            'items' => 'Posiciones',
            'picked_up' => 'Recogida',
        ],
    ],

    'field' => [
        'site' => 'Lugar de intervención',
        'diary_entry' => 'Encargo',
        'picked_up_on' => 'Fecha de recogida',
        'total_weight' => 'Peso total (kg)',
        'created' => 'Creado',
        'cancelled_at' => 'Anulado el',
        'cancel_reason' => 'Motivo de anulación',
        'completed_at' => 'Cerrado el',
        'completed_by' => 'Cerrado por',
    ],

    'form' => [
        'title_create' => 'Nuevo expediente de eliminación',
        'title_edit' => 'Editar expediente de eliminación',
        'submit_create' => 'Crear expediente',
        'group_assignment' => 'Cliente e intervención',
        'group_pickup' => 'Recogida y detalles',
        'site' => 'Lugar de intervención (opcional)',
        'site_none' => 'sin lugar de intervención',
        'diary_entry' => 'Encargo/expediente (opcional)',
        'diary_entry_none' => 'sin referencia de encargo',
    ],

    'show' => [
        'nav' => 'Expediente de eliminación',
        'title' => 'Expediente de eliminación :number',
        'section' => [
            'job' => 'Expediente',
            'blockers' => 'Comprobación de cierre',
            'items' => 'Lista de equipos',
            'handovers' => 'Entregas al gestor de residuos',
            'signature' => 'Confirmación de recepción',
            'record' => 'Justificante del cliente',
        ],
    ],

    'badge' => [
        'hazardous' => 'peligroso',
        'signed' => 'Recepción firmada',
    ],

    'item' => [
        'title_create' => 'Registrar posición',
        'title_edit' => 'Editar posición',
        'group_device' => 'Equipo',
        'group_disposal' => 'Eliminación y soportes de datos',
        'weight' => 'Peso (kg)',
        'condition_note' => 'Nota sobre el estado',
        'avv_code' => 'Código de residuo (AVV/LER)',
        'avv_hint' => 'Asterisco * = residuo peligroso — la clasificación se deriva automáticamente.',
        'has_data_storage' => 'El equipo contiene soportes de datos',
        'note' => 'Nota',
        'empty' => 'Sin posiciones de equipo — añadir equipos mediante «Registrar posición».',
        'col' => [
            'device' => 'Fabricante/modelo',
            'weight' => 'Peso (kg)',
            'avv' => 'Código de residuo (AVV/LER)',
            'data_storage' => 'Soportes de datos',
        ],
        'treatments_count' => '1 tratamiento|:count tratamientos',
        'treatment_missing' => 'Falta tratamiento',
    ],

    'treatment' => [
        'title_create' => 'Registrar tratamiento de soporte de datos',
        'group_method' => 'Procedimiento y norma',
        'group_evidence' => 'Ejecución y justificante',
        'media_type' => 'Tipo de soporte de datos',
        'method' => 'Procedimiento',
        'din_category' => 'Categoría de material DIN 66399',
        'security_level' => 'Nivel de seguridad (1–7)',
        'protection_class' => 'Clase de protección',
        'protection_class_none' => 'sin indicación',
        'protection_class_short' => 'Clase de protección :class',
        'treated_at' => 'Fecha/hora',
        'performed_by' => 'Ejecutante',
        'evidence_reference' => 'Referencia de justificante/certificado',
        'please_select' => '-- seleccionar --',
    ],

    'handover' => [
        'title_create' => 'Registrar entrega al gestor de residuos',
        'group_proof' => 'Gestor de residuos y justificante',
        'group_attachment' => 'Documento y nota',
        'disposer' => 'Gestor de residuos',
        'proof_type' => 'Tipo de justificante',
        'document_number' => 'Número de documento',
        'handed_over_on' => 'Fecha de entrega',
        'certificate_reference' => 'Referencia del certificado EfbV',
        'proof_file' => 'Archivo justificante (opcional)',
        'proof_file_hint' => 'PDF, JPG o PNG — máximo 10 MB. El justificante se archiva como documento DMS.',
        'note' => 'Nota',
        'no_disposers' => 'No hay ninguna empresa gestora de residuos certificada registrada.',
        'create_disposer' => 'Crear el gestor de residuos como contacto externo',
        'empty' => 'Todavía no se ha registrado ninguna entrega a un gestor de residuos.',
        'col' => [
            'disposer' => 'Gestor de residuos',
            'proof_type' => 'Tipo de justificante',
            'document_number' => 'Número de documento',
            'certificate' => 'Referencia EfbV',
            'document' => 'Documento DMS',
        ],
    ],

    'sign' => [
        'signer_name' => 'Nombre de la persona que recibe',
        'signed_at' => 'Firmado el',
        'hash' => 'Suma de verificación',
        'hint' => 'Con «Confirmar recepción» la firma se guarda de forma verificable.',
        'missing' => 'No hay firma de recepción.',
    ],

    'record' => [
        'released_hint' => 'El justificante del cliente está publicado en el portal del cliente.',
        'pending_hint' => 'El justificante del cliente se genera automáticamente al cerrar el expediente.',
    ],

    'cancel' => [
        'title' => 'Anular expediente de eliminación',
        'intro' => 'La anulación es definitiva y se registra con motivo en la cadena de trazabilidad.',
        'reason' => 'Motivo',
    ],

    'action' => [
        'create' => 'Nuevo expediente de eliminación',
        'collect' => 'Registrar recogida',
        'start_treatment' => 'Iniciar tratamiento',
        'hand_over' => 'Entregar al gestor de residuos',
        'pdf_preview' => 'PDF del justificante (vista previa)',
        'add_item' => 'Registrar posición',
        'add_treatment' => 'Registrar tratamiento',
        'add_handover' => 'Registrar entrega',
        'sign' => 'Confirmar recepción',
    ],

    'confirm' => [
        'complete' => '¿Cerrar el expediente? El justificante del cliente se genera y publica, y los activos vinculados se dan de baja.',
        'delete_item' => '¿Eliminar realmente esta posición de equipo?',
        'delete_treatment' => '¿Eliminar realmente este tratamiento de soporte de datos?',
        'delete_handover' => '¿Eliminar realmente esta entrega al gestor de residuos?',
    ],

    'pdf' => [
        'title' => 'Justificante de recepción y eliminación',
        'number' => 'Número de expediente',
        'customer' => 'Cliente',
        'picked_up_on' => 'Fecha de recogida',
        'responsible' => 'Responsable',
        'status' => 'Estado',
        'total_weight' => 'Peso total',
        'items' => 'Lista de equipos',
        'treatments' => 'Justificante de protección de datos y soportes (DIN 66399)',
        'handovers' => 'Justificante de eliminación y destino',
        'confirmation' => 'Confirmación',
        'customer_signature' => 'Recepción por parte del cliente',
        'not_signed' => 'Sin firmar.',
        'provider' => 'Proveedor de servicios',
        'completed_at' => 'Cerrado el',
        'hazardous_suffix' => '(peligroso)',
        'col' => [
            'category' => 'Categoría',
            'device' => 'Fabricante/modelo',
            'serial' => 'Número de serie',
            'quantity' => 'Cantidad',
            'weight' => 'Peso (kg)',
            'avv' => 'Código de residuo (AVV/LER)',
            'media_type' => 'Tipo de soporte',
            'method' => 'Procedimiento',
            'din' => 'DIN 66399',
            'protection_class' => 'Clase de protección',
            'treated_at' => 'Fecha/hora',
            'performed_by' => 'Ejecutante',
            'evidence' => 'N.º de justificante/certificado',
            'disposer' => 'Gestor de residuos',
            'proof_type' => 'Tipo de justificante',
            'document_number' => 'Número de documento',
            'handed_over_on' => 'Fecha',
            'certificate' => 'Certificado EfbV',
        ],
        'footer' => [
            'hash' => 'Suma de verificación',
            'generated' => 'Generado el :at',
        ],
    ],
];
