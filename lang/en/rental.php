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
        'title' => 'Rental terms',
        'signed' => 'Contract :contract, version :revision, signed on :date',
        'missing' => 'No signed rental terms exist for this customer.',
        'missing_required' => 'No signed rental terms — handover is only possible afterwards.',
        'create_agreement' => 'Create rental terms',
        'required' => 'Handover requires rental terms signed by the customer (organisation setting).',
    ],
];
