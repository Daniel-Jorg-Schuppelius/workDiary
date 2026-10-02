<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'channel_not_configured' => ':system no está activado o no está configurado por completo.',
    'channel_payment_note' => 'Documento :number (:system)',
    'retainer_line' => 'Cuota fija mensual :period',
    'retainer_only' => 'Solo disponible en modo de cuota fija.',
    'retainer_voucher_already_linked' => 'Ya hay una factura en :system vinculada a este mes; un envío crearía allí un segundo documento.',
    'trueup_already_open' => 'Ya hay una regularización sin pagar abierta.',
    'trueup_line' => 'Regularización a :date',
    'trueup_no_open_balance' => 'Sin saldo pendiente — no se necesita regularización.',
    'voucher_not_found' => 'No se encontró el documento seleccionado.',
];
