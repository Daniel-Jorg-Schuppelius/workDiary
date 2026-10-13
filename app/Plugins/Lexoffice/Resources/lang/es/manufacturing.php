<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : manufacturing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'order' => [
        'action' => [
            'push_lexoffice' => 'Enviar a Lexoffice',
        ],
        'flash' => [
            'lexoffice_pushed' => 'Albarán enviado a Lexoffice.',
            'already_linked' => 'Este documento ya existe en Lexoffice (ID :id); no se ha creado un segundo.',
        ],
    ],
];
