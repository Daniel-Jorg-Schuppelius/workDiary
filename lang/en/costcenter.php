<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : costcenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => "Cost center rules",
        'rules_subtitle' => "Cost centers for the reviewed time export: per user, per team, or as the organisation default.",
        'rules_help' => "How do cost center rules work?",
        'rules_help_text' => "During the time export each line receives the employee's cost center: a user rule wins first, then the team rule with the highest priority, finally the organisation default. The export review UI allows overriding the cost center per line.",
        'create_rule' => "Create cost center rule",
        'edit_rule' => "Edit cost center rule",
        'empty' => "No cost center rules yet",
    ],

    'field' => [
        'basics' => "Rule",
        'source' => "Source",
        'source_help' => "User rules win over team rules; without a match the organisation default applies.",
        'source_default' => "Organisation default",
        'source_user' => "User",
        'source_team' => "Team",
        'user' => "User",
        'team' => "Team",
        'choose' => "– please choose –",
        'cost_center' => "Cost center",
        'cost_center_master' => "Cost center from master data",
        'cost_center_master_free' => "– enter manually –",
        'cost_center_master_help' => "Selecting takes the master-data code; without a selection the manually entered code applies.",
        'priority' => "Priority",
        'priority_help' => "Tie-breaker between several team rules: higher priority wins.",
    ],

    'action' => [
        'create' => "Create",
        'edit' => "Edit",
        'save' => "Save",
        'delete' => "Delete",
        'delete_confirm' => "Really delete this cost center rule? Existing exports remain unchanged.",
    ],

    'flash' => [
        'created' => "Cost center rule created.",
        'updated' => "Cost center rule updated.",
        'deleted' => "Cost center rule deleted.",
    ],
];
