<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeSpaceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Enums\Knowledge\KnowledgeAudienceKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Knowledge\{KnowledgePage, KnowledgeSection, KnowledgeSpace, KnowledgeSpaceReader};
use App\Models\Platform\{Organization, Team, User};
use App\Services\Org\OrganizationRoles;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Bereiche und Rubriken der Wissensdatenbank (Feature 191, MVP-1664) mit
 * Leserecht je Bereich (MVP-1667): ein eingeschränkter Bereich ist nur für
 * die gewählten Rollen, Teams und Personen sichtbar.
 */
class KnowledgeSpaceController extends Controller {
    use ResolvesCurrentOrganization;

    public function create(): View {
        Gate::authorize('create', KnowledgeSpace::class);

        return view('knowledge-pages._space_dialog', [...$this->audienceOptions(), 'space' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', KnowledgeSpace::class);
        $organization = $this->currentOrganization();
        $data = $this->validateSpace($request);
        $space = DB::transaction(function () use ($organization, $data): KnowledgeSpace {
            $space = KnowledgeSpace::query()->create([
                'organization_id' => $organization->id,
                'title' => trim((string) $data['title']),
                'description' => $data['description'] ?? null,
                'position' => (int) KnowledgeSpace::query()->where('organization_id', $organization->id)->max('position') + 1,
                'is_restricted' => (bool) ($data['is_restricted'] ?? false),
                'created_by' => $this->authUser()->id,
                'updated_by' => $this->authUser()->id,
            ]);
            $this->syncReaders($space, $organization, $data);

            return $space;
        });

        return redirect()->route('knowledge.pages.index', ['space' => $space->sqid])->with('success', __('knowledge_pages.flash.space_created'));
    }

    public function edit(KnowledgeSpace $space): View {
        Gate::authorize('update', $space);

        return view('knowledge-pages._space_dialog', [...$this->audienceOptions(), 'space' => $space->load('readers')]);
    }

    public function update(Request $request, KnowledgeSpace $space): RedirectResponse {
        Gate::authorize('update', $space);
        $organization = $this->currentOrganization();
        $data = $this->validateSpace($request);
        DB::transaction(function () use ($space, $organization, $data): void {
            $space->forceFill([
                'title' => trim((string) $data['title']),
                'description' => $data['description'] ?? null,
                'is_restricted' => (bool) ($data['is_restricted'] ?? false),
                'updated_by' => $this->authUser()->id,
            ])->save();
            $this->syncReaders($space, $organization, $data);
        });

        return redirect()->route('knowledge.pages.index', ['space' => $space->sqid])->with('success', __('knowledge_pages.flash.space_updated'));
    }

    /** Nur leere Bereiche — auch Seiten im Papierkorb zählen. */
    public function destroy(KnowledgeSpace $space): RedirectResponse {
        Gate::authorize('delete', $space);
        if (KnowledgePage::query()->withTrashed()->where('knowledge_space_id', $space->id)->exists()) {
            return back()->with('error', __('knowledge_pages.error.space_not_empty'));
        }
        $space->delete();

        return redirect()->toList('knowledge.pages.index')->with('success', __('knowledge_pages.flash.space_deleted'));
    }

    public function createSection(KnowledgeSpace $space): View {
        Gate::authorize('update', $space);

        return view('knowledge-pages._section_dialog', ['space' => $space, 'section' => null]);
    }

    public function storeSection(Request $request, KnowledgeSpace $space): RedirectResponse {
        Gate::authorize('update', $space);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:180']]);
        $section = KnowledgeSection::query()->create([
            'organization_id' => $space->organization_id,
            'knowledge_space_id' => $space->id,
            'title' => trim((string) $data['title']),
            'position' => (int) KnowledgeSection::query()->where('knowledge_space_id', $space->id)->max('position') + 1,
            'created_by' => $this->authUser()->id,
            'updated_by' => $this->authUser()->id,
        ]);

        return redirect()->route('knowledge.pages.index', ['space' => $space->sqid, 'section' => $section->sqid])->with('success', __('knowledge_pages.flash.section_created'));
    }

    public function editSection(KnowledgeSection $section): View {
        Gate::authorize('update', $section->space);

        return view('knowledge-pages._section_dialog', ['space' => $section->space, 'section' => $section]);
    }

    public function updateSection(Request $request, KnowledgeSection $section): RedirectResponse {
        Gate::authorize('update', $section->space);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:180']]);
        $section->forceFill(['title' => trim((string) $data['title']), 'updated_by' => $this->authUser()->id])->save();

        return back()->with('success', __('knowledge_pages.flash.section_updated'));
    }

    /** Die Seiten der Rubrik rutschen an den Bereich (Fremdschlüssel nullOnDelete). */
    public function destroySection(KnowledgeSection $section): RedirectResponse {
        $space = $section->space;
        Gate::authorize('update', $space);
        $section->delete();

        return redirect()->route('knowledge.pages.index', ['space' => $space?->sqid])->with('success', __('knowledge_pages.flash.section_deleted'));
    }

    /** @return array<string, mixed> */
    private function validateSpace(Request $request): array {
        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_restricted' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array', 'max:30'],
            'roles.*' => ['string', 'max:125'],
            'teams' => ['nullable', 'array', 'max:50'],
            'teams.*' => ['string', 'max:64'],
            'users' => ['nullable', 'array', 'max:100'],
            'users.*' => ['string', 'max:64'],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function syncReaders(KnowledgeSpace $space, Organization $organization, array $data): void {
        $space->readers()->delete();
        if (! $space->is_restricted) {
            return;
        }
        $rows = [];
        foreach ((array) ($data['roles'] ?? []) as $role) {
            if (OrganizationRoles::matching([(string) $role], (int) $organization->id)->isNotEmpty()) {
                $rows[] = ['kind' => KnowledgeAudienceKind::Role->value, 'role_name' => (string) $role];
            }
        }
        $teamIds = array_filter(array_map(static fn ($sqid): ?int => Sqid::decode(Team::class, (string) $sqid), (array) ($data['teams'] ?? [])));
        foreach (Team::query()->where('organization_id', $organization->id)->whereKey($teamIds)->pluck('id') as $teamId) {
            $rows[] = ['kind' => KnowledgeAudienceKind::Team->value, 'team_id' => (int) $teamId];
        }
        $userIds = array_filter(array_map(static fn ($sqid): ?int => Sqid::decode(User::class, (string) $sqid), (array) ($data['users'] ?? [])));
        foreach (User::query()->forOrganization($organization)->whereKey($userIds)->pluck('id') as $userId) {
            $rows[] = ['kind' => KnowledgeAudienceKind::User->value, 'user_id' => (int) $userId];
        }
        foreach ($rows as $row) {
            KnowledgeSpaceReader::query()->create([...$row, 'organization_id' => $organization->id, 'knowledge_space_id' => $space->id]);
        }
    }

    /** @return array{roles: list<string>, teams: \Illuminate\Database\Eloquent\Collection<int, Team>, members: \Illuminate\Database\Eloquent\Collection<int, User>} */
    private function audienceOptions(): array {
        $organization = $this->currentOrganization();

        return [
            'roles' => array_values(Role::query()->where((string) config('permission.column_names.team_foreign_key', 'team_id'), $organization->id)->orderBy('name')->pluck('name')->unique()->all()),
            'teams' => Team::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name']),
            'members' => User::query()->forOrganization($organization)->whereNull('customer_id')->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
        ];
    }
}
