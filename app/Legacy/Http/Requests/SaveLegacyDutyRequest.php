<?php

namespace App\Legacy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveLegacyDutyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user' => ['required', 'integer', 'min:4', 'exists:legacy.user,id'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
        ];
    }
}
