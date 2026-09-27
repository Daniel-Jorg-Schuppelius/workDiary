<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Patrones llamativos (defectos en serie, lotes)',
        'hint' => 'Grupos con al menos :threshold reclamaciones en el periodo. Una indicación, no una decisión.',
        'none' => 'No hay patrones llamativos en el periodo.',
        'rule_label' => 'Regla',
        'group' => 'Grupo',
        'cases' => 'Casos',
        'count' => 'Cantidad',
        'rule' => [
            'lot' => 'Lote',
            'article_defect' => 'Artículo × tipo de defecto',
            'article_cause' => 'Artículo × causa',
            'supplier_defect' => 'Proveedor × tipo de defecto',
            'entry_type_cause' => 'Tipo de orden × causa',
        ],
        'notify_title' => 'Patrón de reclamaciones llamativo: :label',
        'notify_message' => ':count reclamaciones en :days días (:rule).',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Etiqueta de devolución',
        'create' => 'Crear etiqueta de devolución',
        'download' => 'Descargar etiqueta',
        'created' => 'Etiqueta de devolución creada (envío :tracking).',
        'no_address' => 'La etiqueta de devolución necesita la dirección del cliente (calle, código postal, ciudad).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'capability' => 'Registrar una devolución',
        'nav' => 'Registrar devolución',
        'title' => 'Registrar una devolución',
        'intro' => 'Elija la entrega o el objeto, describa el motivo y adjunte fotos si es necesario. Recibirá un número de devolución; si procede, facilitamos una etiqueta de devolución.',
        'empty' => 'No hay entregas ni objetos para su cuenta.',
        'submit' => 'Registrar devolución',
        'label' => 'Descargar etiqueta de devolución',
        'field' => [
            'delivery' => 'Entrega',
            'asset' => 'Objeto',
            'serial_no' => 'Número de serie',
            'quantity' => 'Cantidad',
            'title' => 'Descripción breve',
            'description' => 'Motivo de la devolución',
            'photos' => 'Fotos o justificantes (máx. 5)',
        ],
        'flash' => [
            'submitted' => 'Devolución registrada: reclamación :number, número de devolución :rma.',
        ],
        'error' => [
            'subject' => 'Elija una entrega o un objeto.',
            'serial' => 'Este número de serie no pertenece a la entrega elegida.',
        ],
    ],
];
