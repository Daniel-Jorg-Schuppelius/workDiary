<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Kundenportal: Passwort vergessen, Zugang zurücksetzen, Rechnungsdokument (MVP-1096/1097).
return [
    'password' => [
        'mail_subject' => 'Reset your password for the :org customer portal',
        'mail_heading' => 'Reset password',
        'mail_intro' => 'A password reset was requested for your access to the :org customer portal. Use the following link to set a new password.',
        'mail_validity' => 'The link can be used once and is valid for :minutes minutes.',
        'mail_ignore' => 'If you did not request this, please ignore this email — your current password remains valid.',
        'sessions_hint' => 'Setting a new password signs out all existing sessions of this access. A second factor you have set up remains in place.',
    ],
    'reset' => [
        'action' => 'Reset access',
        'confirm_title' => 'Reset portal access',
        'confirm_message' => 'The current password stops working immediately, all sessions are ended, and :email receives a new invitation. Two-factor methods that have been set up remain in place.',
        'confirm_label' => 'Reset',
        'flash' => 'Access reset — new invitation sent to :email.',
        'mail_heading' => 'Your customer portal access has been reset',
        'mail_intro' => ':org has reset your access to the customer portal. Your previous password is no longer valid. Use the following link to set a new password and then sign in.',
    ],
    'second_factor' => [
        'action' => 'Reset second factor',
        'confirm_title' => 'Reset second factor',
        'confirm_message' => 'All two-factor methods of :email (app, passkeys, recovery codes) are removed and all sessions end. Only use this if the customer no longer has any factor and you have verified their identity.',
        'confirm_label' => 'Reset',
        'flash' => 'Second factor of :email reset — the customer has been informed.',
        'mail_subject' => 'Two-factor sign-in for the :org customer portal reset',
        'mail_heading' => 'Your second factor has been reset',
        'mail_intro' => ':org has removed the two-factor methods of your access to the customer portal. Sign in with your password; if :org requires two-factor sign-in, you will set it up again at that point.',
        'mail_unexpected' => 'If you did not request this, please contact your contact person at :org immediately.',
    ],
    'invoices' => [
        'pdf_label' => 'Download invoice :number as PDF',
    ],
];
