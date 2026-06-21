<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExistsInCurrentOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Prüft, dass ein Fremdschlüssel auf einen Datensatz der AKTIVEN Organisation
 * zeigt. Modelle wie {@see \App\Models\User} tragen `organization_id` direkt,
 * unterliegen aber keinem globalen Org-Scope; ein reines `exists:users,id`
 * erlaubt sonst, einen Datensatz einer fremden Organisation zuzuweisen
 * (Cross-Tenant-Injection). Diese Rule schließt das Org-übergreifend.
 *
 * Ohne gebundene `currentOrganization` (z. B. CLI/globaler Admin) fällt die
 * Prüfung auf reines Existieren zurück und ändert das bisherige Verhalten nicht.
 */
final readonly class ExistsInCurrentOrganization implements ValidationRule {
    public function __construct(
        private string $table = 'users',
        private string $column = 'id',
        private string $organizationColumn = 'organization_id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if ($value === null || $value === '') {
            return; // required/nullable wird separat geregelt
        }

        $query = DB::table($this->table)->where($this->column, $value);

        $orgId = app()->bound('currentOrganization') ? (app('currentOrganization')->id ?? null) : null;
        if ($orgId !== null) {
            $query->where($this->organizationColumn, $orgId);
        }

        if (! $query->exists()) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
