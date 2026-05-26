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
        'created' => 'Procedure template created.',
        'updated' => 'Procedure template updated.',
        'published' => 'Template version :version published.',
        'versionInitial' => 'Initial version',
        'versionCreated' => 'New template version :version created.',
        'stepAdded' => 'Step added.',
        'stepUpdated' => 'Step updated.',
        'stepRemoved' => 'Step removed.',
        'runStarted' => 'Procedure run started.',
        'runCompleted' => 'Procedure run completed.',
        'runAborted' => 'Procedure run aborted.',
        'stepCompleted' => 'Step completed.',
        'backupRegistered' => 'Backup record registered.',
        'backupVerified' => 'Backup record verified.',
        'backupRejected' => 'Backup record rejected.',
        'secondPersonRequested' => 'Second person has been requested.',
        'secondPersonAssigned' => 'Second person took over the approval.',
        'secondPersonSigned' => 'Second person countersigned the approval.',
        'secondPersonRevoked' => 'Four-eyes approval revoked.',
        'deviationRecorded' => 'Deviation recorded.',
        'deviationUpdated' => 'Deviation updated.',
        'deviationActionTriggered' => 'Follow-up action for deviation triggered.',
        'criticalRiskAccepted' => 'Critical deviation has been accepted.',
    ],
    'validation' => [
        'versionLocked' => 'Published template versions are immutable. Please create a new version.',
        'runIncomplete' => 'Run cannot be completed: there are still mandatory steps open.',
        'backupInvalid' => 'Backup record is invalid (reason: :reason).',
        'backupMissingOrExpired' => 'A valid backup record is missing (or the last backup is too old).',
        'secondPersonMissing' => 'Four-eyes approval is missing or incomplete.',
        'secondPersonSelfNotAllowed' => 'The executing person must not take over the approval themselves.',
        'deviationReasonTooShort' => 'Deviation reason is too short (at least 20 characters).',
        'deviationInvalid' => 'Deviation could not be recorded (reason: :reason).',
        'criticalDeviationOpen' => 'Run cannot be completed: there is a critical deviation without risk acceptance.',
    ],
    'blocked' => [
        'previousStepIncomplete' => 'Previous mandatory step is not yet completed.',
        'runNotActive' => 'The run is not active.',
        'stepAlreadyFinal' => 'Step is already completed.',
        'missingQualification' => 'Required qualification is missing.',
        'missingRole' => 'Required role is missing.',
        'secondPersonRequired' => 'Second person is required.',
        'backupNotVerified' => 'Backup record is missing or not verified.',
        'backupMissingOrExpired' => 'Last backup is missing or outside the valid time window.',
    ],
];
