<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : document.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Documentos',
        'versions' => 'Versiones',
        'version_history' => 'Historial de versiones',
    ],

    'subtitle' => 'Gestionar contratos, certificados, informes de prueba y otros documentos.',

    'field' => [
        'title' => 'Título',
        'type' => 'Tipo',
        'status' => 'Estado',
        'reference' => 'Referencia',
        'validity' => 'Validez',
        'valid_from' => 'Válido desde',
        'valid_until' => 'Válido hasta',
        'description' => 'Descripción',
        'file' => 'Archivo',
        'version' => 'Versión',
        'version_note' => 'Nota de versión',
        'creator' => 'Creado por',
    ],

    'action' => [
        'create' => 'Añadir documento',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'archive' => 'Archivar',
        'download' => 'Descargar',
        'add_version' => 'Subir nueva versión',
    ],

    'filter' => [
        'all' => 'Todos',
        'search' => 'Búsqueda',
        'search_placeholder' => 'Buscar en títulos',
        'expiring' => 'Vence',
        'expiring_days' => 'en :days días',
    ],

    'ref' => [
        'customer' => 'Cliente',
        'project' => 'Proyecto',
        'diary' => 'Encargo',
        'asset' => 'Activo',
        'none' => 'Sin referencia',
    ],

    'badge' => [
        'current' => 'Actual',
        'expired' => 'Vencido',
        'expires_soon' => 'Vence pronto',
    ],

    'flash' => [
        'created' => 'El documento se ha creado.',
        'updated' => 'El documento se ha actualizado.',
        'deleted' => 'El documento se ha eliminado.',
        'archived' => 'El documento se ha archivado.',
        'version_added' => 'La versión :no se ha subido.',
    ],

    'error' => [
        'unknown_type' => 'Tipo de documento desconocido.',
        'valid_until_before_from' => 'El fin de la validez debe ser posterior a su inicio.',
    ],

    'hint' => [
        'upload' => 'Permitido: PDF, imágenes, archivos de Office, texto/CSV, ZIP — máx. :mb MB.',
    ],

    // Liberación para el portal de clientes (fase D — reflejo de documentos).
    'customer' => [
        'section' => 'Liberación al cliente',
        'released' => 'Liberado al portal de clientes',
        'not_released' => 'No liberado',
        'released_at' => 'Liberado el',
        'released_by' => 'Liberado por',
        'badge' => 'Portal',
        'not_linked_hint' => 'Solo se pueden liberar documentos vinculados a un cliente o a un trabajo.',
        'action' => [
            'release' => 'Liberar al portal de clientes',
            'revoke' => 'Retirar la liberación',
        ],
        'confirm_revoke' => '¿Retirar realmente la liberación al portal de clientes?',
        'flash' => [
            'released' => 'El documento se ha liberado al portal de clientes.',
            'revoked' => 'Se ha retirado la liberación al portal de clientes.',
        ],
        'error' => [
            'not_linked' => 'Solo se pueden liberar documentos vinculados a un cliente o a un trabajo.',
        ],
        'portal' => [
            'title' => 'Documentos',
            'subtitle' => 'Los documentos liberados para usted.',
            'empty' => 'Todavía no se ha liberado ningún documento para usted.',
        ],
    ],

    'empty' => 'Aún no hay documentos.',
    'empty_title' => 'No se encontraron documentos',
    'empty_filtered' => 'Ningún documento coincide con los filtros actuales.',
    'empty_versions' => 'Aún no hay versiones.',
    'confirm_delete' => '¿Eliminar realmente este documento con todas sus versiones?',
    'confirm_archive' => '¿Archivar realmente este documento?',
];
