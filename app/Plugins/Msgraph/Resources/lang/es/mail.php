<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'flash' => [
        'msgraph_connection_required' => 'Un buzón de Microsoft 365 requiere primero la conexión de envío de correo en el plugin de Microsoft 365 (scope Mail.ReadWrite).',
    ],
    'transport' => [
        'msgraph' => 'Microsoft 365 (Graph)',
        'msgraph_hint' => 'Microsoft 365: usa la conexión de correo Graph de la organización — no se necesitan credenciales IMAP.',
    ],
];
