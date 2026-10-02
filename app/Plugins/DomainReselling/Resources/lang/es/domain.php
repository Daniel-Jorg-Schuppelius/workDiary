<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : domain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'health' => [
        'error' => 'La comprobación de estado falló (:class).',
        'no_org_context' => 'Sin contexto de organización.',
        'ok' => 'Conexiones DomainReselling correctas.',
        'pilot_open' => 'Conexión activa, piloto real aún pendiente.',
    ],
    'plugin' => [
        'description' => 'Conectar y gestionar de forma controlada los dominios de una cuenta DomainReselling con los clientes.',
    ],
    'settings' => [
        'connection_note' => 'Las credenciales (usuario/contraseña) se gestionan por conexión — no aquí.',
        'connection_note_link' => 'Ir a las conexiones DomainReselling',
        'timeout' => 'Timeout de API (segundos)',
        'timeout_help' => 'Tiempo de espera máximo por solicitud al proveedor. Predeterminado: 20.',
        'check_budget_per_hour' => 'Presupuesto de comprobación por hora',
        'check_budget_per_hour_help' => 'Número máximo de comprobaciones de disponibilidad por organización y hora — protege contra los puntos de penalización del proveedor. Predeterminado: 300.',
        'check_cache_ttl' => 'Caché de comprobación (segundos)',
        'check_cache_ttl_help' => 'Tiempo durante el cual se almacena en caché un resultado de disponibilidad; los aciertos de caché no consumen presupuesto. Predeterminado: 300.',
        'list_page_size' => 'Tamaño de página de listas',
        'list_page_size_help' => 'Tamaño de lote de las consultas paginadas de listas de dominios durante la sincronización. Predeterminado: 100.',
        'stale_after_hours' => 'Obsoleto después de (horas)',
        'stale_after_hours_help' => 'Antigüedad de los datos a partir de la cual una proyección de dominio se marca como obsoleta. Predeterminado: 24.',
        'range_error' => 'Introduzca un número entero entre :min y :max.',
    ],
];
