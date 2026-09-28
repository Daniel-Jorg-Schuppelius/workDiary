<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : crisis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Offline-Krisenmappe (MVP-914).
    'offline' => [
        'save' => 'Guardar la carpeta de crisis sin conexión',
        'hint' => 'Guarda en este dispositivo las crisis activas con la situación, las medidas y los contactos del comité de crisis; se puede leer sin conexión en la página sin conexión. Se borra al cerrar sesión.',
    ],
    // Öffentliche Statusseite (MVP-915).
    'status_page' => [
        'title' => 'Página de estado (pública)',
        'intro' => 'La página de estado muestra sin iniciar sesión los mensajes de crisis enviados al público; los mensajes a clientes aparecen además en el portal de clientes. Solo se muestran asunto, texto y hora, nunca el expediente de crisis.',
        'token_once' => 'Este enlace se muestra solo ahora — no se guarda en ningún sitio.',
        'state' => 'Estado',
        'state_none' => 'Sin configurar',
        'state_active' => 'Accesible públicamente',
        'state_paused' => 'En pausa',
        'hint' => 'Identificador',
        'issued_at' => 'Emitido el',
        'action' => [
            'issue' => 'Emitir enlace',
            'rotate' => 'Renovar enlace',
            'revoke' => 'Revocar acceso',
            'pause' => 'Pausar',
            'resume' => 'Activar',
        ],
        'confirm' => [
            'rotate' => '¿Emitir un enlace nuevo? El anterior dejará de funcionar.',
            'revoke' => '¿Revocar el acceso? La página de estado dejará de ser accesible públicamente.',
        ],
        'flash' => [
            'issued' => 'Nuevo enlace emitido.',
            'revoked' => 'Acceso revocado.',
            'saved' => 'Guardado.',
        ],
        'public_title' => 'Situación actual – :org',
        'public_intro' => 'Aquí le informamos sobre incidencias e interrupciones en curso.',
        'all_clear' => 'Actualmente no hay incidencias.',
        'resolved' => 'fin de la alerta',
    ],
    // BIA-Register (MVP-943).
    'bia' => [
        'title' => 'Registro BIA',
        'subtitle' => 'Procesos de negocio con criticidad, objetivos de recuperación (RTO/RPO) y tiempo máximo tolerable de interrupción (MTPD).',
        'create' => 'Añadir proceso',
        'edit' => 'Editar proceso',
        'save' => 'Guardar',
        'empty' => 'Aún no hay procesos en el registro.',
        'inactive' => 'inactivo',
        'import' => 'Importar de registros',
        'import_hint' => 'Sugerencias del registro de actividades de tratamiento, riesgos SGSI y plantillas de procedimiento activas. Solo se importa lo que seleccione.',
        'import_submit' => 'Importar selección',
        'adopt' => 'Adoptar del registro BIA',
        'kind' => [
            'processing_activity' => 'Actividad de tratamiento',
            'isms_risk' => 'Riesgo SGSI',
            'procedure_template' => 'Plantilla de procedimiento',
        ],
        'criticality' => [
            'low' => 'baja',
            'medium' => 'media',
            'high' => 'alta',
            'critical' => 'crítica',
        ],
        'field' => [
            'name' => 'Proceso',
            'description' => 'Descripción',
            'criticality' => 'Criticidad',
            'rto_hours' => 'RTO (horas)',
            'rpo_hours' => 'RPO (horas)',
            'mtpd_hours' => 'MTPD (horas)',
            'owner' => 'Responsable',
            'dependencies' => 'Dependencias (sistemas, proveedores, personas)',
            'review_due_on' => 'Revisión prevista',
            'is_active' => 'Activo',
        ],
        'flash' => [
            'saved' => 'Proceso guardado.',
            'imported' => 'Se importaron :count procesos.',
            'adopted' => 'Proceso adoptado del registro BIA.',
        ],
    ],
    // BCM-Auswertung (MVP-944).
    'bcm_report' => [
        'title' => 'Informe BCM',
        'subtitle' => 'Indicadores según ISO 22301: ejercicios, acciones, revisiones y estado BIA.',
        'back' => 'Gestión de crisis',
        'disclaimer' => 'Indicadores a partir de los datos registrados; no constituye una declaración de certificabilidad.',
        'overdue' => ':count vencidas',
        'row' => [
            'exercises' => 'Ejercicios desde :date',
            'effectiveness' => 'Eficacia',
            'exercises_due' => 'Ejercicios pendientes',
            'actions_open' => 'Acciones abiertas',
            'reviews' => 'Crisis finalizadas con revisión',
            'processes' => 'Procesos en el registro BIA',
            'without_rto' => 'Procesos sin RTO',
            'review_due' => 'Procesos con revisión pendiente',
        ],
        'effectiveness' => [
            'effective' => 'eficaz',
            'partly' => 'parcialmente',
            'ineffective' => 'ineficaz',
            'open' => 'sin evaluar',
        ],
    ],
    // Krisenraum (MVP-963).
    'room' => [
        'title' => 'Sala de crisis',
        'present' => 'Presentes',
        'nobody' => 'nadie más',
        'no_markers' => 'Sin ubicaciones: vincule activos o clientes con coordenadas o añada puntos.',
        'add_point' => 'Añadir punto',
        'field' => [
            'label' => 'Denominación',
            'kind' => 'Tipo',
            'lat' => 'Latitud',
            'lng' => 'Longitud',
        ],
        'kind' => [
            'incident' => 'Lugar del incidente',
            'assembly' => 'Punto de encuentro',
            'closure' => 'Cierre',
            'resource' => 'Recurso',
            'other' => 'Otro',
        ],
        'layer' => [
            'linked' => 'Objetos vinculados',
        ],
        'link' => [
            'asset' => 'Activo',
            'customer' => 'Cliente',
        ],
        'flash' => [
            'point_saved' => 'Punto añadido.',
            'point_deleted' => 'Punto eliminado.',
        ],
    ],
];
