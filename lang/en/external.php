<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : external.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'party' => [
        'subcontractor' => 'Subcontractor',
        'inspector' => 'Inspector',
        'expert' => 'Expert',
        'other' => 'Other',
    ],
    'ability' => [
        'view' => 'View',
        'comment' => 'Comment',
        'upload' => 'Upload file',
        'confirm' => 'Confirm',
    ],
    'status' => [
        'invited' => 'Invited',
        'accessed' => 'Accessed',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
    ],
    'subject' => [
        'order' => 'Order',
        'generic' => 'Item',
    ],
    'panel' => [
        'title' => 'External participants',
        'invite' => 'Invite',
        'empty' => 'No external participants invited yet.',
        'link_once' => 'Copy this link once and send it to the external party — it will not be shown again.',
    ],
    'col' => [
        'name' => 'Name',
        'party' => 'Type',
        'abilities' => 'Permissions',
        'status' => 'Status',
        'expires' => 'Valid until',
    ],
    'group' => [
        'contact' => 'Contact',
        'abilities' => 'Allowed actions',
        'validity' => 'Validity',
    ],
    'field' => [
        'name' => 'Name',
        'email' => 'Email (optional)',
        'role' => 'Role',
        'party' => 'Type',
        'ttl_days' => 'Validity (days)',
    ],
    'hint' => [
        'role' => 'e.g. Electrician, TÜV inspector',
        'abilities' => 'Viewing is always allowed. Additional actions are strictly enforced server-side.',
        'ttl_days' => '1 to 180 days. The access expires automatically afterwards.',
    ],
    'invite' => [
        'title' => 'Invite external party',
        'eyebrow' => 'External participants',
        'submit' => 'Invite & generate link',
        'once_hint' => 'The access link is shown exactly ONCE after creation — only the hash is stored.',
    ],
    'revoke' => [
        'action' => 'Revoke',
        'title' => 'Revoke access',
        'message' => 'The external access will be blocked immediately. Continue?',
        'confirm' => 'Revoke',
    ],
    'flash' => [
        'invited' => 'External participant ":name" invited.',
        'revoked' => 'External access revoked.',
    ],
    'public' => [
        'title' => 'External access',
        'hello' => 'Hello :name',
        'expires_note' => 'This access is valid until :date.',
        'view_only' => 'This access is limited to viewing.',
        'comment_heading' => 'Leave a comment',
        'comment_placeholder' => 'Your note …',
        'comment_submit' => 'Send comment',
        'comment_saved' => 'Comment saved.',
        'upload_heading' => 'Upload file or photo',
        'upload_hint' => 'Allowed: JPG, PNG, GIF, WEBP, PDF (max. 25 MB).',
        'upload_submit' => 'Upload',
        'upload_saved' => 'File uploaded.',
        'upload_rejected' => 'File type not allowed.',
        'confirm_heading' => 'Confirm / Accept',
        'confirm_note_placeholder' => 'Optional note for the confirmation …',
        'confirm_accept' => 'I confirm that the information is correct.',
        'confirm_submit' => 'Confirm',
        'confirmed' => 'Confirmation saved.',
    ],
];
