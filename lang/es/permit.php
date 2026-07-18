<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : permit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Autorizaciones',
    'subtitle' => 'Autorizaciones oficiales para eventos: estado, plazos y justificantes.',
    'label' => 'Autorización',
    'create' => 'Añadir autorización',
    'edit' => 'Editar autorización',
    'delete_confirm' => '¿Eliminar realmente esta autorización?',

    'sections' => [
        'base' => 'Datos',
        'dates' => 'Plazos',
    ],

    'fields' => [
        'title' => 'Denominación',
        'status' => 'Estado',
        'event' => 'Evento',
        'event_none' => '— ninguno —',
        'permit_type' => 'Tipo de autorización',
        'authority' => 'Autoridad',
        'reference_no' => 'Número de expediente',
        'applied_at' => 'Solicitada el',
        'valid_from' => 'Válida desde',
        'valid_until' => 'Válida hasta / plazo',
        'notes' => 'Notas',
        'evidence' => 'Documento justificativo',
    ],

    'filter' => [
        'all_status' => 'Todos los estados',
    ],

    'status' => [
        'required' => 'Requerida',
        'applied' => 'Solicitada',
        'granted' => 'Concedida',
        'rejected' => 'Rechazada',
        'expired' => 'Caducada',
    ],

    'messages' => [
        'created' => 'Autorización creada.',
        'updated' => 'Autorización actualizada.',
        'deleted' => 'Autorización eliminada.',
    ],

    'evidence' => [
        'upload' => 'Subir documento',
        'replace' => 'Reemplazar documento',
        'replace_hint' => 'Una nueva subida reemplaza el documento existente.',
        'hint' => 'Permitido: PDF, JPG, PNG, DOCX (máx. :mb MB).',
        'remove' => 'Eliminar documento',
        'remove_confirm' => '¿Eliminar realmente el documento justificativo?',
        'too_large' => 'El archivo es demasiado grande (máx. :mb MB).',
        'invalid_type' => 'Tipo de archivo no permitido (PDF, JPG, PNG, DOCX).',
    ],
];
