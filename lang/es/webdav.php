<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : webdav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Almacenamiento WebDAV',
    'intro' => 'Los documentos aprobados se copian por tipo de documento en un almacenamiento WebDAV externo (Nextcloud/ownCloud) — con comprobante de transferencia (hash, hora, destino). WorkDiary sigue siendo la referencia; los cambios externos en los archivos copiados aparecen como conflicto, nunca como sobrescritura silenciosa.',

    'conflict' => [
        'subtitle' => 'Cambio externo detectado — copia en pausa (sin sobrescritura).',
        'action' => [
            'overwrite' => 'Sobrescribir remoto',
            'import' => 'Importar como nueva versión',
            'detach' => 'Desvincular copia',
        ],
        'confirm' => [
            'overwrite' => '¿Sobrescribir el archivo externo con el estado local? El cambio externo se perderá.',
            'import' => '¿Importar el estado externo como nueva versión local?',
            'detach' => '¿Desvincular permanentemente la copia de este documento? La conexión permanece activa.',
        ],
        'flash' => [
            'overwritten' => 'Archivo externo sobrescrito con el estado local.',
            'imported' => 'Estado externo importado como nueva versión local.',
            'detached' => 'Copia desvinculada para este documento.',
            'failed' => 'Error al resolver el conflicto: :reason',
        ],
        'import_note' => 'Importado desde WebDAV (resolución de conflicto).',
    ],

    'health' => [
        'ok' => 'Conectado',
        'failing' => 'Inaccesible',
        'inactive' => 'Inactivo',
    ],

    'action' => [
        'mirror' => 'Copiar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'connection' => [
        'heading' => 'Almacenamiento',
    ],

    'field' => [
        'name' => 'Etiqueta',
        'base_url' => 'URL de la colección',
        'base_url_help' => 'Carpeta WebDAV completa, p. ej. .../remote.php/dav/files/USUARIO/WorkDiary.',
        'username' => 'Nombre de usuario',
        'app_password' => 'Contraseña de aplicación',
        'password_keep' => '•••••••• (dejar sin cambios)',
        'password_help' => 'Nextcloud: Ajustes → Seguridad → Contraseña de aplicación. Se almacena cifrada.',
        'default_folder' => 'Carpeta predeterminada',
        'active' => 'Activo',
        'sources' => 'Contenido reflejado',
        'source_document' => 'Documentos (DMS)',
        'source_invoice_pdf' => 'Facturas (PDF)',
        'source_protocol_pdf' => 'Actas (PDF)',
        'sources_help' => 'Qué contenido se refleja en este almacén. Sin selección, solo documentos publicados.',
    ],

    'folder' => [
        'heading' => 'Tipo de documento → carpeta',
        'help' => 'Asigna los tipos de documento a una subcarpeta (relativa a la URL de la colección). Sin coincidencia se aplica la carpeta predeterminada.',
        'type_placeholder' => '— tipo de documento —',
        'path_placeholder' => 'Subcarpeta',
    ],

    'flash' => [
        'saved' => 'Almacenamiento WebDAV guardado.',
        'mirror_done' => 'Copia iniciada.',
        'disconnected' => 'Almacenamiento WebDAV desconectado. Los archivos ya copiados se conservan externamente.',
        'no_connection' => 'No hay ningún almacenamiento WebDAV activo.',
        'invalid_url' => 'La URL de la colección debe empezar por http:// o https://.',
        'password_required' => 'Un almacenamiento nuevo requiere una contraseña de aplicación.',
    ],
];
