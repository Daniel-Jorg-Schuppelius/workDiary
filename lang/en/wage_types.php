<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : wage_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => "Wage types & export delivery",
        'index_subtitle' => "Map internal wage types to the wage type numbers of the target payroll program and configure automatic delivery per export profile.",
        'mappings_help' => "How does the wage type mapping work?",
        'mappings_help_text' => "During the time export, each line's wage type is resolved first via this mapping, then via the wage type of the surcharge rule; regular hours without a mapping keep the profile's default wage type. If a surcharge or absence line has no assignment at all, the export aborts with an error message instead of producing a faulty file.",
        'create' => "Create wage type mapping",
        'edit' => "Edit wage type mapping",
        'empty' => "No wage type mappings yet — the profiles' default wage types remain in effect.",
        'delivery' => "Automatic delivery",
        'delivery_help_text' => "Finished exports are delivered automatically per profile via e-mail and/or SFTP to the payroll office; the evidence (when/where) is stored on the export.",
        'delivery_edit' => "Configure delivery — :profile",
    ],

    'field' => [
        'basics' => "Mapping",
        'profile' => "Export profile",
        'wage_type' => "Internal wage type",
        'wage_type_help' => "Standard wage types of the time export plus your organization's surcharge types.",
        'external_code' => "Target wage type (external)",
        'external_code_help' => "Wage type number in the target payroll program — numeric with up to 4 digits for DATEV/Lexware.",
        'standard_types' => "Standard wage types",
        'surcharge_types' => "Surcharge types (organization)",
        'choose' => "– please choose –",
        'mail' => "E-mail delivery",
        'mail_toggle' => "Send export file by e-mail after completion",
        'mail_recipients' => "Recipients",
        'mail_recipients_help' => "Separate multiple addresses with comma, semicolon or line break.",
        'sftp' => "SFTP upload",
        'sftp_toggle' => "Upload export file via SFTP after completion",
        'sftp_host' => "Host",
        'sftp_port' => "Port",
        'sftp_username' => "Username",
        'sftp_password' => "Password",
        'sftp_password_help' => "Leave empty to keep the stored password.",
        'sftp_root' => "Target directory",
        'sftp_root_help' => "Empty = home directory of the SFTP user.",
        'enabled' => "Active",
        'disabled' => "Off",
    ],

    'action' => [
        'create' => "Create",
        'edit' => "Edit",
        'save' => "Save",
        'delete' => "Delete",
        'delete_confirm' => "Really delete this wage type mapping? Existing exports remain unchanged; future exports fall back to the default wage type.",
        'configure' => "Configure",
    ],

    'flash' => [
        'created' => "Wage type mapping created.",
        'updated' => "Wage type mapping updated.",
        'deleted' => "Wage type mapping deleted.",
        'delivery_saved' => "Delivery configuration saved.",
    ],

    'validation' => [
        'external_code_format' => "The target wage type has an invalid format for the selected export profile (DATEV/Lexware: numeric, 1–4 digits).",
        'wage_type_unique' => "A mapping for this wage type already exists in this profile.",
        'recipients_required' => "E-mail delivery requires at least one recipient address.",
        'password_required' => "The SFTP upload requires a password.",
    ],

    'error' => [
        'missing_mappings' => "Export aborted: the following wage types have no target wage type in the payroll program: :types. Please maintain a mapping under “Wage types & export delivery” or set the wage type on the surcharge rule.",
    ],

    'delivery' => [
        'title_evidence' => "Automatic delivery",
        'evidence_mail' => "E-mail to :to",
        'evidence_sftp' => "SFTP to :target",
        'note_auto' => "Delivered automatically (:channels).",
        'file_missing' => "Export file not found — delivery skipped.",
        'abandoned' => "Automatic delivery finally failed after multiple attempts.",
    ],

    'mail' => [
        'subject' => "Time export :profile :period",
        'heading' => "Time export for payroll",
        'body' => "Attached you will find the time export of profile :profile for period :period.",
        'meta' => ":rows lines · SHA-256 :hash",
    ],
];
