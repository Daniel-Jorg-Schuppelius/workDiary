<?php

namespace App\Legacy\Http\Requests;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;

class RunLegacyMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:all,users,diary,shifts,assignments'],
        ];
    }
}
