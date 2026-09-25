<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Condiciones de alquiler',
        'signed' => 'Contrato :contract, versión :revision, firmado el :date',
        'missing' => 'No hay condiciones de alquiler firmadas para este cliente.',
        'missing_required' => 'No hay condiciones de alquiler firmadas; la entrega solo es posible después.',
        'create_agreement' => 'Crear condiciones de alquiler',
        'required' => 'La entrega requiere condiciones de alquiler firmadas por el cliente (ajuste de la organización).',
    ],
];
