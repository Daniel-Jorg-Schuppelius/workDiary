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
    // Steuerfall der Übergabe (MVP-1712)
    'tax_case' => [
        'not_taxable_outside_third_country' => 'Para servicios no sujetos en Alemania, Lexoffice solo ofrece «servicios a terceros países». Si el lugar de prestación está en la UE (por ejemplo, un inmueble en otro país de la UE) o en varias zonas, no hay un tipo de impuesto adecuado: facture en workDiary o registre el documento manualmente en Lexoffice.',
        'contact_required' => 'Para «:case», Lexoffice exige un contacto creado allí; el cliente aún no está vinculado con Lexoffice. Transfiera primero el contacto.',
        'time_voucher' => 'Para el caso fiscal «:case», la interfaz de comprobantes de Lexoffice no tiene categoría contable (por ejemplo, § 13b en Alemania o exención según el § 4 UStG). Transfiera los tiempos a Lexoffice mediante la entrega de facturación, que envía el tipo de impuesto adecuado.',
        'time_voucher_mixed' => 'Los tiempos dan lugar a varios casos fiscales (:case); un comprobante solo admite uno. Facture los tiempos por separado, por ejemplo mediante la entrega de facturación por caso fiscal.',
        'time_voucher_currency' => 'Un comprobante de Lexoffice solo admite importes en euros, pero al cliente se le factura en :currency. Facture en workDiary o registre el comprobante manualmente en Lexoffice.',
    ],
    'error' => [
        'lexoffice_contact_missing' => 'No hay contacto de Lexoffice para el cliente — sincronice primero el contacto.',
        'lexoffice_delivery_no_customer' => 'Una entrega sin cliente no puede transferirse como albarán.',
        'lexoffice_delivery_not_linked' => 'No hay ningún albarán de Lexoffice vinculado a esta entrega.',
        'lexoffice_dunning_not_invoice' => 'Solo se puede crear un aviso de pago para una factura.',
        'lexoffice_not_configured' => 'Lexoffice no está configurado para esta organización (falta la clave API).',
        'lexoffice_outcome_unclear' => 'Resultado del traspaso a Lexoffice incierto (tiempo de espera agotado tras el envío): no repita a ciegas; la próxima ejecución busca el borrador mediante el marcador de origen.',
        'contact_billing_address' => 'El contacto «:customer» necesita exactamente una dirección de facturación en Lexoffice, pero tiene :count. Corrija la dirección allí y repita el traspaso.',
        'lexoffice_oc_no_customer' => 'Una orden de fabricación sin cliente no puede transferirse como confirmación de pedido.',
        'lexoffice_oc_not_linked' => 'No hay ninguna confirmación de pedido de Lexoffice vinculada a esta orden de fabricación.',
        'lexoffice_quote_no_customer' => 'Una orden de fabricación sin cliente no puede transferirse como oferta.',
        'lexoffice_document_no_customer' => 'Un documento sin cliente no puede transferirse a Lexoffice.',
        'lexoffice_quote_not_linked' => 'No hay ninguna oferta de Lexoffice vinculada a esta orden de fabricación.',
    ],
    'lexoffice' => [
        'introduction' => 'Les facturamos como sigue nuestras entregas y servicios.',
        'delivery_title' => 'Albarán',
        'transfer_marker' => 'Referencia de traspaso :marker',
    ],
    'vouchers' => [
        'sync_now' => 'Sincronizar comprobantes ahora',
        'sync_confirm' => "¿Sincronizar ahora los comprobantes con Lexoffice? La ejecución obtiene en segundo plano los comprobantes nuevos y modificados; la sincronización completa diaria no se ve afectada.",
        'sync_hint' => "Obtiene en segundo plano los comprobantes nuevos y modificados de Lexoffice: el equivalente manual de la sincronización de comprobantes programada.",
    ],
];
