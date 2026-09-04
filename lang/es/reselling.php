<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reselling.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lizenz-Reselling-Abgleich (Feature 151, MVP-757).
return [
    'mapping' => [
        'mode' => [
            'customer' => 'Directamente: la empresa es el cliente',
            'partner' => 'A través de un socio (cliente externo)',
            'contact' => 'Contacto Lexoffice',
        ],
    ],
];
