<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunEventType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

enum ProcedureRunEventType: string {
    case RunStarted = 'procedure.runStarted';
    case StepCompleted = 'procedure.stepCompleted';
    case StepFailed = 'procedure.stepFailed';
    case StepDeviated = 'procedure.stepDeviated';
    case StepNA = 'procedure.stepNA';
    case StepUnlocked = 'procedure.stepUnlocked';
    case StepBlocked = 'procedure.stepBlocked';
    case RunCompleted = 'procedure.runCompleted';
    case RunCompletionRejected = 'procedure.runCompletionRejected';
    case RunAborted = 'procedure.runAborted';
    case SecondPersonAssigned = 'procedure.secondPersonAssigned';
    case SecondPersonSigned = 'procedure.secondPersonSigned';
    case SecondPersonRequested = 'procedure.secondPersonRequested';
    case SecondPersonRevoked = 'procedure.secondPersonRevoked';
    case BackupRegistered = 'procedure.backupRegistered';
    case BackupVerified = 'procedure.backupVerified';
    case BackupRejected = 'procedure.backupRejected';
}
