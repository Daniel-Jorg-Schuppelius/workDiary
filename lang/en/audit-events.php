<?php

declare(strict_types=1);

return [
    // generic model events
    'created'                       => 'Created',
    'updated'                       => 'Updated',
    'deleted'                       => 'Deleted',
    'archived'                      => 'Archived',
    'restored'                      => 'Restored',

    // authentication
    'auth' => [
        'login'                     => 'Login',
        'logout'                    => 'Logout',
        'failed'                    => 'Failed login',
        'password_reset'            => 'Password reset',
    ],

    // onboarding
    'onboarding' => [
        'completed'                 => 'Onboarding completed',
        'stepCompleted'             => 'Onboarding step completed',
        'stepSkipped'               => 'Onboarding step skipped',
        'widgetDismissed'           => 'Onboarding widget dismissed',
    ],

    // diagnostics / backup
    'backup' => [
        'completed'                 => 'Backup completed',
    ],
    'diagnostics' => [
        'viewed'                    => 'Diagnostics viewed',
        'testTriggered'             => 'Diagnostics test triggered',
    ],

    // roles & access
    'role' => [
        'created'                   => 'Role created',
        'updated'                   => 'Role updated',
        'deleted'                   => 'Role deleted',
    ],
    'user_group' => [
        'member_added'              => 'User group: member added',
        'member_removed'            => 'User group: member removed',
    ],

    // support / reports
    'support' => [
        'test'                      => 'Support test',
        'reportGenerated'           => 'Support report generated',
        'reportDownloaded'          => 'Support report downloaded',
    ],
    'report' => [
        'exported'                  => 'Report exported',
    ],

    // license / limits
    'limit' => [
        'exceeded'                  => 'Limit exceeded',
    ],
    'license' => [
        'installed'                 => 'License installed',
    ],

    // assets
    'asset' => [
        'created'                   => 'Asset created',
    ],

    // protocols
    'protocol' => [
        'signatureRequested'        => 'Signature requested',
        'signatureLinkOpened'       => 'Signature link opened',
    ],

    // security / sessions
    'session' => [
        'revoked'                   => 'Session revoked',
    ],
    'token' => [
        'revoked'                   => 'Token revoked',
    ],

    // tenant / export
    'tenant' => [
        'export' => [
            'requested'             => 'Tenant export requested',
        ],
    ],

    // branch profile
    'branch_profile' => [
        'installed'                 => 'Branch profile installed',
    ],

    // demo
    'demo' => [
        'reset'                     => 'Demo tenant reset',
        'seeded'                    => 'Demo data seeded',
    ],
];
