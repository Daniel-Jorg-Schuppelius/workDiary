<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diagnostics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Diagnose-Seite: Überwachungszugang (MVP-1618).
return [
    'token' => [
        'heading' => 'Acceso de monitorización',
        'intro' => 'Una monitorización externa obtiene el informe sin iniciar sesión en :url y envía el token en la cabecera «Authorization: Bearer …». El informe no contiene nombres ni direcciones.',
        'create' => 'Crear acceso',
        'name' => 'Denominación',
        'name_help' => 'Por ejemplo, el nombre del sistema de monitorización, para que pueda identificar el acceso más adelante.',
        'hint' => 'Identificador',
        'created_by' => 'Creado',
        'last_used' => 'Última consulta',
        'status' => 'Estado',
        'active' => 'Activo',
        'revoked_state' => 'Revocado',
        'revoke' => 'Revocar',
        'revoke_confirm' => '¿Revocar este acceso? La monitorización dejará de recibir el informe.',
        'shown_once' => 'Copie el token ahora. Solo se muestra esta vez y no se puede recuperar.',
        'empty' => 'Todavía no se ha creado ningún acceso de monitorización.',
        'created' => 'Acceso de monitorización creado.',
        'revoked' => 'Acceso de monitorización revocado.',
    ],
];
