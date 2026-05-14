<?php

namespace App\Http\Requests;

use App\Models\Timesheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(Timesheet::STATUSES)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_role' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
