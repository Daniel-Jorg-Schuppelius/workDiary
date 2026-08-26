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
    'verification_code' => 'workDiary : votre code de confirmation est :code. Il est valable 10 minutes.',
    'opt_in_hint' => 'Vous ne recevrez des SMS d’alerte qu’après confirmation de votre numéro de mobile. Vous pouvez retirer votre consentement à tout moment.',
    'code_sent' => 'Un code de confirmation a été envoyé à votre numéro de mobile.',
    'code_invalid' => 'Le code est incorrect ou a expiré.',
    'opt_in_active' => 'Les SMS d’alerte sont activés.',
    'opt_in_revoked' => 'Les SMS d’alerte sont désactivés.',
    'section' => 'SMS d’alerte',
    'status_active' => 'Actif — numéro de mobile confirmé',
    'status_inactive' => 'Inactif',
    'no_gateway' => 'Aucune passerelle SMS n’est activée pour cette organisation.',
    'no_mobile' => 'Veuillez d’abord renseigner un numéro de mobile.',
    'send_code' => 'Demander un code',
    'code' => 'Code de confirmation',
    'confirm' => 'Confirmer',
    'revoke' => 'Révoquer',
];
