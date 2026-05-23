<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : procedure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'flash' => [
        'created' => 'Prozedurvorlage angelegt.',
        'updated' => 'Prozedurvorlage aktualisiert.',
        'published' => 'Vorlagenversion :version veröffentlicht.',
        'versionInitial' => 'Initiale Version',
        'versionCreated' => 'Neue Vorlagenversion :version angelegt.',
        'stepAdded' => 'Schritt hinzugefügt.',
        'stepUpdated' => 'Schritt aktualisiert.',
        'stepRemoved' => 'Schritt entfernt.',
        'runStarted' => 'Prozedurlauf gestartet.',
        'runCompleted' => 'Prozedurlauf abgeschlossen.',
        'runAborted' => 'Prozedurlauf abgebrochen.',
        'stepCompleted' => 'Schritt erledigt.',
        'backupRegistered' => 'Backup-Nachweis erfasst.',
        'backupVerified' => 'Backup-Nachweis verifiziert.',
        'backupRejected' => 'Backup-Nachweis abgelehnt.',
        'secondPersonRequested' => 'Zweite Person wurde angefordert.',
        'secondPersonAssigned' => 'Zweite Person hat die Freigabe übernommen.',
        'secondPersonSigned' => 'Zweite Person hat die Freigabe gegengezeichnet.',
        'secondPersonRevoked' => 'Vier-Augen-Freigabe widerrufen.',
    ],
    'validation' => [
        'versionLocked' => 'Veröffentlichte Vorlagenversionen sind unveränderlich. Bitte eine neue Version anlegen.',
        'runIncomplete' => 'Lauf kann nicht abgeschlossen werden: Es sind noch Pflichtschritte offen.',
        'backupInvalid' => 'Backup-Nachweis ist ungültig (Grund: :reason).',
        'backupMissingOrExpired' => 'Es fehlt ein gültiger Backup-Nachweis (oder das letzte Backup ist zu alt).',
        'secondPersonMissing' => 'Vier-Augen-Freigabe fehlt oder ist unvollständig.',
        'secondPersonSelfNotAllowed' => 'Die ausführende Person darf die Freigabe nicht selbst übernehmen.',
    ],
    'blocked' => [
        'previousStepIncomplete' => 'Vorheriger Pflichtschritt ist noch nicht abgeschlossen.',
        'runNotActive' => 'Der Lauf ist nicht aktiv.',
        'stepAlreadyFinal' => 'Schritt ist bereits abgeschlossen.',
        'missingQualification' => 'Erforderliche Qualifikation fehlt.',
        'missingRole' => 'Erforderliche Rolle fehlt.',
        'secondPersonRequired' => 'Zweite Person ist erforderlich.',
        'backupNotVerified' => 'Backup-Nachweis fehlt oder ist nicht verifiziert.',
        'backupMissingOrExpired' => 'Letztes Backup fehlt oder ist außerhalb des gültigen Zeitfensters.',
    ],
];
