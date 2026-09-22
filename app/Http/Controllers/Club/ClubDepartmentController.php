<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDepartmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\SaveClubDepartmentRequest;
use App\Models\Club\ClubDepartment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Abteilungen/Sparten (MVP-842): Referenzliste mit Dialog; Löschen nur ohne
 * zugeordnete Gruppen.
 */
class ClubDepartmentController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', ClubDepartment::class);

        return view('club.departments.index', [
            'departments' => ClubDepartment::query()->withCount('groups')->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString(),
            'canManage' => Gate::allows('create', ClubDepartment::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubDepartment::class);

        return view('club.departments._form_dialog', ['department' => null]);
    }

    public function store(SaveClubDepartmentRequest $request): RedirectResponse {
        Gate::authorize('create', ClubDepartment::class);

        ClubDepartment::query()->create(['organization_id' => $this->currentOrganization()->id] + $this->attributes($request->validated()));

        return redirect()
            ->toList('club.departments.index')
            ->with('success', __('club.flash.department_created'));
    }

    public function edit(ClubDepartment $department): View {
        Gate::authorize('update', $department);

        return view('club.departments._form_dialog', ['department' => $department]);
    }

    public function update(SaveClubDepartmentRequest $request, ClubDepartment $department): RedirectResponse {
        Gate::authorize('update', $department);

        $department->update($this->attributes($request->validated()));

        return redirect()
            ->toList('club.departments.index')
            ->with('success', __('club.flash.department_updated'));
    }

    public function destroy(ClubDepartment $department): RedirectResponse {
        Gate::authorize('delete', $department);

        if ($department->groups()->exists()) {
            throw ValidationException::withMessages(['name' => __('club.error.department_has_groups')]);
        }
        $department->delete();

        return redirect()
            ->toList('club.departments.index')
            ->with('success', __('club.flash.department_deleted'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array {
        return [
            'name' => trim((string) $data['name']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'discipline' => filled($data['discipline'] ?? null) ? trim((string) $data['discipline']) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
