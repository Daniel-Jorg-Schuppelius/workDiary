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
    'verification_code' => 'workDiary: Ihr Bestätigungscode lautet :code. Er gilt 10 Minuten.',
    'opt_in_hint' => 'Alarm-SMS erhalten Sie nur nach Bestätigung Ihrer Mobilnummer. Sie können die Einwilligung jederzeit widerrufen.',
    'code_sent' => 'Bestätigungscode wurde an Ihre Mobilnummer gesendet.',
    'code_invalid' => 'Der Code stimmt nicht oder ist abgelaufen.',
    'opt_in_active' => 'Alarm-SMS sind aktiviert.',
    'opt_in_revoked' => 'Alarm-SMS sind deaktiviert.',
    'section' => 'Alarm-SMS',
    'status_active' => 'Aktiv — bestätigte Mobilnummer',
    'status_inactive' => 'Nicht aktiv',
    'no_gateway' => 'Für diese Organisation ist kein SMS-Gateway aktiviert.',
    'no_mobile' => 'Hinterlegen Sie zuerst eine Mobilnummer.',
    'send_code' => 'Code anfordern',
    'code' => 'Bestätigungscode',
    'confirm' => 'Bestätigen',
    'revoke' => 'Widerrufen',
];
