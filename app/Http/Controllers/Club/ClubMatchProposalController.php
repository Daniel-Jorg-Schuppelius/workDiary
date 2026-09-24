<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMatchProposalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubMatchProposalSource, ClubProposalStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{AcceptMatchProposalRequest, ImportMatchProposalsRequest};
use App\Models\Club\{ClubEventDetails, ClubGroup, ClubMatchProposal};
use App\Models\Facility\Room;
use App\Models\Platform\User;
use App\Services\Club\ClubMatchService;
use App\Support\{Sqid, Tz};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Spielplan-Import als Vorschlagsliste (MVP-852): prüfen, übernehmen, verwerfen — vor Bestätigung ändert sich nichts. */
class ClubMatchProposalController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubMatchService $matches,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubEventDetails::class);
        /** @var User $user */
        $user = Auth::user();
        $status = (string) $request->query('status', 'open');
        $teamSqid = trim((string) $request->query('team', ''));
        $query = ClubMatchProposal::query()->with(['team:id,name,leader_user_id', 'event:id,title', 'duplicateEvent:id,title', 'importedBy:id,name'])->orderBy('starts_at');
        if (ClubProposalStatus::tryFrom($status) instanceof ClubProposalStatus) {
            $query->where('status', $status);
        } else {
            $status = 'all';
        }
        $teamId = $teamSqid !== '' ? Sqid::decodeOrNumeric(ClubGroup::class, $teamSqid) : null;
        if ($teamId !== null) {
            $query->where('club_group_id', $teamId);
        }
        if (! Gate::allows('create', ClubEventDetails::class)) {
            $query->whereHas('team', fn($q) => $q->where('leader_user_id', $user->id));
        }

        return view('club.matches.proposals.index', [
            'proposals' => $query->paginate(50)->withQueryString(),
            'filters' => ['status' => $status, 'team' => $teamSqid],
            'teams' => $this->teamsFor($user),
        ]);
    }

    public function importDialog(Request $request): View {
        Gate::authorize('viewAny', ClubEventDetails::class);
        /** @var User $user */
        $user = Auth::user();
        $teams = $this->teamsFor($user);
        abort_if($teams->isEmpty(), 403);
        $teamId = Sqid::decodeOrNumeric(ClubGroup::class, (string) $request->query('team', ''));

        return view('club.matches.proposals._import_dialog', ['teams' => $teams, 'preselectedTeam' => $teamId, 'formTz' => Tz::current()]);
    }

    public function import(ImportMatchProposalsRequest $request): RedirectResponse {
        $data = $request->validated();
        /** @var ClubGroup $team */
        $team = ClubGroup::query()->whereKey((int) $data['club_group_id'])->firstOrFail();
        Gate::authorize('decide', $team);
        /** @var User $actor */
        $actor = Auth::user();
        $file = $request->file('file');
        $content = $file !== null ? ToolkitFile::read($file->getRealPath()) : (string) ($data['content'] ?? '');
        $summary = $this->matches->importProposals($team, $actor, ClubMatchProposalSource::from((string) $data['source']), $content, $data['timezone'] ?? null);
        $flash = __('club.matches.flash.imported', ['created' => $summary['created'], 'skipped' => $summary['skipped'], 'duplicates' => $summary['duplicates']]);
        $redirect = redirect()->route('club.matches.proposals.index', ['team' => $team->sqid]);
        if ($summary['errors'] !== []) {
            return $redirect->with('warning', $flash . ' ' . implode(' ', array_slice($summary['errors'], 0, 5)));
        }

        return $redirect->with('success', $flash);
    }

    public function acceptDialog(ClubMatchProposal $proposal): View {
        Gate::authorize('decide', $proposal->team()->firstOrFail());
        $tz = Tz::current();

        return view('club.matches.proposals._accept_dialog', [
            'proposal' => $proposal->load(['team:id,name', 'duplicateEvent:id,title']),
            'formTz' => $tz,
            'start' => CarbonImmutable::instance($proposal->starts_at)->setTimezone($tz)->format('Y-m-d\TH:i'),
            'end' => CarbonImmutable::instance($proposal->ends_at ?? CarbonImmutable::instance($proposal->starts_at)->addMinutes(ClubMatchService::DEFAULT_DURATION_MINUTES))->setTimezone($tz)->format('Y-m-d\TH:i'),
            'leaders' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function accept(AcceptMatchProposalRequest $request, ClubMatchProposal $proposal): RedirectResponse {
        Gate::authorize('decide', $proposal->team()->firstOrFail());
        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $tz = Tz::isValid($data['timezone'] ?? null) && ($data['timezone'] ?? 'UTC') !== 'UTC' ? (string) $data['timezone'] : Tz::current();
        $data['timezone'] = $tz;
        foreach (['started_at', 'ended_at'] as $key) {
            $data[$key] = CarbonImmutable::parse((string) $data[$key], $tz)->utc()->format('Y-m-d H:i:s');
        }
        $event = $this->matches->acceptProposal($proposal, $actor, $data);

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.proposal_accepted'));
    }

    public function dismiss(ClubMatchProposal $proposal): RedirectResponse {
        Gate::authorize('decide', $proposal->team()->firstOrFail());
        /** @var User $actor */
        $actor = Auth::user();
        $this->matches->dismissProposal($proposal, $actor);

        return redirect()->route('club.matches.proposals.index', ['team' => $proposal->team?->sqid])->with('success', __('club.matches.flash.proposal_dismissed'));
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ClubGroup> */
    private function teamsFor(User $user) {
        $query = ClubGroup::query()->where('is_team', true)->where('is_active', true)->orderBy('name');
        if (! Gate::allows('create', ClubEventDetails::class)) {
            $query->where('leader_user_id', $user->id);
        }

        return $query->get(['id', 'name', 'leader_user_id']);
    }
}
