<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : whistleblowing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Cadenas para el módulo de denuncias (categorías, etc.).
 */

return [
    'category' => [
        'corruption' => 'Corrupción y soborno',
        'fraud' => 'Fraude, apropiación indebida y robo',
        'money_laundering' => 'Blanqueo de capitales y financiación del terrorismo',
        'procurement' => 'Infracciones en contratación pública y competencia',
        'data_protection' => 'Protección de datos y seguridad de la información',
        'product_safety' => 'Seguridad de los productos y protección del consumidor',
        'environment' => 'Infracciones medioambientales y de seguridad laboral',
        'discrimination' => 'Discriminación, acoso y abuso de poder',
        'policy_violation' => 'Infracción de las directrices internas',
        'other' => 'Otra posible infracción legal',
    ],
    'status' => [
        'submitted' => 'Recibida',
        'acknowledged' => 'Recepción confirmada',
        'triage' => 'Evaluación preliminar',
        'investigating' => 'En tramitación',
        'waiting_reporter' => 'A la espera del denunciante',
        'referred' => 'Remitida',
        'closed_substantiated' => 'Cerrada – fundada',
        'closed_unsubstantiated' => 'Cerrada – no fundada',
        'closed_out_of_scope' => 'Cerrada – fuera del ámbito de aplicación',
        'closed_duplicate' => 'Cerrada – duplicada',
        'retention_review' => 'Revisión del plazo de conservación',
        'legal_hold' => 'Bloqueo de borrado (legal hold)',
        'deleted' => 'Eliminada',
    ],
    'reporter_status' => [
        'received' => 'Recibida y en revisión',
        'in_progress' => 'En tramitación',
        'awaiting_you' => 'Se espera su respuesta',
        'closed' => 'Cerrada',
    ],
    'priority' => [
        'normal' => 'Normal',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ],
    'role' => [
        'owner' => 'Responsable',
        'processor' => 'Tramitación',
        'reviewer' => 'Revisión',
    ],
];
