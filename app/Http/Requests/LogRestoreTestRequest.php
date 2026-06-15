<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogRestoreTestRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Backup\RestoreTestResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class LogRestoreTestRequest extends FormRequest {
    public function authorize(): bool {
        // Autorisierung erfolgt im Controller via Gate (Permission backup.restoreTest.log).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array {
        return [
            'source' => ['required', 'string', 'max:191'],
            'tested_on' => ['required', 'date', 'before_or_equal:today'],
            'result' => ['required', new Enum(RestoreTestResult::class)],
            'scope' => ['nullable', 'string', 'max:191'],
            'restored_size_bytes' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_due_on' => ['nullable', 'date', Rule::date()->afterOrEqual('today')],
        ];
    }
}
