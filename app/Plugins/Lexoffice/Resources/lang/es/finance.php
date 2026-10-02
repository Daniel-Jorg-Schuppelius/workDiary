<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'error' => [
        'lexoffice_contact_missing' => 'No hay contacto de Lexoffice para el cliente — sincronice primero el contacto.',
        'lexoffice_delivery_no_customer' => 'Una entrega sin cliente no puede transferirse como albarán.',
        'lexoffice_delivery_not_linked' => 'No hay ningún albarán de Lexoffice vinculado a esta entrega.',
        'lexoffice_dunning_not_invoice' => 'Solo se puede crear un aviso de pago para una factura.',
        'lexoffice_not_configured' => 'Lexoffice no está configurado para esta organización (falta la clave API).',
        'lexoffice_oc_no_customer' => 'Una orden de fabricación sin cliente no puede transferirse como confirmación de pedido.',
        'lexoffice_oc_not_linked' => 'No hay ninguna confirmación de pedido de Lexoffice vinculada a esta orden de fabricación.',
        'lexoffice_quote_no_customer' => 'Una orden de fabricación sin cliente no puede transferirse como oferta.',
        'lexoffice_quote_not_linked' => 'No hay ninguna oferta de Lexoffice vinculada a esta orden de fabricación.',
    ],
    'lexoffice' => [
        'introduction' => 'Les facturamos como sigue nuestras entregas y servicios.',
        'delivery_title' => 'Albarán',
    ],
];
