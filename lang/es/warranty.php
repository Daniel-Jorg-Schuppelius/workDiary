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
    'updated' => 'Plazo de garantía actualizado.',
    'edit_hint' => 'Un fin de plazo desplazado arrastra las retenciones de garantía abiertas vinculadas; los avales vinculados indican su devolución tras el nuevo fin. Un fin que se aparta de la regla necesita una justificación.',
    'closed_locked' => 'Un plazo cerrado ya no se puede modificar.',
    'claim' => [
        'title' => 'Registrar reclamación por defecto',
        'submit' => 'Marcar como reclamado',
        'legend' => 'Defecto reclamado dentro del plazo',
        'hint' => 'El plazo pasa al estado «Reclamado» y sigue sin cambios hasta el :date. La suspensión o el reinicio del plazo son cuestiones jurídicas; si es necesario, introduzca un nuevo fin con «Editar». Mientras la reclamación esté abierta, un aval vinculado no indica su devolución.',
        'case' => 'Expediente de reclamación',
        'case_hint' => 'Opcional: el expediente en el que se tramita la reclamación.',
        'unknown_case' => 'No se encontró el expediente de reclamación elegido.',
        'recorded' => 'Reclamación registrada.',
    ],
    'action' => [
        'create' => 'Registrar plazo',
        'close' => 'Cerrar',
        'edit' => 'Editar plazo',
        'claim' => 'Registrar reclamación',
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
