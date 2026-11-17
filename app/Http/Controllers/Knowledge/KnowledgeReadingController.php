<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeReadingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Enums\Knowledge\KnowledgeAudienceKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Knowledge\{KnowledgePage, KnowledgePageAssignment};
use App\Models\Platform\{Team, User};
use App\Services\Knowledge\Pages\KnowledgeReadingService;
use App\Services\Org\OrganizationRoles;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Pflichtlektüre (Feature 191, MVP-1666): eigene offene Lektüre und
 * Einarbeitungsliste, Zuordnung je Seite und die Übersicht, wer welche
 * Fassung bestätigt hat.
 */
class KnowledgeReadingController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly KnowledgeReadingService $readings) {}

    /** Meine Lektüre: offene Pflichtlektüre und Einarbeitungsliste. */
    public function index(): View {
        Gate::authorize('viewAny', KnowledgePage::class);
        $user = $this->authUser();

        return view('knowledge-pages.readings', [
            'open' => $this->readings->openFor($user),
            'onboarding' => $this->readings->onboardingFor($user),
        ]);
    }

    /** Zuordnung und Bestätigungen einer Seite (Verantwortliche). */
    public function show(KnowledgePage $page): View {
        Gate::authorize('manage', $page);
        $organization = $this->currentOrganization();

        return view('knowledge-pages.reading_overview', [
            'page' => $page->load(['assignments.team:id,name', 'assignments.user:id,name']),
            'rows' => $this->readings->overview($page),
            'roles' => Role::query()->where((string) config('permission.column_names.team_foreign_key', 'team_id'), $organization->id)->orderBy('name')->pluck('name')->unique()->values()->all(),
            'teams' => Team::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name']),
            'members' => User::query()->forOrganization($organization)->whereNull('customer_id')->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, KnowledgePage $page): RedirectResponse {
        Gate::authorize('manage', $page);
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'kind' => ['required', Rule::enum(KnowledgeAudienceKind::class)],
            'role_name' => ['nullable', 'string', 'max:125'],
            'team' => ['nullable', 'string', 'max:64'],
            'user' => ['nullable', 'string', 'max:64'],
            'is_onboarding' => ['nullable', 'boolean'],
        ]);
        $kind = KnowledgeAudienceKind::from((string) $data['kind']);
        $roleName = null;
        $teamId = null;
        $userId = null;
        if ($kind === KnowledgeAudienceKind::Role) {
            $roleName = (string) ($data['role_name'] ?? '');
            abort_if($roleName === '' || OrganizationRoles::matching([$roleName], (int) $organization->id)->isEmpty(), 422);
        } elseif ($kind === KnowledgeAudienceKind::Team) {
            $id = Sqid::decode(Team::class, (string) ($data['team'] ?? ''));
            $teamId = $id !== null && Team::query()->where('organization_id', $organization->id)->whereKey($id)->exists() ? $id : null;
            abort_if($teamId === null, 422);
        } elseif ($kind === KnowledgeAudienceKind::User) {
            $id = Sqid::decode(User::class, (string) ($data['user'] ?? ''));
            $userId = $id !== null ? User::query()->forOrganization($organization)->whereKey($id)->value('id') : null;
            abort_if($userId === null, 422);
        }
        $this->readings->assign($page, $this->authUser(), $kind, $roleName, $teamId, $userId === null ? null : (int) $userId, (bool) ($data['is_onboarding'] ?? false));

        return back()->with('success', __('knowledge_pages.flash.assigned'));
    }

    public function destroy(KnowledgePage $page, KnowledgePageAssignment $assignment): RedirectResponse {
        Gate::authorize('manage', $page);
        abort_unless((int) $assignment->knowledge_page_id === (int) $page->id, 404);
        $this->readings->unassign($assignment);

        return back()->with('success', __('knowledge_pages.flash.unassigned'));
    }
}
