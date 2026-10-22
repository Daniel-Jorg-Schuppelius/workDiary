<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : helpdesk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Helpdesk und Service Desk (Feature 065, Phase 156).
return [
    'form' => [
        'noCustomer' => 'sin cliente',
        'noProject' => 'sin proyecto',
        'noAsset' => 'sin activo',
    ],
    'service' => [
        'label' => 'Servicio de negocio afectado',
        'none' => 'sin servicio de negocio',
        'offering' => 'Oferta de servicio',
        'noOffering' => 'sin oferta',
        'all' => 'Todos los servicios de negocio',
    ],
    'map' => [
        'title' => 'Mapa de servicios',
        'subtitle' => 'Qué servicios de negocio dependen entre sí y quién se ve afectado por una caída.',
        'empty' => 'Todavía no hay servicios de negocio en el catálogo.',
        'service' => 'Servicio de negocio',
        'dependsOn' => 'Depende de',
        'dependsOnHint' => 'Si falla uno de estos servicios de negocio, este también se ve afectado. Selección múltiple con Ctrl o Cmd.',
        'dependents' => 'Requerido por',
        'affected' => 'Afectados por una caída',
        'openTickets' => 'Tickets abiertos',
        'cycle' => 'Esta dependencia crearía un ciclo: un servicio de negocio seleccionado ya depende de este.',
    ],
    'impact' => [
        'title' => 'Impacto',
        'none' => 'Ningún otro servicio de negocio depende de «:service».',
        'affected' => 'Si falla «:service», también se ven afectados estos servicios de negocio:',
        'openTickets' => ':count ticket abierto|:count tickets abiertos',
    ],
    'time' => [
        'title' => 'Tiempos del ticket',
        'total' => 'Total',
        'billable' => 'facturable',
        'book' => 'Registrar tiempo',
        'empty' => 'Todavía no hay tiempos registrados en este ticket.',
        'needsProject' => 'El ticket necesita un proyecto para registrar tiempos.',
        'ticketOptional' => 'Ticket (opcional)',
        'noTicket' => 'Sin ticket',
    ],
    'routing' => [
        'conditionsHint' => 'Claves: kind, priority, source, customer_id, queue_id, sla_contract_id, business_service_id, service_offering_id, locale (p. ej. "en"), business_hours ("in" o "out"). Valores sueltos o como lista.',
        'actionsHint' => 'Claves: set_queue, set_priority, set_sla, set_team, assign ("round_robin" por turnos o "least_loaded" según carga, dentro del equipo de la cola).',
    ],
    'report' => [
        'month' => 'Mes',
        'created' => 'Notificados',
        'reactionP50' => 'Reacción P50 (h)',
        'resolutionP50' => 'Resolución P50 (h)',
        'trendCreated' => 'Tickets notificados por mes',
        'trendResolution' => 'Tiempo de resolución P50 por mes',
        'trendTable' => 'Evolución de los últimos doce meses',
        'byService' => 'Tickets por servicio de negocio',
        'open' => 'Abiertos',
        'breached' => 'Plazo de resolución incumplido',
        'csvTrends' => 'CSV evolución',
        'csvServices' => 'CSV servicios de negocio',
    ],
    'portal' => [
        'asset' => 'Equipo afectado',
        'noAsset' => 'ningún equipo concreto',
        'service' => 'Servicio afectado',
        'noService' => 'ningún servicio concreto',
    ],
];
