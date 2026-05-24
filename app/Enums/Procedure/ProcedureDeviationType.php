<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

enum ProcedureDeviationType: string {
    case NotApplicable = 'not_applicable';
    case NotPossible = 'not_possible';
    case Partial = 'partial';
    case AlternativeMethod = 'alternative_method';
    case FailedCheck = 'failed_check';
    case MaterialSubstitute = 'material_substitute';
    case SafetyBlock = 'safety_block';
    case CustomerDecline = 'customer_decline';

    public function defaultSeverity(): ProcedureDeviationSeverity {
        return match ($this) {
            self::NotApplicable, self::AlternativeMethod => ProcedureDeviationSeverity::Low,
            self::Partial, self::MaterialSubstitute, self::CustomerDecline => ProcedureDeviationSeverity::Medium,
            self::NotPossible, self::FailedCheck => ProcedureDeviationSeverity::High,
            self::SafetyBlock => ProcedureDeviationSeverity::Critical,
        };
    }
}
