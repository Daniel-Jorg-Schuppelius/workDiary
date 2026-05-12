<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTimeEntryRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'date'        => ['required', 'date'],
            'minutes'     => ['required', 'integer', 'min:1', 'max:1440'],
            'task_id'     => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
