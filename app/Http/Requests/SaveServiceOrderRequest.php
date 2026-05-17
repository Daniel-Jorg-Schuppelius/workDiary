<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveServiceOrderRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\ServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:16'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_country' => ['nullable', 'string', 'size:2'],
            'address_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'scheduled_for' => ['required', 'date'],
            'time_window_start' => ['nullable', 'date_format:H:i'],
            'time_window_end' => ['nullable', 'date_format:H:i', 'after_or_equal:time_window_start'],
            'service_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'priority' => ['required', 'string', Rule::in(ServiceOrder::PRIORITIES)],
            'status' => ['sometimes', 'string', Rule::in(ServiceOrder::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
