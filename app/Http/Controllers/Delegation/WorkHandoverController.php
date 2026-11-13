<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkHandoverController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Delegation;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\User;
use App\Services\Delegation\WorkDelegationService;
use App\Services\Org\UserOffboardingService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Endgültige Übergabe ohne Austritt (Feature 202, MVP-1648): derselbe
 * Übergabe-Abschnitt wie im Austritts-Dialog — je Bereich (Aufträge, Aufgaben,
 * Tickets, Prozedurläufe, Ideenkarten …) eine eigene Nachfolge oder keine. Das
 * Mitglied bleibt aktiv; die Anzahl je Bereich steht im Audit des Mitglieds.
 * Einstiege: Mitgliederliste und — für die Leitung der Vertretungen auch ohne
 * Zugang zur Mitgliederliste — die Seite „Vertretungen“ über die Personenwahl.
 */
class WorkHandoverController extends Controller {
    use ResolvesCurrentOrganization;

    /**
     * Herkunftsmarke „Vertretungen“: nach dem Speichern geht es dorthin zurück
     * statt in die Mitgliederliste. Nur dieser feste Wert zählt, nie ein Ziel
     * aus der Eingabe.
     */
    private const string ORIGIN_DELEGATIONS = 'delegations';

    public function __construct(
        private readonly WorkDelegationService $delegations,
        private readonly UserOffboardingService $offboarding,
    ) {}

    /** Personenwahl auf der Seite „Vertretungen“: wessen Arbeit endgültig übergeben wird. */
    public function choose(): View {
        Gate::authorize('manage', WorkDelegation::class);
        $actor = $this->authUser();

        $members = User::query()
            ->where('organization_id', $this->currentOrganizationId())
            ->whereKeyNot($actor->id)
            ->whereNull('deactivated_at')
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $member): bool => $this->mayHandOver($member))
            ->values();

        return view('delegations._handover_choose', ['members' => $members]);
    }

    public function dialog(Request $request, User $member): View {
        $this->authorizeHandover($member);

        return view('delegations._handover_dialog', [
            'member' => $member,
            'handovers' => $this->offboarding->handovers($member),
            'candidates' => $this->candidates($member),
            'origin' => $this->fromDelegations($request) ? self::ORIGIN_DELEGATIONS : null,
        ]);
    }

    public function store(Request $request, User $member): RedirectResponse {
        $this->authorizeHandover($member);
        $data = $request->validate([
            'handovers' => ['nullable', 'array'],
            'handovers.*' => ['nullable', 'string', 'max:64'],
        ]);

        $successors = [];
        foreach ((array) ($data['handovers'] ?? []) as $key => $successorKey) {
            $successorId = filled($successorKey) ? Sqid::decode(User::class, (string) $successorKey) : null;
            if ($successorId === null) {
                continue;
            }
            $successors[(string) $key] = User::query()
                ->where('organization_id', $member->organization_id)
                ->whereKey($successorId)
                ->firstOrFail();
        }

        $result = $this->delegations->handOverPermanently($member, $successors, $this->authUser());
        $count = array_sum(array_column($result, 'count'));

        $list = $this->fromDelegations($request) ? 'delegations.index' : 'org.members.index';

        return redirect()->toList($list)
            ->with('success', trans_choice('delegation.handover.flash', $count, ['count' => $count, 'name' => $member->name]));
    }

    private function fromDelegations(Request $request): bool {
        return $request->string('origin')->toString() === self::ORIGIN_DELEGATIONS;
    }

    private function authorizeHandover(User $member): void {
        $this->assertSameOrganization($member);
        abort_unless($this->mayHandOver($member), 403);
    }

    /** Aktives Mitglied, nicht die eigene Person, Plattform-Admins nur durch Plattform-Admins, Recht über die Policy. */
    private function mayHandOver(User $member): bool {
        $actor = $this->authUser();

        return (int) $member->id !== (int) $actor->id
            && ! $member->isDeactivated()
            && (! $member->isGlobalAdmin() || $actor->isGlobalAdmin())
            && Gate::allows('handOver', [WorkDelegation::class, $member]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function candidates(User $member): \Illuminate\Database\Eloquent\Collection {
        return User::query()
            ->where('organization_id', $member->organization_id)
            ->whereKeyNot($member->id)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
