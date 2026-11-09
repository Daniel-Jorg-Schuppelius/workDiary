<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksShiftCompliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Platform\Organization;
use App\Models\Schedule\ScheduledShift;
use App\Services\Compliance\{ComplianceReport, ShiftComplianceService};
use App\Services\Schedule\QualificationEnforcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Stellt eine `withValidator()`-Hook bereit, die eine geplante Schicht
 * gegen die Compliance-Regeln der aktuellen Organisation prüft.
 *
 * - mode=block → fügt Validierungsfehler hinzu (außer override_compliance=1).
 * - mode=warn  → speichert den Report als Request-Attribut.
 * - mode=off   → no-op (Service liefert leeren Report).
 *
 * @mixin FormRequest
 */
trait ChecksShiftCompliance {
    private ?ComplianceReport $complianceReport = null;

    protected function attachComplianceCheck(Validator $validator, ?ScheduledShift $existing = null): void {
        $validator->after(function (Validator $v) use ($existing): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<string, mixed> $data */
            $data = $v->validated();

            $shift = $existing ?? new ScheduledShift;
            $shift->fill(array_intersect_key($data, array_flip([
                'user_id',
                'shift_type_id',
                'date',
                'start_time',
                'end_time',
                'duty_plan_id',
                'status',
            ])));

            $user = $this->user();
            $orgId = $existing && $existing->organization_id
                ? (int) $existing->organization_id
                : ($user && isset($user->organization_id) ? (int) $user->organization_id : null);

            if ($orgId !== null && empty($shift->organization_id)) {
                $shift->organization_id = $orgId;
            }
            if ($existing && empty($shift->duty_plan_id) && $existing->duty_plan_id) {
                $shift->duty_plan_id = $existing->duty_plan_id;
            }

            /** @var Organization|null $org */
            $org = $orgId ? Organization::query()->find($orgId) : null;
            $report = app(ShiftComplianceService::class)->check($shift, $org);

            $this->complianceReport = $report;

            $mode = ($org?->complianceSettings() ?? Organization::COMPLIANCE_DEFAULTS)['mode'];

            if (
                $mode === Organization::COMPLIANCE_BLOCK
                && $report->hasErrors()
                && ! $this->boolean('override_compliance')
            ) {
                foreach ($report->violations as $vio) {
                    $v->errors()->add('compliance', $vio->message);
                }
            }

            // Qualifikationssperre (MVP-1514) nur bei neuer Zuweisung; die
            // Compliance-Übersteuerung hebt sie bewusst nicht auf.
            if ($existing === null || $shift->isDirty(['user_id', 'shift_type_id', 'date'])) {
                $candidate = (new ScheduledShift)->forceFill([
                    'organization_id' => $shift->organization_id,
                    'user_id' => $shift->user_id,
                    'shift_type_id' => $shift->shift_type_id,
                    'date' => $shift->date,
                ]);
                $message = app(QualificationEnforcement::class)->shiftBlockMessage($candidate);
                if ($message !== null) {
                    $v->errors()->add('user_id', $message);
                }
            }
        });
    }

    public function complianceReport(): ?ComplianceReport {
        return $this->complianceReport;
    }
}
