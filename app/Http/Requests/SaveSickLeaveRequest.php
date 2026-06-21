<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSickLeaveRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Sickness\SickLeaveKind;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\SickLeave;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

class SaveSickLeaveRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => \App\Models\User::class,
        'follow_up_for_id' => \App\Models\SickLeave::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        $threshold = (int) config('sickness.attachment_required_from_day', 4);
        $maxKb = (int) config('sickness.attachments.max_kilobytes', 10240);
        /** @var list<string> $mimes */
        $mimes = (array) config('sickness.attachments.mimes', ['pdf', 'jpg', 'jpeg', 'png', 'heic']);

        /** @var SickLeave|null $route */
        $route = $this->route('sick_leave');
        $hasExisting = $route !== null && $route->attachments()->exists();

        return [
            'user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'gte:start_date'],
            'kind' => ['required', Rule::enum(SickLeaveKind::class)],
            'follow_up_for_id' => [
                'nullable',
                'integer',
                Rule::exists('sick_leaves', 'id'),
                Rule::requiredIf(fn() => $this->input('kind') === SickLeaveKind::FollowUp->value),
            ],
            'au_number' => ['nullable', 'string', 'max:100'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'kasse_notified' => ['nullable', 'boolean'],
            'au_file' => [
                Rule::requiredIf(fn() => ! $hasExisting && $this->calendarDays() >= $threshold),
                'nullable',
                'file',
                'max:' . $maxKb,
                'mimes:' . implode(',', $mimes),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        $threshold = (int) config('sickness.attachment_required_from_day', 4);

        return [
            'au_file.required' => __('Ab dem :n. Krankheitstag ist eine AU-Bescheinigung erforderlich.', ['n' => $threshold]),
            'follow_up_for_id.required' => __('Bitte die vorausgehende Krankmeldung auswählen.'),
            'end_date.gte' => __('Das Enddatum darf nicht vor dem Startdatum liegen.'),
        ];
    }

    public function calendarDays(): int {
        try {
            $start = CarbonImmutable::parse((string) $this->input('start_date'));
            $end = CarbonImmutable::parse((string) $this->input('end_date'));
        } catch (\Throwable) {
            return 0;
        }

        if ($end->lessThan($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }
}
