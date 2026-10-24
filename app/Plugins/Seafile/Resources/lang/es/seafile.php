<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : seafile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura archivos de una biblioteca de una instancia autoalojada de Seafile en la recepción de documentos en la nube y sirve como destino de copia de seguridad cifrado. Las reglas de recepción llevan los archivos a las facturas recibidas o al archivo de documentos.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instancia de Seafile para la recepción de documentos, p. ej. https://seafile.example.com (sin /api2).',
        'api_token' => 'Token de API',
        'api_token_help' => 'Token de cuenta de un usuario de Seafile con acceso de lectura a la biblioteca. Para la recepción, WorkDiary no modifica nada en Seafile. El token lo devuelve POST /api2/auth-token/ con nombre de usuario y contraseña (con inicio de sesión en dos pasos, además la cabecera X-Seafile-OTP con el código actual).',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
    'action' => [
        'connect_intake' => 'Conectar Seafile',
    ],
    'connect' => [
        'title' => 'Conectar Seafile',
        'legend' => 'Conexión',
        'instance' => 'Instancia de los ajustes del plugin: :instance',
        'name' => 'Nombre de la conexión',
        'hint' => 'Después elija la biblioteca, la carpeta raíz dentro de ella y cree reglas. Las bibliotecas cifradas no se ofrecen.',
        'submit' => 'Comprobar conexión',
        'not_configured' => 'Introduzca primero la dirección y el token de API en los ajustes del plugin.',
        'to_settings' => 'Ir a los ajustes del plugin',
    ],
    'container' => [
        'library' => 'Biblioteca',
    ],
    'flash' => [
        'not_configured' => 'Seafile no está configurado: falta la dirección o el token de API en los ajustes del plugin.',
        'instance_changed' => 'Los ajustes del plugin apuntan a otra instancia de Seafile distinta de esta conexión. Desconéctela primero; los justificantes ya incorporados se conservan.',
    ],
    'health' => [
        'not_configured' => 'Seafile no está configurado (falta la dirección o el token).',
        'not_connected' => 'Todavía no hay ninguna conexión de Seafile en la recepción de documentos en la nube.',
        'attention' => 'La conexión de Seafile informa de un error: vea la recepción de documentos en la nube.',
        'backup_attention' => 'Un destino de copia de seguridad de Seafile requiere atención: vea los destinos de copia de seguridad.',
    ],
    'backup' => [
        'connect_title' => 'Conectar Seafile como destino de copia de seguridad',
        'connect_legend' => 'Credenciales',
        'connect_submit' => 'Conectar',
        'name' => 'Nombre',
        'server_url' => 'URL del servidor',
        'server_url_help' => 'Solo HTTPS. Ejemplo: https://seafile.example.com',
        'api_token' => 'Token de API',
        'api_token_help' => 'Token de cuenta de un usuario de Seafile propio para las copias de seguridad, con acceso de escritura a la biblioteca. El token lo devuelve POST /api2/auth-token/ con nombre de usuario y contraseña (con inicio de sesión en dos pasos, además la cabecera X-Seafile-OTP con el código actual).',
        'library' => 'Biblioteca',
        'library_help' => 'Nombre o ID de una biblioteca existente sin cifrar. WorkDiary solo escribe en su propia carpeta dentro de ella.',
        'https_required' => 'La URL del servidor debe empezar por https://.',
        'unsafe_url' => 'La URL del servidor debe ser accesible públicamente (sin destino interno/privado).',
        'library_not_found' => 'Esta biblioteca no existe para la cuenta. Créela en Seafile o compruebe el nombre y el uso compartido.',
        'library_encrypted' => 'Esta biblioteca está cifrada. Elija una biblioteca sin cifrar: WorkDiary cifra la copia de seguridad por sí mismo.',
        'library_ambiguous' => 'Varias bibliotecas tienen este nombre. Indique en su lugar el ID de la biblioteca.',
        'library_read_only' => 'La cuenta no puede escribir en esta biblioteca. Elija una biblioteca con permiso de lectura y escritura.',
    ],
];
