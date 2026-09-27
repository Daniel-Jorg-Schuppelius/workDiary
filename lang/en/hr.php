<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Digital personnel file (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Personnel file',
        'title_mine' => 'My personnel file',
        'nav' => 'My personnel file',
        'subtitle' => 'Personnel file of :name — confidential, visible only to the personnel-file circle and the person concerned.',
        'subtitle_mine' => 'Your own personnel file (self-access, read-only).',
        'back' => 'Back to staff list',
        'empty' => 'No documents in the personnel file yet.',
        'confidential_fixed' => 'Personnel files are always confidential — the switch is omitted, the flag is enforced.',
        'retention_pending' => 'from exit',
        'confirm_delete' => 'Permanently destroy this document from the personnel file? Files and versions are deleted; the audit log remains.',
        'field' => [
            'title' => 'Title',
            'category' => 'Category',
            'validity' => 'Validity',
            'valid_from' => 'Valid from',
            'valid_until' => 'Valid until',
            'retention_until' => 'Retention until',
            'version' => 'Version',
            'updated_at' => 'Updated',
            'description' => 'Description',
            'file' => 'File',
            'version_note' => 'Version note',
            'documents' => 'Documents',
        ],
        'action' => [
            'upload' => 'Add document',
            'edit' => 'Edit',
            'save' => 'Save',
            'download' => 'Download',
            'versions' => 'Versions',
            'delete' => 'Destroy',
        ],
        'flash' => [
            'created' => 'Document was added to the personnel file.',
            'updated' => 'Personnel file document was updated.',
        ],
    ],
    // Personal-Kapazität (MVP-940).
    'capacity' => [
        'title' => 'Staff capacity',
        'button' => 'Capacity',
        'subtitle' => 'Planned demand (assigned orders) against the target hours of team members per week; public holidays and approved leave are deducted.',
        'team' => 'Team',
        'week' => 'Week from :date',
        'members' => ':count members',
        'empty' => 'No teams yet.',
        'hint' => 'Figures in hours: planned / available.',
        'open_requisitions' => 'Open positions in total: :count.',
    ],
    // Vertretungen beim Austritt (MVP-941).
    'offboarding' => [
        'deputies' => 'Reassign deputies',
        'deputies_hint' => 'These people have the leaving member as their deputy. Without a selection, the deputy role ends.',
        'deputy_for' => 'New deputy for :name',
        'no_deputy' => '— no deputy —',
    ],
    // Arbeitsvertrag zur Unterschrift (MVP-939).
    'employment' => [
        'title' => 'Employment contract for signature',
        'intro' => 'The contract goes to the person via link; the organisation then countersigns. The signed version is filed in the personnel file.',
        'send' => 'Send for signature',
        'default_title' => 'Employment contract :name',
        'default_declaration' => 'I have read the employment contract and agree to it.',
        'filed_note' => 'Signed version from contract :number.',
        'field' => [
            'title' => 'Title',
            'starts_on' => 'Start',
            'email' => 'Person\'s email',
            'declaration_text' => 'Declaration of consent',
            'file' => 'Contract (PDF)',
        ],
        'flash' => [
            'sent' => 'Employment contract sent to :email for signature.',
        ],
        'error' => [
            'email' => 'Please enter an email address.',
        ],
    ],
];
