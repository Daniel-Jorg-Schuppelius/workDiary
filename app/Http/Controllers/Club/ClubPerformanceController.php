<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubPerformanceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SavePerformanceRequest, SaveStartRightRequest};
use App\Models\Club\{ClubMember, ClubPerformance, ClubSportProfile, ClubStartRight};
use App\Models\User;
use App\Services\Club\ClubCompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Leistungen und Startrechte je Mitglied (MVP-855); Dialoge auf der Mitgliederseite. */
class ClubPerformanceController extends Controller {
    public function __construct(
        private readonly ClubCompetitionService $competitions,
    ) {}

    public function create(ClubMember $member): View {
        Gate::authorize('create', ClubPerformance::class);
        Gate::authorize('view', $member);

        return view('club.members._performance_dialog', ['member' => $member, 'performance' => null, 'profiles' => ClubSportProfile::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(SavePerformanceRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('create', ClubPerformance::class);
        Gate::authorize('view', $member);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->recordPerformance($member, $request->validated(), $actor);

        return redirect()->route('club.members.show', $member)->with('success', __('club.competitions.flash.performance_saved'));
    }

    public function edit(ClubMember $member, ClubPerformance $performance): View {
        Gate::authorize('update', $performance);
        abort_unless($performance->club_member_id === $member->id, 404);

        return view('club.members._performance_dialog', ['member' => $member, 'performance' => $performance->load('profile'), 'profiles' => collect()]);
    }

    public function update(SavePerformanceRequest $request, ClubMember $member, ClubPerformance $performance): RedirectResponse {
        Gate::authorize('update', $performance);
        abort_unless($performance->club_member_id === $member->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->correctPerformance($performance, $request->validated(), $actor);

        return redirect()->route('club.members.show', $member)->with('success', __('club.competitions.flash.performance_corrected'));
    }

    public function confirm(ClubMember $member, ClubPerformance $performance): RedirectResponse {
        Gate::authorize('confirm', $performance);
        abort_unless($performance->club_member_id === $member->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->confirmPerformance($performance, $actor);

        return redirect()->route('club.members.show', $member)->with('success', __('club.competitions.flash.performance_confirmed'));
    }

    public function destroy(ClubMember $member, ClubPerformance $performance): RedirectResponse {
        Gate::authorize('delete', $performance);
        abort_unless($performance->club_member_id === $member->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->deletePerformance($performance, $actor);

        return redirect()->route('club.members.show', $member)->with('success', __('club.competitions.flash.performance_deleted'));
    }

    public function startRightDialog(ClubMember $member): View {
        Gate::authorize('grantStartRight', ClubPerformance::class);

        return view('club.members._startright_dialog', ['member' => $member, 'profiles' => ClubSportProfile::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function grantStartRight(SaveStartRightRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('grantStartRight', ClubPerformance::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->grantStartRight($member, $request->validated(), $actor);

        return redirect()->route('club.members.show', $member)->with('success', __('club.competitions.flash.start_right_granted'));
    }

    public function revokeStartRight(ClubMember $member, ClubStartRight $startRight): RedirectResponse {
        Gate::authorize('grantStartRight', ClubPerformance::class);
        abort_unless($startRight->club_member_id === $member->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->revokeStartRight($startRight, $actor);

        return redirect()->route('club.members.show', $member)->with('success', __('club.competitions.flash.start_right_revoked'));
    }
}
