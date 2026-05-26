<?php

declare(strict_types=1);

return [
    // generische Modell-Events
    'created'                       => 'Angelegt',
    'updated'                       => 'Geändert',
    'deleted'                       => 'Gelöscht',
    'archived'                      => 'Archiviert',
    'restored'                      => 'Wiederhergestellt',

    // Authentifizierung
    'auth' => [
        'login'                     => 'Anmeldung',
        'logout'                    => 'Abmeldung',
        'failed'                    => 'Fehlgeschlagene Anmeldung',
        'password_reset'            => 'Passwort zurückgesetzt',
    ],

    // Onboarding
    'onboarding' => [
        'completed'                 => 'Onboarding abgeschlossen',
        'stepCompleted'             => 'Onboarding-Schritt abgeschlossen',
        'stepSkipped'               => 'Onboarding-Schritt übersprungen',
        'widgetDismissed'           => 'Onboarding-Widget ausgeblendet',
    ],

    // Diagnostics / Backup
    'backup' => [
        'completed'                 => 'Backup abgeschlossen',
    ],
    'diagnostics' => [
        'viewed'                    => 'Diagnose aufgerufen',
        'testTriggered'             => 'Diagnose-Test ausgelöst',
    ],

    // Rollen & Access
    'role' => [
        'created'                   => 'Rolle angelegt',
        'updated'                   => 'Rolle geändert',
        'deleted'                   => 'Rolle gelöscht',
    ],
    'user_group' => [
        'member_added'              => 'Benutzergruppe: Mitglied hinzugefügt',
        'member_removed'            => 'Benutzergruppe: Mitglied entfernt',
    ],

    // Support / Reports
    'support' => [
        'test'                      => 'Support-Test',
        'reportGenerated'           => 'Support-Report erzeugt',
        'reportDownloaded'          => 'Support-Report heruntergeladen',
    ],
    'report' => [
        'exported'                  => 'Report exportiert',
    ],

    // Lizenz / Limits
    'limit' => [
        'exceeded'                  => 'Limit überschritten',
    ],
    'license' => [
        'installed'                 => 'Lizenz installiert',
    ],

    // Assets
    'asset' => [
        'created'                   => 'Asset angelegt',
    ],

    // Protokolle
    'protocol' => [
        'signatureRequested'        => 'Signatur angefordert',
        'signatureLinkOpened'       => 'Signatur-Link geöffnet',
    ],

    // Sicherheit / Sessions
    'session' => [
        'revoked'                   => 'Session widerrufen',
    ],
    'token' => [
        'revoked'                   => 'Token widerrufen',
    ],

    // Mandant / Export
    'tenant' => [
        'export' => [
            'requested'             => 'Mandanten-Export angefordert',
        ],
    ],

    // Branch-Profile
    'branch_profile' => [
        'installed'                 => 'Branch-Profil installiert',
    ],

    // Demo
    'demo' => [
        'reset'                     => 'Demo-Mandant zurückgesetzt',
        'seeded'                    => 'Demo-Daten erzeugt',
    ],
];
