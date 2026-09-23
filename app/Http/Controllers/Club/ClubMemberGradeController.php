<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberGradeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubProofKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{RecognizeGradeRequest, SaveMemberProofRequest};
use App\Models\Club\{ClubExamCandidate, ClubGrade, ClubGradingSystem, ClubMember, ClubMemberGrade, ClubMemberProof};
use App\Models\User;
use App\Services\Club\{ClubEligibilityService, ClubGradeCertificatePdfRenderer, ClubGradingService};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Graduierung je Mitglied (MVP-846): aktueller Grad je Ordnung, Voraussetzungen
 * für den nächsten Grad (fachlicher Zählzeitraum, unabhängig vom Header),
 * Anerkennung, Widerruf und Nachweise.
 */
class ClubMemberGradeController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubGradingService $grading,
        private readonly ClubEligibilityService $eligibility,
    ) {}

    public function show(ClubMember $member): View {
        Gate::authorize('view', $member);
        $today = CarbonImmutable::today();
        $systems = ClubGradingSystem::query()->where('is_active', true)->with('grades')->orderBy('name')->get();

        $overview = $systems->map(function (ClubGradingSystem $system) use ($member, $today): array {
            $current = $this->grading->currentGrade($member, $system, $today);
            $requirement = $this->grading->nextRequirement($member, $system, $today);

            return [
                'system' => $system,
                'current' => $current,
                'requirement' => $requirement,
                'report' => $requirement !== null ? $this->eligibility->evaluate($member, $requirement, $today) : null,
            ];
        });

        return view('club.members.grading', [
            'member' => $member,
            'overview' => $overview,
            'history' => ClubMemberGrade::query()->where('club_member_id', $member->id)->with(['grade', 'system', 'confirmedBy:id,name'])->orderByDesc('obtained_on')->orderByDesc('id')->get(),
            'proofs' => ClubMemberProof::query()->where('club_member_id', $member->id)->with('confirmedBy:id,name')->orderByDesc('obtained_on')->get(),
            // Prüfungen (MVP-847): Kandidaturen mit Ergebnis und Überprüfungsmarke.
            'candidates' => ClubExamCandidate::query()->where('club_member_id', $member->id)->with(['offer.event:id,title,started_at', 'targetGrade:id,name'])->orderByDesc('id')->get(),
            'enabled' => $this->grading->isEnabled($this->currentOrganization()),
            'canManage' => Gate::allows('manageMembers', ClubGradingSystem::class),
            'today' => $today,
        ]);
    }

    /** Bescheinigung (MVP-847) als PDF — auch für anerkannte Grade; ein Widerruf wird gedruckt. */
    public function certificate(ClubMember $member, ClubMemberGrade $memberGrade): Response {
        Gate::authorize('view', $member);
        abort_unless($memberGrade->club_member_id === $member->id, 404);
        $renderer = app(ClubGradeCertificatePdfRenderer::class);

        return response($renderer->output($memberGrade), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($memberGrade) . '"',
        ]);
    }

    public function createGrade(ClubMember $member): View {
        Gate::authorize('manageMembers', ClubGradingSystem::class);

        return view('club.members._grade_dialog', [
            'member' => $member,
            'systems' => ClubGradingSystem::query()->where('is_active', true)->with('grades')->orderBy('name')->get(),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function storeGrade(RecognizeGradeRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('manageMembers', ClubGradingSystem::class);
        $data = $request->validated();
        /** @var ClubGrade $grade */
        $grade = ClubGrade::query()->findOrFail((int) $data['club_grade_id']);
        /** @var User $actor */
        $actor = Auth::user();
        $this->grading->recognizeGrade($member, $grade, CarbonImmutable::parse((string) $data['obtained_on']), $data['evidence'] ?? null, $actor);

        return redirect()->route('club.members.grading', $member)->with('success', __('club.grading.flash.grade_recognized', ['grade' => $grade->name]));
    }

    public function revokeDialog(ClubMember $member, ClubMemberGrade $memberGrade): View {
        Gate::authorize('manageMembers', ClubGradingSystem::class);
        abort_unless($memberGrade->club_member_id === $member->id, 404);

        return view('club.members._grade_revoke_dialog', ['member' => $member, 'memberGrade' => $memberGrade->load('grade')]);
    }

    public function revoke(Request $request, ClubMember $member, ClubMemberGrade $memberGrade): RedirectResponse {
        Gate::authorize('manageMembers', ClubGradingSystem::class);
        abort_unless($memberGrade->club_member_id === $member->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->grading->revokeGrade($memberGrade, (string) $data['reason'], $actor);

        return redirect()->route('club.members.grading', $member)->with('success', __('club.grading.flash.grade_revoked'));
    }

    public function createProof(ClubMember $member): View {
        Gate::authorize('manageMembers', ClubGradingSystem::class);

        return view('club.members._proof_dialog', ['member' => $member, 'proof' => null, 'kinds' => ClubProofKind::cases(), 'today' => CarbonImmutable::today()]);
    }

    public function storeProof(SaveMemberProofRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('manageMembers', ClubGradingSystem::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->grading->addProof($member, $request->validated(), $actor);

        return redirect()->route('club.members.grading', $member)->with('success', __('club.grading.flash.proof_saved'));
    }

    public function editProof(ClubMember $member, ClubMemberProof $proof): View {
        Gate::authorize('manageMembers', ClubGradingSystem::class);
        abort_unless($proof->club_member_id === $member->id, 404);

        return view('club.members._proof_dialog', ['member' => $member, 'proof' => $proof, 'kinds' => ClubProofKind::cases(), 'today' => CarbonImmutable::today()]);
    }

    public function updateProof(SaveMemberProofRequest $request, ClubMember $member, ClubMemberProof $proof): RedirectResponse {
        Gate::authorize('manageMembers', ClubGradingSystem::class);
        abort_unless($proof->club_member_id === $member->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->grading->updateProof($proof, $request->validated(), $actor);

        return redirect()->route('club.members.grading', $member)->with('success', __('club.grading.flash.proof_saved'));
    }

    public function destroyProof(ClubMember $member, ClubMemberProof $proof): RedirectResponse {
        Gate::authorize('manageMembers', ClubGradingSystem::class);
        abort_unless($proof->club_member_id === $member->id, 404);
        $this->grading->deleteProof($proof);

        return redirect()->route('club.members.grading', $member)->with('success', __('club.grading.flash.proof_deleted'));
    }
}
