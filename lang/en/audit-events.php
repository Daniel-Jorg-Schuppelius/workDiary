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

    // import
    'import' => [
        'confirmed'                 => 'Import confirmed',
        'started'                   => 'Import started',
        'finished'                  => 'Import finished',
        'partial'                   => 'Import partially finished',
        'preflightFailed'           => 'Import preflight failed',
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
    // role/permission assignment to users/groups (Bauturbo A17, MVP-335)
    'user' => [
        'role' => [
            'assigned'              => 'Role assigned',
            'revoked'               => 'Role revoked',
        ],
        'permission' => [
            'granted'               => 'Permission granted',
            'revoked'               => 'Permission revoked',
        ],
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

    // privacy page (MVP-005/MVP-327)
    // ArbZG-Compliance-Verstöße (Feature 006, Welle D)
    'compliance' => [
        'finding' => [
            'detected' => 'Violation detected',
            'acknowledged' => 'Violation acknowledged',
            'accepted' => 'Violation accepted',
            'resolved' => 'Violation resolved',
            'reopened' => 'Violation recurred',
        ],
    ],
    'privacy' => [
        'overviewExported'          => 'Privacy overview exported',
        'report' => [
            'exported'              => 'Privacy report exported',
        ],
    ],
    'integration' => [
        'changed'                   => 'Integration enabled/disabled',
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
    // daily close (MVP-015)
    'dayClose' => [
        'opened'                    => 'Daily close opened',
        'entrySaved'                => 'Daily close saved',
        'closed'                    => 'Day closed',
        'correctionRequested'       => 'Day correction requested',
        'correctionApproved'        => 'Day correction approved',
        'correctionRejected'        => 'Day correction rejected',
        'reopened'                  => 'Day reopened',
    ],
    // Time entries (MVP-508)
    'timeEntry' => [
        'reassigned'                => 'Time entry reassigned to another user',
    ],
    // Customer portal access (MVP-510)
    'portal' => [
        'query' => [
            'withdrawn' => 'Portal query withdrawn',
        ],
        'visibility' => [
            'updated' => 'Portal visibility changed',
        ],
        'access' => [
            'invited'          => 'Portal access invited',
            'invite_resent'    => 'Portal invitation re-sent',
            'invite_accepted'  => 'Portal invitation accepted',
            'deactivated'      => 'Portal access deactivated',
            'reactivated'      => 'Portal access reactivated',
        ],
    ],
];
