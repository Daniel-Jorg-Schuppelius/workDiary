<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTravelLogRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Travel\TravelLogVehicle;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\{Rule, Validator};

class SaveTravelLogRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'project_id' => \App\Models\Project::class,
        'task_id' => \App\Models\Task::class,
        'customer_id' => \App\Models\Customer::class,
        'attendance_id' => \App\Models\Attendance::class,
        'vehicle_id' => \App\Models\Vehicle::class,
    ];

    public function authorize(): bool {
        return true;
    }

    /**
     * Compose `started_at` / `ended_at` from the separately submitted
     * `date` + `start_time` / `end_time` time-only inputs. Day rolls over
     * automatically when `end_time` <= `start_time`.
     */
    protected function prepareForValidation(): void {
        $date = is_string($this->input('date')) ? trim($this->input('date')) : null;
        $startTime = is_string($this->input('start_time')) ? trim($this->input('start_time')) : null;
        $endTime = is_string($this->input('end_time')) ? trim($this->input('end_time')) : null;

        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        if (! $startTime || ! preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            return;
        }

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $startTime");
        } catch (\Throwable) {
            $start = null;
        }
        if (! $start instanceof CarbonImmutable) {
            return;
        }

        $merge = ['started_at' => $start->format('Y-m-d\TH:i')];

        if ($endTime && preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $endTime");
            } catch (\Throwable) {
                $end = null;
            }
            if ($end instanceof CarbonImmutable) {
                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }
                $merge['ended_at'] = $end->format('Y-m-d\TH:i');
            }
        }

        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'attendance_id' => ['nullable', 'integer', Rule::exists('attendances', 'id')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')],
            'from_address' => ['nullable', 'string', 'max:255'],
            'to_address' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['required', 'numeric', 'min:0', 'max:10000'],
            'vehicle' => ['required', Rule::enum(TravelLogVehicle::class)],
            'vehicle_label' => ['nullable', 'string', 'max:64'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'round_trip' => ['sometimes', 'boolean'],
            'reimbursable' => ['sometimes', 'boolean'],
            'rate_per_km' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function ($v): void {
            if ($this->filled('end_time') && ! $this->filled('start_time')) {
                $v->errors()->add('start_time', __('Startzeit erforderlich, wenn eine Endzeit angegeben ist.'));
            }
        });
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array {
        $data = parent::validated();
        $data['round_trip'] = (bool) ($data['round_trip'] ?? false);
        $data['reimbursable'] = (bool) ($data['reimbursable'] ?? true);

        return $data;
    }
}
