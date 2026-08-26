<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dsar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'portal' => [
        'title' => 'Data protection request',
        'subtitle' => 'Your rights as a data subject',
        'footer' => 'This page is solely for exercising your data subject rights. Please do not send payment or login details here.',
    ],

    'landing' => [
        'title' => 'Submit a data protection request',
        'intro' => 'Data subjects can use this procedure to exercise their rights under the General Data Protection Regulation.',
        'no_link' => 'To submit a request you need the link of the controller. Please contact the organisation whose data concerns you.',
        'rights' => 'Available request types',
    ],

    'legal_note' => 'This information is guidance, not legal advice. The statutory text prevails.',
    'privacy_notice' => 'Your details are used solely to process this request, are stored encrypted and are deleted once the retention period expires. The legal basis is Art. 6(1)(c) GDPR in conjunction with Art. 15 to 21 GDPR.',
    'identity_hint' => 'Before disclosing information the controller verifies your identity (Art. 12(6) GDPR) and may contact you separately for that purpose.',

    'form' => [
        'title' => 'Submit request',
        'what' => 'What is this for?',
        'what_text' => 'You may request access to the data stored about you, ask for it to be rectified or erased, have processing restricted, receive your data in a portable format, or object to the processing.',
        'submit' => 'Send request',
    ],

    'field' => [
        'type' => 'Type of request',
        'full_name' => 'First and last name',
        'email' => 'E-mail address for our reply',
        'reference' => 'Case, customer or personnel number (optional)',
        'message' => 'Your request',
        'attachments' => 'Attachments (optional)',
        'attachments_hint' => 'At most :max files, up to :size MB each.',
        'honeypot' => 'Please leave empty',
        'privacy_ack' => 'I have read the privacy notice and provide my details to the best of my knowledge.',
    ],

    'receipt' => [
        'title' => 'Request received',
        'headline' => 'Your request has been received.',
        'number' => 'Reference: :nr',
        'mail_sent' => 'A confirmation of receipt has been sent to the address you provided. The statutory processing period starts with today’s receipt.',
        'back' => 'Back to the form',
    ],

    'confirmed' => [
        'title' => 'Address confirmed',
        'headline' => 'Thank you — your e-mail address is confirmed.',
        'text' => 'The confirmation has been recorded for case :nr.',
        'no_deadline_effect' => 'The processing period still runs from the receipt of your request; the confirmation does not postpone it.',
    ],

    'mail' => [
        'subject' => 'Confirmation of receipt for your data protection request :nr',
        'headline' => 'Your data protection request has been received',
        'intro' => 'A data protection request was submitted with this e-mail address under reference :nr.',
        'deadline' => 'The statutory processing period runs from receipt and ends on :date.',
        'confirm_button' => 'Confirm e-mail address',
        'confirm_note' => 'The confirmation proves that this address is reachable. It does not replace the verification of your identity — the controller will contact you separately about that. The click has no effect on the deadline.',
        'not_you' => 'If you did not submit this request, please ignore this e-mail. No information is disclosed without identity verification.',
    ],

    'subject' => [
        'email' => 'E-mail: :value',
        'reference' => 'Reference: :value',
    ],

    'internal' => [
        'from_portal' => 'Portal intake',
        'portal_banner' => 'This request came in through the public data subject portal. The identity details are unverified self-declaration.',
        'contact_email' => 'Reply address',
        'email_confirmed' => 'confirmed on :date',
        'email_unconfirmed' => 'not confirmed',
        'identity_required' => 'Identity must be verified and confirmed before disclosing information (portal intake).',
    ],

    'admin' => [
        'nav' => 'Data subject portal',
        'title' => 'Manage data subject portal',
        'subtitle' => 'Configure the public form for data subject requests.',
        'link' => 'Public link',
        'link_hint' => 'Publish this link in your privacy notice. It cannot be derived from the organisation name.',
        'rotate' => 'Rotate link',
        'rotate_confirm' => 'Really rotate the link? Links already published become invalid.',
        'not_created' => 'No data subject portal has been created yet. Save to create one with a random link.',
        'settings' => 'Settings',
        'visibility' => 'Visibility',
        'is_enabled' => 'Portal active (publicly reachable)',
        'allow_attachments' => 'Allow attachments',
        'presentation' => 'Presentation',
        'intro_text' => 'Introductory text (optional)',
        'default_locale' => 'Default language (optional, e.g. en)',
        'saved' => 'Data subject portal saved.',
        'rotated' => 'The portal link has been rotated. Links already published are now invalid.',
    ],
];
