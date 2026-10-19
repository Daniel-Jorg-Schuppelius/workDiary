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
        'document_unavailable' => 'The document is not yet available in the accounting system. Please try again later.',
    ],

    // Preview as customer (Phase 153, MVP-1229–1231).
    'preview' => [
        'action' => 'View portal',
        'action_as' => 'View as this person',
        'no_account' => 'Preview available once an access exists',
        'running' => 'Preview running as :name until :until.',
        'stop' => 'End preview',
        'banner' => 'Preview: you are viewing the portal as :name (:customer). Actions are locked.',
        'banner_until' => 'Ends at :time.',
        'title_prefix' => 'Preview',
        'blocked' => 'Actions are locked during the preview.',
        'blocked_page' => 'This page is not available during the preview.',
        'nested_support' => 'A preview is not possible during a support session.',
        'deactivated' => 'There is no preview for a deactivated access.',
        'ended_manual' => 'Preview ended.',
        'ended_replaced' => 'The previous preview was ended.',
        'ended_expired' => 'The preview expired after :minutes minutes.',
        'ended_actor_changed' => 'The preview was ended because the login changed.',
        'ended_permission_lost' => 'The preview was ended because the permission is missing.',
        'ended_organization_changed' => 'The preview was ended because the organization changed.',
        'ended_account_changed' => 'The preview was ended because the portal account changed.',
        'ended_account_deactivated' => 'The preview was ended because the portal account is no longer available.',
    ],
    // Access with start password (MVP-1232).
    'direct' => [
        'action' => 'Create access',
        'title' => 'Create portal access with start password',
        'intro' => 'You receive a random start password that is shown only once. Hand it over in person or by phone, never by email. At the first login the person sets their own password.',
        'submit' => 'Create access',
        'flash' => 'Access for :email created.',
        'password_heading' => 'Start password for :email',
        'password_hint' => 'Shown only once. Hand it over in person or by phone, never by email. At the first login a personal password must be set.',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'state_provisional' => 'Start password pending',
        'reissue_action' => 'Generate start password',
        'reissue_confirm_title' => 'Generate new start password',
        'reissue_confirm_message' => 'The current password of :email stops working immediately, an open invitation expires, all sessions are ended. The new start password is shown once; at the next login a personal password must be set. Two-factor methods remain.',
        'reissue_confirm_label' => 'Generate',
        'reissue_flash' => 'New start password for :email generated.',
        'reauth_hint' => 'Creating an access with a start password requires a fresh login. Please confirm your password first and open the dialog again afterwards.',
        'reauth_action' => 'Confirm password',
    ],
    // Change password in the portal (MVP-1233).
    'password_change' => [
        'title' => 'Change password',
        'forced_hint' => 'You received a start password. Please set your own password now before using the portal.',
        'intro' => 'Enter your current password and a new password. Logins of this access on other devices will be ended.',
        'current' => 'Current password',
        'new' => 'New password',
        'confirm' => 'Repeat new password',
        'submit' => 'Save password',
        'flash' => 'Your password has been changed.',
        'profile_heading' => 'Password',
        'profile_intro' => 'Change your password if you suspect someone knows it or if you received a start password.',
        'profile_link' => 'Change password',
    ],
];
