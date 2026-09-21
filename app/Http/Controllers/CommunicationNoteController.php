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
use App\Http\Controllers\Concerns\{ParsesIndexQuery, ResolvesCurrentOrganization};
use App\Models\{CommunicationNote, Customer, DiaryEntry, Organization, Project, Tag, User};
use App\Services\Communication\CommunicationNoteService;
use App\Services\Ideas\NodeConversionService;
use App\Support\{EntityUrl, Sqid, Tz};
use App\Support\ErrorText;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunicationNoteController extends Controller {
    use ParsesIndexQuery;
    use ResolvesCurrentOrganization;

    /** Ablage der zentralen Schnellerfassung (Feature 154). */
    private const STORAGE_INTERNAL = 'internal';

    private const STORAGE_CUSTOMER = 'customer';

    private const ALLOWED_SORTS = ['occurred_at', 'subject', 'type'];

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
        // Vollaudit 2026-07 (M12): Spec §5 kennt fünf Bezüge — Karte am
        // Abnahmeprotokoll und am Objekt/Asset.
        'protocol' => \App\Models\Protocol::class,
        'asset' => \App\Models\Asset::class,
        // Feature 091: Qualifizierungs-Notizen an der Lead-Akte.
        'lead' => \App\Models\Lead::class,
        // Feature 149 (MVP-789): private Lernnotizen an der Einschreibung.
        'learning_enrollment' => \App\Models\Learning\LearningEnrollment::class,
    ];

    public function __construct(
        private readonly CommunicationNoteService $service,
    ) {}

    /** Zentrale Notizliste (Feature 154): alle für den Nutzer sichtbaren Notizen der Organisation. */
    public function index(Request $request): View {
        Gate::authorize('viewAny', CommunicationNote::class);

        /** @var User $user */
        $user = Auth::user();
        ['sort' => $sort, 'dir' => $dir, 'search' => $search] = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'occurred_at', defaultDir: 'desc');

        $search = trim($search);
        $storage = (string) $request->query('storage', '');
        $customerRaw = trim((string) $request->query('customer', ''));
        // Unbekannte Kennung filtert auf 0 statt den Filter fallen zu lassen.
        $customerId = $customerRaw !== '' ? (Sqid::decodeOrNumeric(Customer::class, $customerRaw) ?? 0) : null;
        $type = CommunicationNoteType::tryFrom((string) $request->query('type', ''));
        $openFollowUps = $request->boolean('open_followups');
        // Schlagwort (MVP-810): unbekannte Kennung filtert auf 0 statt den Filter fallen zu lassen.
        $tagRaw = trim((string) $request->query('tag', ''));
        $tagId = $tagRaw !== '' ? (Sqid::decodeOrNumeric(Tag::class, $tagRaw) ?? 0) : null;

        $notes = CommunicationNote::query()
            ->visibleTo($user)
            ->with(['notable', 'creator:id,name', 'nextActionUser:id,name', 'tags:id,name,color,slug'])
            ->when($storage === self::STORAGE_INTERNAL, fn($q) => $q->where('notable_type', Organization::class))
            ->when($storage === self::STORAGE_CUSTOMER || $customerId !== null, fn($q) => $q->where('notable_type', Customer::class))
            ->when($customerId !== null, fn($q) => $q->where('notable_id', $customerId))
            ->when($type, fn($q, CommunicationNoteType $t) => $q->where('type', $t->value))
            ->when($openFollowUps, fn($q) => $q->openFollowUps())
            ->when($tagId !== null, fn($q) => $q->whereHas('tags', fn($t) => $t->whereKey($tagId)))
            ->when($search !== '', fn($q) => $q->where(fn(Builder $w) => $w->whereLikeEscaped('subject', $search)->orWhereLikeEscaped('body', $search)))
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $this->service->recordConfidentialViews($notes->getCollection(), $user);

        // Sprung aus der Tätigkeitsrecherche: interne Notizen haben keine Akte, der Lesedialog öffnet sich hier.
        $openNote = null;
        $openNoteId = Sqid::decodeOrNumeric(CommunicationNote::class, (string) $request->query('note', ''));
        if ($openNoteId !== null && $openNoteId > 0) {
            $candidate = CommunicationNote::query()->find($openNoteId);
            $openNote = $candidate !== null && Gate::allows('view', $candidate) ? $candidate : null;
        }

        return view('communication-notes.index', [
            'notes' => $notes,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            // Nur Schlagwörter an Notizen, die die Person sehen darf — sonst
            // verriete die Auswahl Stichworte aus vertraulichen Notizen.
            'tags' => Tag::query()
                ->whereHas('communicationNotes', fn($q) => $q->visibleTo($user))
                ->orderBy('name')
                ->get(['id', 'name']),
            'contextUrls' => $notes->getCollection()->mapWithKeys(static fn(CommunicationNote $note): array => [
                $note->id => $note->isOrganizationNote() ? null : EntityUrl::byType($note->notable_type, (int) $note->notable_id),
            ])->all(),
            'filters' => [
                'q' => $search,
                'storage' => in_array($storage, [self::STORAGE_INTERNAL, self::STORAGE_CUSTOMER], true) ? $storage : '',
                'customer' => $customerId ? (string) Sqid::encode(Customer::class, $customerId) : '',
                'type' => $type->value ?? '',
                'open_followups' => $openFollowUps,
                'tag' => $tagId ? (string) Sqid::encode(Tag::class, $tagId) : '',
            ],
            'sort' => $sort,
            'dir' => $dir,
            'openNote' => $openNote,
        ]);
    }

    public function show(CommunicationNote $note): View {
        Gate::authorize('view', $note);
        $this->guardPrivate($note);

        /** @var User $viewer */
        $viewer = Auth::user();
        $this->service->recordConfidentialView($note, $viewer);

        return view('communication-notes._show_dialog', [
            'note' => $note->load(['notable', 'creator:id,name', 'participants', 'nextActionUser:id,name', 'nextActionCompletedBy:id,name']),
            'contextUrl' => $note->isOrganizationNote() ? null : EntityUrl::byType($note->notable_type, (int) $note->notable_id),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', CommunicationNote::class);

        // Ohne Bezug: Schnellerfassung mit Ablage intern oder beim Kunden (Feature 154).
        if (! $request->filled('notable_kind')) {
            $customerId = Sqid::decodeOrNumeric(Customer::class, (string) $request->query('customer', ''));

            return view('communication-notes._quick_dialog', [
                'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
                'customerSqid' => $customerId !== null && Customer::query()->whereKey($customerId)->exists()
                    ? (string) Sqid::encode(Customer::class, $customerId)
                    : null,
                'users' => $this->assignableUsers(),
                'canManageConfidential' => Gate::allows('manageConfidential', CommunicationNote::class),
            ]);
        }

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
        $this->guardPrivate($note);

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

        if ($request->has('storage')) {
            return $this->storeFromQuickCapture($request);
        }

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
        $this->guardPrivate($note);

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
        $this->guardPrivate($note);

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
        // Ohne diese Zeile konnte confidential.manage eine fremde PRIVATE Notiz
        // auf „intern vertraulich" stellen und sie danach lesen
        // (Sicherheitsaudit 2026-09-17, authz-note-1).
        $this->guardPrivate($note);

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
        $this->guardPrivate($note);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->completeFollowup($note, $actor);

        return redirect()
            ->back()
            ->with('success', __('communication.flash.followup_completed'))
            ->withFragment('communication-note-' . $note->id);
    }

    /** Notiz → Wissensartikel-Entwurf mit Herkunftsverweis (MVP-813). */
    public function convertToKnowledge(CommunicationNote $note, NodeConversionService $conversions): RedirectResponse {
        Gate::authorize('view', $note);
        $this->guardPrivate($note);

        /** @var User $actor */
        $actor = Auth::user();
        try {
            $reference = $conversions->convertNoteToKnowledgeArticle($note, $actor);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', ErrorText::for($e));
        }

        return redirect()
            ->route('knowledge.show', Sqid::encode(\App\Models\KnowledgeArticle::class, $reference->target_id))
            ->with($reference->wasRecentlyCreated ? 'success' : 'info', (string) __($reference->wasRecentlyCreated ? 'communication.convert.flash.created' : 'communication.convert.flash.existing'));
    }

    public function destroy(Request $request, CommunicationNote $note): RedirectResponse {
        Gate::authorize('delete', $note);
        $this->guardPrivate($note);

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
     * Schnellerfassung ohne Bezug (Feature 154): Ablage bei der aktuellen
     * Organisation — nie aus dem Request — oder bei genau einem Kunden. Die
     * Kundenzuordnung setzt nie die Kundensichtbarkeit.
     */
    private function storeFromQuickCapture(Request $request): RedirectResponse {
        $data = $this->validateNote($request, includeNotable: false, quickCapture: true);

        $type = CommunicationNoteType::from((string) $data['type']);
        $direction = $type->isInternalByNature()
            ? CommunicationDirection::Internal
            : CommunicationDirection::tryFrom((string) ($data['direction'] ?? ''));
        if ($type === CommunicationNoteType::Call && ! in_array($direction, [CommunicationDirection::Inbound, CommunicationDirection::Outbound], true)) {
            throw ValidationException::withMessages(['direction' => (string) __('communication.error.call_requires_external_direction')]);
        }
        if ($direction === null) {
            throw ValidationException::withMessages(['direction' => (string) __('communication.error.direction_required')]);
        }
        if (! empty($data['confidential'])) {
            Gate::authorize('manageConfidential', CommunicationNote::class);
        }

        $notable = $data['storage'] === self::STORAGE_CUSTOMER
            ? Customer::query()->findOrFail((int) $data['customer_id'])
            : $this->currentOrganization();

        /** @var User $creator */
        $creator = Auth::user();

        $note = $this->service->create($notable, $creator, [
            ...$this->serviceAttributes([...$data, 'direction' => $direction->value]),
            'visibility' => CommunicationVisibility::Internal->value,
        ]);

        return redirect()
            ->back()
            ->with('success', __('communication.flash.created'))
            ->withFragment('communication-note-' . $note->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNote(Request $request, bool $includeNotable, bool $quickCapture = false): array {
        // Sqid-Input dekodieren (numerischer Fallback für Alt-Clients).
        if ($request->filled('next_action_user_id')) {
            $request->merge(['next_action_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('next_action_user_id'))]);
        }
        if ($quickCapture && $request->filled('customer_id')) {
            $request->merge(['customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id'))]);
        }

        $rules = [
            'type' => ['required', 'string', 'in:' . implode(',', array_column(CommunicationNoteType::cases(), 'value'))],
            'direction' => ['required', 'string', 'in:' . implode(',', array_column(CommunicationDirection::cases(), 'value'))],
            'occurred_at' => ['required', 'date', new \App\Rules\TimestampRange()],
            'subject' => ['required', 'string', 'min:3', 'max:180'],
            'body' => ['required', 'string', 'max:8000'],
            'result' => ['nullable', 'string', 'max:8000'],
            'next_action' => ['nullable', 'string', 'max:180'],
            'next_action_due_at' => ['nullable', 'date', 'required_with:next_action_user_id', new \App\Rules\TimestampRange()],
            'next_action_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'visibility' => ['nullable', 'string', 'in:' . implode(',', array_column(CommunicationVisibility::cases(), 'value'))],
            'confidential' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
            'participants' => ['nullable', 'array', 'max:25'],
            'participants.*.name' => ['nullable', 'string', 'max:120'],
            'participants.*.role' => ['nullable', 'string', 'max:40'],
            'participants.*.party' => ['nullable', 'string', 'in:' . implode(',', array_column(ParticipantParty::cases(), 'value'))],
            'participants.*.user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
        ];

        if ($includeNotable) {
            $rules['notable_kind'] = ['required', 'string', 'in:' . implode(',', array_keys(self::NOTABLE_MAP))];
            $rules['notable_id'] = ['required', 'string'];
        }

        if ($quickCapture) {
            // Richtung ergänzt storeFromQuickCapture() je nach Art; eine Sichtbarkeit nimmt die Schnellerfassung nicht an.
            $rules['direction'] = ['nullable', 'string', 'in:' . implode(',', array_column(CommunicationDirection::cases(), 'value'))];
            $rules['storage'] = ['required', 'string', 'in:' . self::STORAGE_INTERNAL . ',' . self::STORAGE_CUSTOMER];
            $rules['customer_id'] = ['nullable', 'required_if:storage,' . self::STORAGE_CUSTOMER, 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')];
            unset($rules['visibility']);
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
            // Nur wenn das Formular das Feld trägt — sonst blieben Schlagwörter
            // aus anderen Eingabewegen beim Speichern auf der Strecke.
            ...(array_key_exists('tags', $data) ? ['tags' => (string) ($data['tags'] ?? '')] : []),
        ];
    }

    /**
     * @return array{0: string, 1: Model}
     */
    /**
     * Private Notizen (MVP-789) gehören ihrer Verfasserin — der Admin-Bypass
     * des Gates darf sie nicht öffnen; deshalb hier ausdrücklich, nicht nur
     * in der Policy.
     */
    private function guardPrivate(CommunicationNote $note): void {
        /** @var User $user */
        $user = Auth::user();
        abort_if($note->isPrivate() && (int) $note->created_by_user_id !== (int) $user->id, 404);
    }

    /** @return array{string, Model} Art und aufgeloestes Zielobjekt. */
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
