<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubDepartmentRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Models\Club\ClubDepartment;
use App\Models\Platform\Organization;
use Illuminate\Validation\Rule;

/** Abteilung/Sparte (MVP-842): Name je Organisation eindeutig unter den nicht gelöschten. */
class SaveClubDepartmentRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        $department = $this->route('department');
        $department = $department instanceof ClubDepartment ? $department : null;
        $organizationId = $department !== null ? $department->organization_id : $this->currentOrganizationId();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('club_departments', 'name')
                    ->where(fn($query) => $query->where('organization_id', $organizationId)->whereNull('deleted_at'))
                    ->ignore($department?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'discipline' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge(['is_active' => $this->has('is_active') ? $this->boolean('is_active') : true]);
    }

    /** Organisationskontext der Anfrage — abgesichert, weil die Bindung in Konsole/Queue fehlen kann. */
    private function currentOrganizationId(): ?int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof Organization) {
                return (int) $organization->id;
            }
        }

        return null;
    }
}
