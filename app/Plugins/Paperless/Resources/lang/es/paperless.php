<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : paperless.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura documentos de una instancia autoalojada de Paperless-ngx a la entrada de documentos en la nube: todos los documentos o los de una etiqueta, un corresponsal o un tipo de documento. Las reglas de la entrada los dirigen a las facturas recibidas o al archivo de documentos.',
    ],
    'settings' => [
        'base_url' => 'URL base',
        'base_url_help' => 'Dirección de la instancia de Paperless-ngx, p. ej. https://paperless.example.com (sin /api).',
        'api_token' => 'Token de API',
        'api_token_help' => 'Créelo en Paperless-ngx en «Mi perfil». El token lee con los permisos de su usuario; WorkDiary no cambia nada en Paperless. Se necesita permiso de lectura sobre documentos, etiquetas, interlocutores, tipos de documento y «UISettings»; solo se ven los documentos que el usuario puede ver.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una URL base con una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
    'action' => [
        'connect' => 'Conectar Paperless-ngx',
    ],
    'connect' => [
        'title' => 'Conectar Paperless-ngx',
        'legend' => 'Conexión',
        'instance' => 'Instancia de la configuración del plugin: :instance',
        'name' => 'Nombre de la conexión',
        'hint' => 'Después elija el origen (todos los documentos, etiqueta, corresponsal o tipo de documento), «/» o un tipo de documento como carpeta raíz, y cree reglas. La ruta de un documento es tipo de documento/corresponsal/nombre de archivo.',
        'submit' => 'Comprobar conexión',
        'not_configured' => 'Introduzca primero la URL base y el token de API en la configuración del plugin.',
        'to_settings' => 'Ir a la configuración del plugin',
    ],
    'container' => [
        'all' => 'Todos los documentos',
        'kind' => [
            'tag' => 'Etiqueta',
            'correspondent' => 'Corresponsal',
            'type' => 'Tipo de documento',
        ],
    ],
    'flash' => [
        'not_configured' => 'Paperless-ngx no está configurado: falta la URL base o el token de API en la configuración del plugin.',
        'instance_changed' => 'La configuración del plugin apunta a otra instancia de Paperless distinta de esta conexión. Desconéctela primero; los documentos ya incorporados se conservan.',
    ],
    'health' => [
        'not_configured' => 'Paperless-ngx no está configurado (falta la URL base o el token).',
        'not_connected' => 'Todavía no hay conexión de Paperless en la entrada de documentos en la nube.',
        'attention' => 'La conexión de Paperless informa de un error: consulte la entrada de documentos en la nube.',
    ],
];
