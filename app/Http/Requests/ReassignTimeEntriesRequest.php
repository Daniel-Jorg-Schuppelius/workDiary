<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReassignTimeEntriesRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{TimeEntry, User};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Massen-Neuzuordnung von Projektzeiten (MVP-508). Nur explizit übermittelte,
 * sichtbare Eintrags-IDs — keine „alle Treffer"-Semantik über Query-Filter.
 */
class ReassignTimeEntriesRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'ids' => TimeEntry::class,
        'target_user_id' => User::class,
    ];

    public function authorize(): bool {
        $user = $this->user();

        return $user instanceof User && ($user->isAdmin() || Gate::allows('timeEntry.reassign'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'target_user_id' => ['required', 'integer', new ExistsInCurrentOrganization('users')],
        ];
    }
}
