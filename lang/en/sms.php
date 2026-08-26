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
    'verification_code' => 'workDiary: your confirmation code is :code. It is valid for 10 minutes.',
    'opt_in_hint' => 'You will only receive alert SMS after confirming your mobile number. You can withdraw your consent at any time.',
    'code_sent' => 'A confirmation code was sent to your mobile number.',
    'code_invalid' => 'The code is wrong or has expired.',
    'opt_in_active' => 'Alert SMS are enabled.',
    'opt_in_revoked' => 'Alert SMS are disabled.',
    'section' => 'Alert SMS',
    'status_active' => 'Active — confirmed mobile number',
    'status_inactive' => 'Not active',
    'no_gateway' => 'No SMS gateway is enabled for this organisation.',
    'no_mobile' => 'Please add a mobile number first.',
    'send_code' => 'Request code',
    'code' => 'Confirmation code',
    'confirm' => 'Confirm',
    'revoke' => 'Withdraw',
];
