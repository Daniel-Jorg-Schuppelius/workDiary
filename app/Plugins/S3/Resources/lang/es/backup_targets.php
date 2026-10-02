<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    's3' => [
        'connect_title' => 'Conectar destino de copia S3',
        'connect_legend' => 'Almacenamiento de objetos compatible con S3',
        'connect_submit' => 'Conectar y comprobar',
        'selftest_hint' => 'Antes de guardar se escribe, se vuelve a leer y se elimina un archivo de prueba. Si falla, el destino no se activa.',
        'field' => [
            'name' => 'Denominación',
            'endpoint' => 'Endpoint (vacío para AWS S3)',
            'endpoint_help' => 'Introduzca la dirección HTTPS para MinIO, Wasabi, Hetzner o Scaleway. Si se deja vacío, AWS S3 se deduce de la región.',
            'region' => 'Región',
            'region_help' => 'A menudo arbitraria en almacenamiento propio — us-east-1 es el valor habitual.',
            'bucket' => 'Bucket',
            'access_key' => 'Clave de acceso',
            'secret_key' => 'Clave secreta',
            'secret_key_help' => 'Se guarda cifrada y no se vuelve a mostrar.',
            'prefix' => 'Prefijo (opcional)',
            'prefix_help' => 'Subcarpeta dentro del bucket. WorkDiary crea debajo su propia carpeta de seudónimo.',
            'path_style' => 'Direccionar el bucket en la ruta (path style)',
            'path_style_help' => 'Necesario para MinIO y la mayoría de almacenamientos propios. AWS S3 no lo necesita.',
        ],
        'validation' => [
            'https_required' => 'El endpoint debe empezar por https://.',
            'unsafe_url' => 'Este endpoint apunta a una red privada. Habilítelo con S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'El destino no superó la prueba de escritura/lectura (:class). No se activó.',
        ],
    ],
];
