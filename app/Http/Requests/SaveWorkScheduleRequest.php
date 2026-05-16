<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveWorkScheduleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'weekly_minutes' => ['required', 'integer', 'min:60', 'max:6000'],
            'daily_target_minutes' => ['required', 'integer', 'min:30', 'max:720'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'core_start' => ['nullable', 'date_format:H:i'],
            'core_end' => ['nullable', 'date_format:H:i', 'after:core_start'],
            'frame_start' => ['nullable', 'date_format:H:i'],
            'frame_end' => ['nullable', 'date_format:H:i', 'after:frame_start'],
            'break_after_minutes' => ['required', 'integer', 'min:60', 'max:720'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],
        ];
    }
}
