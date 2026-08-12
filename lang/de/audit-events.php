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

    // Import
    'import' => [
        'confirmed'                 => 'Import bestätigt',
        'started'                   => 'Import gestartet',
        'finished'                  => 'Import abgeschlossen',
        'partial'                   => 'Import teilweise abgeschlossen',
        'preflightFailed'           => 'Import-Vorprüfung fehlgeschlagen',
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
    // Rollen-/Permission-Vergabe an User/Gruppen (Bauturbo A17, MVP-335)
    'user' => [
        'role' => [
            'assigned'              => 'Rolle zugewiesen',
            'revoked'               => 'Rolle entzogen',
        ],
        'permission' => [
            'granted'               => 'Permission vergeben',
            'revoked'               => 'Permission entzogen',
        ],
    ],

    // Support / Reports
    'support' => [
        'test'                      => 'Support-Test',
        'reportGenerated'           => 'Support-Report erzeugt',
        'reportDownloaded'          => 'Support-Report heruntergeladen',
    ],
    'report' => [
        'exported'                  => 'Report exportiert',
        'presenceEmergencyViewed'   => 'Notfall-Anwesenheitsliste abgerufen',
    ],
    'rules' => [
        'recalculated'              => 'Zeitregel-Ergebnisse neu berechnet',
    ],
    'timeDimension' => [
        'type_created'              => 'Zeit-Dimensionstyp angelegt',
        'type_toggled'              => 'Zeit-Dimensionstyp umgeschaltet',
        'value_created'             => 'Zeit-Dimensionswert angelegt',
        'value_deleted'             => 'Zeit-Dimensionswert gelöscht',
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

    // Datenschutzseite (MVP-005/MVP-327)
    // ArbZG-Compliance-Verstöße (Feature 006, Welle D)
    'compliance' => [
        'finding' => [
            'detected' => 'Verstoß erkannt',
            'acknowledged' => 'Verstoß quittiert',
            'accepted' => 'Verstoß akzeptiert',
            'resolved' => 'Verstoß behoben',
            'reopened' => 'Verstoß erneut aufgetreten',
        ],
    ],
    'privacy' => [
        'overviewExported'          => 'Datenschutz-Übersicht exportiert',
        'report' => [
            'exported'              => 'Datenschutzbericht exportiert',
        ],
    ],
    'integration' => [
        'changed'                   => 'Integration aktiviert/deaktiviert',
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
    // Tagesabschluss (MVP-015)
    'dayClose' => [
        'opened'                    => 'Tagesabschluss geöffnet',
        'entrySaved'                => 'Tagesabschluss gespeichert',
        'closed'                    => 'Tag abgeschlossen',
        'correctionRequested'       => 'Tages-Korrektur beantragt',
        'correctionApproved'        => 'Tages-Korrektur freigegeben',
        'correctionRejected'        => 'Tages-Korrektur abgelehnt',
        'reopened'                  => 'Tag wieder geöffnet',
    ],
    // Zeiteinträge (MVP-508)
    'timeEntry' => [
        'reassigned'                => 'Zeiteintrag anderem Benutzer zugeordnet',
    ],
    // Kundenportal-Zugänge (MVP-510)
    'portal' => [
        'query' => [
            'withdrawn' => 'Portal-Rückfrage zurückgezogen',
        ],
        'visibility' => [
            'updated' => 'Portal-Sichtbarkeit geändert',
        ],
        'access' => [
            'invited'          => 'Portalzugang eingeladen',
            'invite_resent'    => 'Portal-Einladung erneut versendet',
            'invite_accepted'  => 'Portal-Einladung angenommen',
            'deactivated'      => 'Portalzugang deaktiviert',
            'reactivated'      => 'Portalzugang reaktiviert',
        ],
    ],
];
