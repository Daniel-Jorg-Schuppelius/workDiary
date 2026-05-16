<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RunLegacyMigrationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
