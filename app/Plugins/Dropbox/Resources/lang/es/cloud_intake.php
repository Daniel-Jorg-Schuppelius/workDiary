<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_dropbox' => 'Conectar Dropbox',
    ],
    'dropbox' => [
        'description' => 'Lee documentos de carpetas de Dropbox supervisadas (entrada de documentos en la nube) — con reglas de carpetas, comprobante de transferencia y bandeja para casos dudosos.',
        'health' => [
            'not_configured' => 'Claves de la aplicación de Dropbox sin configurar.',
            'no_org_context' => 'Sin contexto de organización (ejecución del sistema).',
            'attention' => 'Al menos una conexión de Dropbox necesita atención (reautenticación/bloqueada).',
            'backup_attention' => 'El destino de copia de seguridad de Dropbox necesita atención (reautenticación/bloqueado) — afecta a todas las organizaciones.',
            'ok' => 'Conexiones de Dropbox correctas.',
            'error' => 'La comprobación de estado falló (:class).',
        ],
    ],
];
