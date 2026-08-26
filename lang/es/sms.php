<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// SMS-Kanal (Feature 147, MVP-730). Texte bewusst knapp: eine SMS trägt
// 160 Zeichen, und der Bestätigungstext darf nie zwei Segmente kosten.

return [
    'verification_code' => 'workDiary: su código de confirmación es :code. Es válido durante 10 minutos.',
    'opt_in_hint' => 'Solo recibirá SMS de alerta tras confirmar su número de móvil. Puede retirar su consentimiento en cualquier momento.',
    'code_sent' => 'Se ha enviado un código de confirmación a su número de móvil.',
    'code_invalid' => 'El código es incorrecto o ha caducado.',
    'opt_in_active' => 'Los SMS de alerta están activados.',
    'opt_in_revoked' => 'Los SMS de alerta están desactivados.',
    'section' => 'SMS de alerta',
    'status_active' => 'Activo — número de móvil confirmado',
    'status_inactive' => 'No activo',
    'no_gateway' => 'Esta organización no tiene ninguna pasarela SMS activada.',
    'no_mobile' => 'Introduzca primero un número de móvil.',
    'send_code' => 'Solicitar código',
    'code' => 'Código de confirmación',
    'confirm' => 'Confirmar',
    'revoke' => 'Revocar',
];
