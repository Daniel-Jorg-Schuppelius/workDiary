<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAdminTimeEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a non-project (administrative / travel / training) TimeEntry.
 */
class SaveAdminTimeEntryRequest extends FormRequest
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
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'activity_type' => ['required', 'string', Rule::in([
                TimeEntry::ACTIVITY_ADMIN,
                TimeEntry::ACTIVITY_TRAVEL,
                TimeEntry::ACTIVITY_TRAINING,
                TimeEntry::ACTIVITY_MEETING,
                TimeEntry::ACTIVITY_INTERNAL,
                TimeEntry::ACTIVITY_BREAK,
                TimeEntry::ACTIVITY_OTHER,
            ])],
            'activity_category_id' => ['nullable', 'integer', Rule::exists('activity_categories', 'id')],
            'attendance_id' => ['nullable', 'integer', Rule::exists('attendances', 'id')],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
