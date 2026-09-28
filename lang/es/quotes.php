<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : quotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Angebote (Feature 112, MVP-601: Nachfassen).
return [
    'follow_up' => [
        'title' => 'Seguimiento de presupuestos',
        'subtitle' => 'Seguimientos pendientes, presupuestos que vencen y enviados sin fecha',
        'action' => 'Registrar seguimiento',
        'submit' => 'Registrar',
        'recorded' => 'Seguimiento registrado.',
        'scheduled' => 'Fecha de seguimiento establecida.',
        'empty' => 'Nada que seguir.',
        'dialog_title' => 'Seguimiento del presupuesto :number',
        'dialog_hint' => 'El resultado se guarda como nota de comunicación en el expediente del cliente.',
        'result' => 'Resultado de la conversación',
        'result_hint' => '¿Qué dijo el cliente? Esta nota será la base del próximo presupuesto.',
        'next_at' => 'Volver a hacer seguimiento el',
        'next_at_hint' => 'Dejar vacío cuando el seguimiento haya terminado.',
        'note_subject' => 'Seguimiento del presupuesto :number',
        'next_action' => 'Volver a hacer seguimiento del presupuesto :number',
        'wrong_status' => 'Solo se puede hacer seguimiento de presupuestos enviados o aprobados.',
        'no_customer' => 'El presupuesto no tiene cliente — sin cliente no hay expediente para la nota.',
        'kpi' => [
            'due' => 'Pendientes',
            'upcoming' => 'Próximos',
            'expiring' => 'Vence (:days días)',
            'expiring_hint' => 'Sin reacción — después habrá que rehacer o prorrogar el presupuesto.',
            'untracked' => 'Sin fecha',
            'untracked_hint' => 'Enviado, pero nadie fijó una fecha de seguimiento.',
        ],
        'section' => [
            'due' => 'Pendientes',
            'upcoming' => 'Próximos',
            'expiring' => 'Vence sin reacción',
            'untracked' => 'Enviado sin fecha de seguimiento',
        ],
        'column' => [
            'number' => 'Presupuesto',
            'customer' => 'Cliente',
            'owner' => 'Responsable',
            'follow_up_at' => 'Seguimiento el',
            'valid_until' => 'Válido hasta',
            'total' => 'Total',
        ],
        'filter' => ['mine' => 'Solo los míos'],
    ],
    // Trefferquote (Feature 112, MVP-998).
    'win_rate' => [
        'title' => 'Tasa de éxito de los presupuestos',
        'subtitle' => 'Presupuestos ganados, perdidos y vencidos según la fecha de decisión o el fin de validez',
        'group' => 'Agrupar por',
        'group_customer' => 'Cliente',
        'group_owner' => 'Responsable',
        'rate' => 'Tasa de éxito (cantidad)',
        'volume_rate' => 'Tasa de éxito (volumen)',
        'won' => 'Ganados',
        'lost' => 'Perdidos',
        'expired' => 'Vencidos',
        'won_volume' => 'Volumen ganado',
        'decided_volume' => 'Volumen decidido',
        'open' => 'Presupuestos abiertos',
        'open_hint' => 'Sin decidir y sin vencer: no cuentan para la tasa.',
        'empty' => 'En el periodo no se decidió ningún presupuesto.',
        'note' => 'Solo cuenta la versión más reciente por número de presupuesto; volumen neto, en aceptación parcial la parte aceptada.',
        'no_owner' => 'Sin responsable',
        'link' => 'Tasa de éxito',
    ],
];
