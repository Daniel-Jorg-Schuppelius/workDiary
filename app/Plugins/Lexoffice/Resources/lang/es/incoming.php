<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → Lexware Office (Feature 163, MVP-1111).

return [
    'label' => 'Lexware Office',
    'amount_differs' => 'En Lexware ya existe el comprobante :number con otro importe. Revíselo allí y vuelva a iniciar el traspaso.',
    'remark' => 'Desde las facturas recibidas de WorkDiary (:sender).',
    'no_party' => 'El documento aún no está asignado a ninguna parte.',
    'contact_missing' => 'El contacto :party no se pudo encontrar ni crear en Lexware.',
    'contact_role' => 'Lexware rechaza el contacto :party por su rol. Añada en Lexware el rol de proveedor o cliente y vuelva a iniciar el traspaso.',
    'header_only' => [
        'no_category' => 'Traspasado sin importes: no hay categoría contable para la parte ni en la configuración.',
        'currency' => 'Traspasado sin importes: Lexware solo acepta comprobantes en euros.',
        'rates' => 'Traspasado sin importes: los tipos impositivos no encajan con Lexware o los totales están incompletos.',
    ],
    'settings' => [
        'transfer' => 'Traspasar las facturas recibidas a Lexware Office',
        'transfer_help' => 'Los documentos asignados llegan a Lexware como «por revisar». Si allí ya existe un comprobante con el mismo número, solo se vincula.',
        'incoming_category' => 'Categoría predeterminada para documentos de entrada',
        'outgoing_category' => 'Categoría predeterminada para documentos de salida',
        'category_help' => 'Sin categoría, los comprobantes llegan a Lexware sin importes. Una categoría en el proveedor o cliente tiene prioridad. La lista procede de la sincronización de categorías.',
        'no_category' => '— ninguna —',
    ],
    'category' => [
        'title' => 'Categoría contable en Lexware',
        'help' => 'Los documentos de las facturas recibidas llegan a Lexware con esta categoría en lugar de la predeterminada de la configuración.',
        'save' => 'Guardar categoría',
        'saved' => 'Categoría contable guardada.',
        'cleared' => 'Categoría contable eliminada; se aplica la predeterminada de la configuración.',
    ],
];
