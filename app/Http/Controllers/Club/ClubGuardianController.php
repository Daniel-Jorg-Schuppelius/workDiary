<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGuardianController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Http\Requests\Club\SaveClubGuardianRequest;
use App\Models\Club\{ClubGuardian, ClubMember};
use App\Models\User;
use App\Services\Club\ClubMemberService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Vertretungen (Sorgeberechtigte) eines Mitglieds (MVP-842): Dialoge auf der
 * Mitgliedsseite, Widerruf statt Löschen. Rechte über die Mitglieds-Policy.
 */
class ClubGuardianController extends Controller {
    public function __construct(
        private readonly ClubMemberService $members,
    ) {}

    public function create(ClubMember $member): View {
        Gate::authorize('manageMembership', $member);

        return view('club.members._guardian_dialog', ['member' => $member, 'guardian' => null, 'users' => $this->linkableUsers()]);
    }

    public function store(SaveClubGuardianRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('manageMembership', $member);

        /** @var User $actor */
        $actor = Auth::user();
        $this->members->addGuardian($member, $request->validated(), $actor);

        return redirect()
            ->route('club.members.show', $member)
            ->with('success', __('club.flash.guardian_added'));
    }

    public function edit(ClubMember $member, ClubGuardian $guardian): View {
        Gate::authorize('manageMembership', $member);
        abort_unless($guardian->club_member_id === $member->id, 404);

        return view('club.members._guardian_dialog', ['member' => $member, 'guardian' => $guardian, 'users' => $this->linkableUsers()]);
    }

    public function update(SaveClubGuardianRequest $request, ClubMember $member, ClubGuardian $guardian): RedirectResponse {
        Gate::authorize('manageMembership', $member);
        abort_unless($guardian->club_member_id === $member->id, 404);

        $this->members->updateGuardian($guardian, $request->validated());

        return redirect()
            ->route('club.members.show', $member)
            ->with('success', __('club.flash.guardian_updated'));
    }

    public function revoke(Request $request, ClubMember $member, ClubGuardian $guardian): RedirectResponse {
        Gate::authorize('manageMembership', $member);
        abort_unless($guardian->club_member_id === $member->id, 404);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->members->revokeGuardian($guardian, $actor, $data['note'] ?? null);

        return redirect()
            ->route('club.members.show', $member)
            ->with('success', __('club.flash.guardian_revoked'));
    }

    /** @return Collection<int, User> */
    private function linkableUsers(): Collection {
        return User::query()
            ->inCurrentOrganization()
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
