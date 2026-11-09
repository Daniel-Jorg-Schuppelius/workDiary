<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → sevDesk (Feature 163, MVP-1507).

return [
    'label' => 'sevDesk',
    'amount_differs' => 'En sevDesk ya existe el documento :number con otro importe. Revíselo allí y vuelva a iniciar la transferencia.',
    'candidate' => 'Es probable que sevDesk ya contenga el mismo documento (:voucher), pero sin número de factura no es seguro. Vincúlelo o haga crear un documento nuevo.',
    'no_party' => 'La recepción aún no está asignada a ninguna parte.',
    'contact_missing' => 'El contacto :party no se pudo encontrar de forma inequívoca ni crear en sevDesk. Vincule el contacto en sevDesk y vuelva a iniciar la transferencia.',
    'no_account' => 'En los ajustes de sevDesk no hay ninguna cuenta contable para esta dirección.',
    'currency' => 'sevDesk solo recibe de la recepción de facturas documentos en euros. Registre el documento en sevDesk; la próxima transferencia lo vincula mediante el número de factura.',
    'rates' => 'Los tipos impositivos no encajan con sevDesk (0, 7 o 19 %) o los totales están incompletos. Registre el documento en sevDesk; la próxima transferencia lo vincula mediante el número de factura.',
    'tax_case' => 'La inversión del sujeto pasivo, los casos intracomunitarios y las exportaciones los contabiliza la contabilidad en sevDesk. Registre allí el documento; la próxima transferencia lo vincula mediante el número de factura.',
    'xml_requires_update' => 'sevDesk solo acepta XRechnung a partir de la versión contable 2.0 y la recepción no incluye ningún PDF.',
    'settings' => [
        'transfer' => 'Transferir la recepción de facturas a sevDesk',
        'transfer_help' => 'Las recepciones asignadas se envían a sevDesk como borrador con el original. Si allí ya existe un documento con el mismo número, solo se vincula.',
        'incoming_account' => 'Cuenta contable para documentos entrantes (ID)',
        'outgoing_account' => 'Cuenta contable para documentos salientes (ID)',
        'account_help' => 'ID de la cuenta en el plan contable de sevDesk: AccountDatev en la versión contable 2.0, AccountingType en la 1.0. Sin cuenta, la transferencia espera.',
    ],
];
