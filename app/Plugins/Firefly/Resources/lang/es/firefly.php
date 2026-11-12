<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : firefly.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Refleja entradas y salidas de Firefly III y las asigna a facturas o facturas recibidas mediante el número de documento del concepto. No se contabiliza nada en WorkDiary y no se escribe nada en Firefly III.',
        'capability' => 'Conciliación de pagos',
    ],
    'page' => [
        'subtitle' => 'Movimientos de cuenta de Firefly III, asignados mediante números de documento del concepto; solo lectura.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instalación de Firefly III, p. ej. https://finanzas.example.com (sin /api).',
        'access_token' => 'Token de acceso personal',
        'access_token_help' => 'Cree un token de acceso personal en Firefly III en Opciones → Perfil → OAuth.',
        'window_days' => 'Periodo (días)',
        'window_days_help' => 'Cada sincronización lee los movimientos de este periodo hasta hoy. De 1 a 365 días.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instalación en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
    'connection' => [
        'title' => 'Conexión',
        'connected' => 'Conectado',
        'not_connected' => 'No conectado',
        'verify' => 'Comprobar conexión',
        'settings' => 'Credenciales',
        'disconnect' => 'Desconectar',
        'confirm_disconnect' => '¿Desconectar de Firefly III? La sincronización se detiene; los movimientos reflejados se conservan hasta la próxima ejecución completa.',
        'sync_now' => 'Sincronizar ahora',
        'not_configured' => 'Firefly III no está configurado: introduzca la dirección y el token en la configuración del plugin.',
        'privacy_note' => 'WorkDiary lee entradas y salidas con concepto y contraparte y no escribe nada en Firefly III. WorkDiary no contabiliza pagos a partir de ellas.',
        'instance' => 'Instancia',
        'account' => 'Cuenta',
        'window' => 'Periodo',
        'window_value' => 'Movimientos de los últimos :days días',
        'last_sync' => 'Última sincronización',
        'incomplete' => 'incompleta',
        'last_error' => 'Último error',
    ],
    'list' => [
        'search' => 'Búsqueda',
        'search_placeholder' => 'Concepto, contraparte, número de documento',
        'all_matches' => 'Todas las asignaciones',
        'open_matches' => 'Abiertas (sin asignación o ambiguas)',
        'col' => [
            'date' => 'Fecha',
            'kind' => 'Tipo',
            'counterparty' => 'Contraparte',
            'description' => 'Concepto',
            'match' => 'Asignación',
            'amount' => 'Importe',
            'actions' => 'Acciones',
        ],
        'empty' => 'Todavía no hay movimientos',
        'foreign_amount' => 'Importe en una segunda moneda',
    ],
    // Erledigt markieren (MVP-1628)
    'settle' => [
        'title' => 'Marcar el movimiento como resuelto',
        'action' => 'Resuelto',
        'intro' => 'El movimiento no necesita justificante (por ejemplo, comisión de mantenimiento o retirada privada). Después deja de contar en el mosaico «Movimientos financieros» y en la cola de trabajo; la sincronización no lo cambia.',
        'note' => 'Motivo',
        'done' => 'Movimiento marcado como resuelto.',
        'already' => 'El movimiento ya está resuelto.',
        'reopened' => 'Movimiento reabierto.',
        'reopen' => 'Reabrir',
        'filter' => 'Resueltos',
        'badge' => 'Resuelto por :name el :date',
    ],
    'search' => [
        'domain' => 'Movimientos de Firefly III',
    ],
    'flash' => [
        'not_configured' => 'Firefly III no está configurado: falta la dirección o el token en la configuración del plugin.',
        'verify_failed' => 'La conexión con Firefly III ha fallado (:class).',
        'source_locked' => 'La configuración del plugin apunta a otra instancia. Mientras haya movimientos de la anterior reflejados, el cambio no es posible.',
        'verified' => 'Conexión de Firefly III comprobada: :source_name.',
        'not_ready' => 'Compruebe primero la conexión.',
        'sync_failed' => 'La sincronización ha fallado (:class).',
        'synced' => 'Sincronización: :read leídos, :created nuevos, :updated actualizados, :matched asignados, :removed eliminados.',
        'disconnected' => 'Firefly III desconectado.',
    ],
    'health' => [
        'not_configured' => 'Firefly III no está configurado (falta la dirección o el token).',
        'not_connected' => 'La conexión con Firefly III aún no se ha comprobado.',
    ],
    'work_queue' => [
        'source' => 'Firefly III',
        'unmatched' => '{1} 1 movimiento de Firefly sin asignación|[2,*] :count movimientos de Firefly sin asignación',
        'ambiguous' => '{1} 1 movimiento de Firefly con asignación ambigua|[2,*] :count movimientos de Firefly con asignación ambigua',
    ],
];
