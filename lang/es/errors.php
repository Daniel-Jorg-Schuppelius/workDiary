<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : errors.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'csv' => [
        'unreadable' => 'El archivo no es legible.',
        'header_missing' => 'Fila de encabezado ausente o ilegible: :error',
        'name_column_missing' => 'Columna obligatoria «Name» no encontrada.',
    ],
    'routing' => [
        'nominatim_missing_coords' => 'La respuesta de Nominatim no contiene coordenadas.',
        'nominatim_http' => 'Nominatim devolvió HTTP :status.',
    ],
    'upload' => [
        'too_large' => 'El archivo es demasiado grande (máx. :max KB).',
        'type_not_allowed' => 'Tipo de archivo no permitido.',
    ],

    // Páginas de error HTTP (041-P0, MVP-053)
    'request_id' => 'ID de la solicitud',
    'report_problem' => 'Informar de un problema',
    '404' => [
        'title' => 'Página no encontrada',
        'message' => 'La página solicitada no existe o se ha movido.',
    ],
    '403' => [
        'title' => 'Acceso denegado',
        'message' => 'No tiene permiso para esta acción. Póngase en contacto con su administración.',
    ],
    '419' => [
        'title' => 'Sesión caducada',
        'message' => 'La página estuvo abierta demasiado tiempo. Recárguela e inténtelo de nuevo.',
    ],
    '500' => [
        'title' => 'Error interno',
        'message' => 'Se produjo un error inesperado. Inténtelo de nuevo más tarde o informe del problema con el ID de la solicitud.',
    ],
];
