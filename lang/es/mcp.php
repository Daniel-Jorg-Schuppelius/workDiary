<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mcp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// MCP-Server für KI-Assistenten (MVP-1063/1064).
return [
    'error' => [
        'forbidden' => 'Sin acceso a esta herramienta.',
        'scope' => 'Este token no está habilitado para asistentes de IA (MCP). Cree un token con el permiso «Asistente de IA (MCP)».',
        'not_found' => 'No encontrado.',
        'invalid_number' => 'Posición :position: la cantidad, el precio o el tipo impositivo no es un número.',
        'conflicts' => 'No se ha movido — conflictos: :list',
    ],
    'oauth' => [
        'title' => 'Conectar asistente de IA',
        'intro' => ':client desea acceder a los datos de :organization en workDiary en nombre de :user.',
        'scope' => [
            'read' => 'Leer: clientes, proyectos, pedidos, presupuestos, facturas, partidas abiertas, tiempos, citas, indicadores y búsqueda — con sus permisos.',
            'write' => 'Crear borradores: borradores de presupuestos y facturas, crear clientes, mover intervenciones. Emitir y enviar sigue en sus manos.',
        ],
        'target' => 'Tras su aprobación, el acceso se concede a',
        'untrusted_warning' => 'Este destino no pertenece a ningún asistente de IA conocido. Apruebe solo si acaba de configurar usted mismo la conexión; de lo contrario, un tercero obtendrá acceso a sus datos.',
        'untrusted_confirm' => 'He configurado yo mismo la conexión con :target.',
        'untrusted_required' => 'Confirme que ha configurado usted mismo la conexión con este destino.',
        'revoke_hint' => 'Puede revocar el acceso en cualquier momento en Perfil → Tokens de API.',
        'approve' => 'Permitir acceso',
        'deny' => 'Rechazar',
        'error' => [
            'title' => 'Conexión no posible',
            'client' => 'Cliente desconocido: configure de nuevo el conector.',
            'redirect_uri' => 'La dirección de retorno no está registrada para este cliente o no está permitida (solo https o direcciones locales).',
            'response_type' => 'Solo se admite el tipo de respuesta «code».',
            'pkce' => 'Se requiere PKCE con S256.',
            'resource' => 'El recurso solicitado no es este servidor MCP.',
            'scope' => 'No se ha solicitado ningún permiso válido (mcp:read, mcp:write).',
            'grant' => 'El código o el token de actualización no es válido, ha caducado o ya se ha utilizado.',
            'grant_type' => 'Este tipo de concesión no es compatible.',
            'disabled' => 'Su organización no ha habilitado los asistentes de IA (MCP). La administración lo habilita en la configuración de la organización.',
            'no_organization' => 'Su cuenta no pertenece a ninguna organización.',
        ],
    ],
];
