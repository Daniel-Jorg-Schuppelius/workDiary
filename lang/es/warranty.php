<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : warranty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Gewährleistungsfristen (Feature 115, MVP-604).
return [
    'title' => 'Garantías',
    'subtitle' => 'Responsabilidad propia y plazos exigibles a subcontratistas en paralelo',
    'empty' => 'Aún no hay ningún plazo de garantía registrado.',
    'overridden' => '(desviado)',
    'created' => 'Plazo de garantía registrado.',
    'closed' => 'Plazo cerrado.',
    'dialog_hint' => 'Sin fecha de fin propia se deriva del fundamento jurídico. El plazo empieza el día de la recepción, no en la factura ni al finalizar la obra.',
    'override_reason' => 'Motivo de una fecha de fin distinta',
    'override_reason_hint' => 'Obligatorio en cuanto la fecha de fin se aparte del fundamento jurídico.',
    'custom_needs_end' => 'Un plazo libremente pactado necesita una fecha de fin.',
    'end_before_start' => 'El fin debe ser posterior al inicio.',
    'override_needs_reason' => 'Una fecha de fin distinta necesita una justificación.',
    'not_open' => 'Este plazo ya no está abierto.',
    'action' => [
        'create' => 'Registrar plazo',
        'close' => 'Cerrar',
    ],
    'kpi' => [
        'owed' => 'Responsabilidad propia',
        'owed_hint' => 'Plazos que debemos al cliente.',
        'claimable' => 'Exigibles',
        'claimable_hint' => 'Plazos frente a subcontratistas.',
        'expiring' => 'Vence en 6 meses',
        'critical' => 'El plazo del subcontratista acaba antes',
        'critical_hint' => 'Después se responde en solitario por un defecto causado por otro.',
    ],
    'critical' => [
        'heading' => 'Plazos de subcontratistas que acaban antes que la propia responsabilidad',
        'hint' => 'Compruébelo ahora y reclame en caso de duda — después se pierde el derecho frente al subcontratista mientras la propia responsabilidad continúa.',
    ],
    'column' => [
        'side' => 'Lado',
        'project' => 'Proyecto',
        'party' => 'Contraparte',
        'trade' => 'Oficio',
        'basis' => 'Fundamento',
        'starts_on' => 'Inicio',
        'ends_on' => 'Fin',
        'status' => 'Estado',
        'protocol' => 'Acta de recepción',
        'customer' => 'Cliente',
        'supplier' => 'Subcontratista',
        'responsible' => 'Responsable',
        'note' => 'Nota',
    ],
    'filter' => [
        'side' => 'Lado',
        'status' => 'Estado',
    ],
];
