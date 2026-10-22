<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : survey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Umfragen (Feature 090, Phase 156).
return [
    'trend' => [
        'title' => 'NPS por mes',
        'unit' => 'NPS',
    ],
    'triggers' => [
        'title' => 'Desencadenantes',
        'order' => 'Invitar automáticamente al finalizar una orden',
        'course' => 'Invitar automáticamente al finalizar un curso',
        'schedule' => 'Invitar con intervalo fijo (días)',
        'scheduleHint' => 'Clientes con una orden finalizada o un ticket resuelto en el intervalo, como máximo una vez por intervalo. Vacío = desactivado.',
        'saved' => 'Desencadenantes guardados.',
    ],
];
