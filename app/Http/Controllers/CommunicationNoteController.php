<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility, ParticipantParty};
use App\Models\{CommunicationNote, Customer, DiaryEntry, Project, User};
use App\Services\Communication\CommunicationNoteService;
use App\Support\{Sqid, Tz};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class CommunicationNoteController extends Controller {
    /**
     * Whitelist der erlaubten Bezugs-Typen. Verhindert, dass Aufrufer
     * beliebige Klassen an `notable_type` setzen können.
     *
     * @var array<string, class-string<Model>>
     */
    private const NOTABLE_MAP = [
        'diary' => DiaryEntry::class,
        'customer' => Customer::class,
        'project' => Project::class,
    ];

    public function __construct(
        private readonly CommunicationNoteService $service,
    ) {}

    public function create(Request $request): View {
        Gate::authorize('create', CommunicationNote::class);

        [$notableKind, $notable] = $this->resolveNotableFromRequest($request);

        return view('communication-notes._form_dialog', [
            'note' => null,
            'notableKind' => $notableKind,
            'notableId' => Sqid::encode($notable::class, (int) $notable->getKey()),
            'users' => $this->assignableUsers(),
            'canPublishToCustomer' => Gate::allows('publishToCustomer', CommunicationNote::class),
            'canManageConfidential' => Gate::allows('manageConfidential', CommunicationNote::class),
        ]);
    }

    public function edit(CommunicationNote $note): View {
        Gate::authorize('update', $note);

        /** @var User $viewer */
        $viewer = Auth::user();
        $this->service->recordConfidentialView($note, $viewer);

        return view('communication-notes._form_dialog', [
            'note' => $note->load('participants'),
            'notableKind' => array_search($note->notable_type, self::NOTABLE_MAP, true) ?: 'diary',
            'notableId' => Sqid::encode($note->notable_type, (int) $note->notable_id),
            'users' => $this->assignableUsers(),
            'canPublishToCustomer' => Gate::allows('publishToCustomer', CommunicationNote::class),
            'canManageConfidential' => Gate::allows('manageConfidential', CommunicationNote::class),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', CommunicationNote::class);

        $data = $this->validateNote($request, includeNotable: true);

        $notableClass = self::NOTABLE_MAP[$data['notable_kind']];
        $notable = $this->findNotable($notableClass, (string) $data['notable_id']);

        if (($data['visibility'] ?? CommunicationVisibility::Internal->value) === CommunicationVisibility::Customer->value) {
            Gate::authorize('publishToCustomer', CommunicationNote::class);
        }
        if (! empty($data['confidential'])) {
            Gate::authorize('manageConfidential', CommunicationNote::class);
        }

        /** @var User $creator */
        $creator = Auth::user();

        $note = $this->service->create($notable, $creator, $this->serviceAttributes($data));

        return redirect()
            ->back()
            ->with('success', __('communication.flash.created'))
            ->withFragment('communication-note-' . $note->id);
    }

    public function update(Request $request, CommunicationNote $note): RedirectResponse {
        Gate::authorize('update', $note);

        $data = $this->validateNote($request, includeNotable: false);

        if (($data['visibility'] ?? $note->visibility->value) === CommunicationVisibility::Customer->value
            && $note->visibility !== CommunicationVisibility::Customer) {
            Gate::authorize('publishToCustomer', CommunicationNote::class);
        }
        if (array_key_exists('confidential', $data) && (bool) $data['confidential'] !== $note->confidential) {
            Gate::authorize('manageConfidential', CommunicationNote::class);
        }

        /** @var User $actor */
        $actor = Auth::user();

        $this->service->update($note, $actor, $this->serviceAttributes($data));

        return redirect()
            ->back()
            ->with('success', __('communication.flash.updated'))
            ->withFragment('communication-note-' . $note->id);
    }

    public function publish(CommunicationNote $note): RedirectResponse {
        Gate::authorize('publishToCustomer', CommunicationNote::class);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->publishToCustomer($note, $actor);

        return redirect()
            ->back()
            ->with('success', __('communication.flash.published'))
            ->withFragment('communication-note-' . $note->id);
    }

    public function confidential(Request $request, CommunicationNote $note): RedirectResponse {
        Gate::authorize('manageConfidential', CommunicationNote::class);

        $data = $request->validate([
            'confidential' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        if ((bool) $data['confidential']) {
            $this->service->markConfidential($note, $actor);
            $flash = __('communication.flash.confidential_set');
        } else {
            $this->service->unmarkConfidential($note, $actor);
            $flash = __('communication.flash.confidential_unset');
        }

        return redirect()
            ->back()
            ->with('success', $flash)
            ->withFragment('communication-note-' . $note->id);
    }

    public function completeFollowup(CommunicationNote $note): RedirectResponse {
        Gate::authorize('completeFollowup', $note);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->completeFollowup($note, $actor);

        return redirect()
            ->back()
            ->with('success', __('communication.flash.followup_completed'))
            ->withFragment('communication-note-' . $note->id);
    }

    public function destroy(Request $request, CommunicationNote $note): RedirectResponse {
        Gate::authorize('delete', $note);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($note, $actor, $data['reason'] ?? null);

        return redirect()
            ->back()
            ->with('success', __('communication.flash.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNote(Request $request, bool $includeNotable): array {
        $rules = [
            'type' => ['required', 'string', 'in:' . implode(',', array_column(CommunicationNoteType::cases(), 'value'))],
            'direction' => ['required', 'string', 'in:' . implode(',', array_column(CommunicationDirection::cases(), 'value'))],
            'occurred_at' => ['required', 'date'],
            'subject' => ['required', 'string', 'min:3', 'max:180'],
            'body' => ['required', 'string', 'max:8000'],
            'result' => ['nullable', 'string', 'max:8000'],
            'next_action' => ['nullable', 'string', 'max:180'],
            'next_action_due_at' => ['nullable', 'date', 'required_with:next_action_user_id'],
            'next_action_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'visibility' => ['nullable', 'string', 'in:' . implode(',', array_column(CommunicationVisibility::cases(), 'value'))],
            'confidential' => ['nullable', 'boolean'],
            'participants' => ['nullable', 'array', 'max:25'],
            'participants.*.name' => ['nullable', 'string', 'max:120'],
            'participants.*.role' => ['nullable', 'string', 'max:40'],
            'participants.*.party' => ['nullable', 'string', 'in:' . implode(',', array_column(ParticipantParty::cases(), 'value'))],
            'participants.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];

        if ($includeNotable) {
            $rules['notable_kind'] = ['required', 'string', 'in:' . implode(',', array_keys(self::NOTABLE_MAP))];
            $rules['notable_id'] = ['required', 'string'];
        }

        return $request->validate($rules);
    }

    /**
     * Mappt validierte Request-Daten auf Service-Attribute (UTC-Zeiten).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function serviceAttributes(array $data): array {
        return [
            'type' => $data['type'],
            'direction' => $data['direction'],
            'occurred_at' => Tz::toUtcString((string) $data['occurred_at']),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'result' => $data['result'] ?? null,
            'next_action' => $data['next_action'] ?? null,
            'next_action_due_at' => Tz::toUtcString($data['next_action_due_at'] ?? null),
            'next_action_user_id' => $data['next_action_user_id'] ?? null,
            'visibility' => $data['visibility'] ?? CommunicationVisibility::Internal->value,
            'confidential' => (bool) ($data['confidential'] ?? false),
            'participants' => $data['participants'] ?? [],
        ];
    }

    /**
     * @return array{0: string, 1: Model}
     */
    private function resolveNotableFromRequest(Request $request): array {
        $notableKind = (string) $request->query('notable_kind', '');
        if (! array_key_exists($notableKind, self::NOTABLE_MAP)) {
            abort(404);
        }

        $notableClass = self::NOTABLE_MAP[$notableKind];
        $notable = $this->findNotable($notableClass, (string) $request->query('notable_id', ''));

        return [$notableKind, $notable];
    }

    /**
     * @param  class-string<Model>  $notableClass
     */
    private function findNotable(string $notableClass, string $rawId): Model {
        $notableId = Sqid::decodeOrNumeric($notableClass, $rawId);
        if ($notableId === null || $notableId < 1) {
            abort(404);
        }

        /** @var Model|null $notable */
        $notable = $notableClass::query()->find($notableId);
        if ($notable === null) {
            abort(404);
        }

        return $notable;
    }

    /**
     * Benutzer der eigenen Organisation für Verantwortlichen-/Beteiligten-Auswahl.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assignableUsers() {
        /** @var User $user */
        $user = Auth::user();

        return User::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
