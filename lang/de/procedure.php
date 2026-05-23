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
    ],
    'validation' => [
        'versionLocked' => 'Veröffentlichte Vorlagenversionen sind unveränderlich. Bitte eine neue Version anlegen.',
        'runIncomplete' => 'Lauf kann nicht abgeschlossen werden: Es sind noch Pflichtschritte offen.',
    ],
    'blocked' => [
        'previousStepIncomplete' => 'Vorheriger Pflichtschritt ist noch nicht abgeschlossen.',
        'runNotActive' => 'Der Lauf ist nicht aktiv.',
        'stepAlreadyFinal' => 'Schritt ist bereits abgeschlossen.',
        'missingQualification' => 'Erforderliche Qualifikation fehlt.',
        'missingRole' => 'Erforderliche Rolle fehlt.',
        'secondPersonRequired' => 'Zweite Person ist erforderlich.',
    ],
];
