<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cockpit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Panels der Entitäts-Cockpits (Feature 182).
return [
    'more' => '… y :count más',
    'contracts' => ['title' => 'Contratos y plazos'],
    'warranties' => ['title' => 'Plazos de garantía'],
    'guarantees' => ['title' => 'Avales'],
    'tickets' => ['title' => 'Tickets abiertos'],
    'surveys' => [
        'title' => 'Encuestas',
        'summary' => ':responded de :total invitaciones respondidas',
        'summary_nps' => 'NPS :nps de :answers valoraciones · :responded de :total invitaciones respondidas',
    ],
    'purchase_orders' => ['title' => 'Pedidos de compra'],
    'credentials' => [
        'title' => 'Justificantes obligatorios',
        'summary' => 'Estado general: :status',
    ],
    'project' => [
        'budget' => 'Presupuesto consumido',
        'budget_of' => 'de :budget',
    ],
];
