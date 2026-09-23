<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveReportTargetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetPeriod, ReportTargetScope};
use App\Models\{Customer, Project, User};
use App\Services\SqidEncoder;
use Illuminate\Validation\Rule;

class SaveReportTargetRequest extends BaseFormRequest {
    protected function prepareForValidation(): void {
        $scope = $this->input('scope');

        // Bei org-Scope darf kein scope_id gesetzt sein.
        if ($scope === ReportTargetScope::Org->value || $scope === null || $scope === '') {
            $this->merge(['scope_id' => null]);
        } else {
            $raw = $this->input('scope_id');
            if (is_string($raw) && $raw !== '' && ! is_numeric($raw)) {
                /** @var SqidEncoder $encoder */
                $encoder = app(SqidEncoder::class);
                $modelClass = match ($scope) {
                    ReportTargetScope::Customer->value => Customer::class,
                    ReportTargetScope::Project->value => Project::class,
                    ReportTargetScope::User->value => User::class,
                    default => null,
                };
                if ($modelClass !== null) {
                    $this->merge(['scope_id' => $encoder->decode($modelClass, $raw)]);
                }
            } elseif ($raw === '') {
                $this->merge(['scope_id' => null]);
            }
        }

        foreach (['period', 'valid_from', 'valid_until', 'note'] as $optional) {
            if ($this->input($optional) === '') {
                $this->merge([$optional => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'metric' => ['required', Rule::enum(ReportTargetMetric::class)],
            'scope' => ['required', Rule::enum(ReportTargetScope::class)],
            'scope_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn(): bool => $this->input('scope') !== ReportTargetScope::Org->value),
            ],
            'target_value' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'period' => ['nullable', Rule::enum(ReportTargetPeriod::class)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
