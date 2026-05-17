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

use App\Models\TravelLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTravelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'attendance_id' => ['nullable', 'integer', Rule::exists('attendances', 'id')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')],
            'from_address' => ['nullable', 'string', 'max:255'],
            'to_address' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['required', 'numeric', 'min:0', 'max:10000'],
            'vehicle' => ['required', 'string', Rule::in(TravelLog::VEHICLES)],
            'vehicle_label' => ['nullable', 'string', 'max:64'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'round_trip' => ['sometimes', 'boolean'],
            'reimbursable' => ['sometimes', 'boolean'],
            'rate_per_km' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['round_trip'] = (bool) ($data['round_trip'] ?? false);
        $data['reimbursable'] = (bool) ($data['reimbursable'] ?? true);

        return $data;
    }
}
