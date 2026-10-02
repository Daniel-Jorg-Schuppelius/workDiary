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
        'connect_google' => 'Conectar Google Drive',
    ],
    'google' => [
        'description' => 'Lee documentos de carpetas de Google Drive supervisadas (entrada de documentos en la nube) — Mi unidad y unidades compartidas; despliegue bloqueado hasta la verificación OAuth de Google.',
        'health' => [
            'not_configured' => 'Claves de cliente de Google Drive sin configurar.',
            'no_org_context' => 'Sin contexto de organización (ejecución del sistema).',
            'attention' => 'Al menos una conexión de Google Drive necesita atención (reautenticación/bloqueada).',
            'backup_attention' => 'El destino de copia de seguridad de Google Drive necesita atención (reautenticación/bloqueado) — afecta a todas las organizaciones.',
            'ok' => 'Conexiones de Google Drive correctas.',
            'error' => 'La comprobación de estado falló (:class).',
        ],
    ],
];
