<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Safety\{SafetyEventKind, SafetyEventSeverity, SafetyEventStatus};
use App\Enums\User\Permission;
use App\Models\{DiaryEntry, SafetyEvent, User};
use App\Services\Safety\SafetyEventService;
use App\Support\{Sqid, Tz};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Sicherheitsereignis-Register (Feature 013): Liste, Detail, Melde-/Bearbeiten-
 * Dialoge und Statusmaschine. Anlage/Statuswechsel laufen über den
 * SafetyEventService.
 */
class SafetyEventController extends Controller {
    /**
     * Whitelist der erlaubten Subject-Typen für die optionale Verknüpfung.
     *
     * @var array<string, class-string<Model>>
     */
    private const SUBJECT_MAP = [
        'diary' => DiaryEntry::class,
        'asset' => \App\Models\Asset::class,
        'room' => \App\Models\Room::class,
    ];

    public function __construct(
        private readonly SafetyEventService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', SafetyEvent::class);

        $query = SafetyEvent::query()->with(['reporter:id,name'])->latest('occurred_at');

        $kind = (string) $request->query('kind', '');
        if (SafetyEventKind::tryFrom($kind) instanceof SafetyEventKind) {
            $query->where('kind', $kind);
        }
        $status = (string) $request->query('status', '');
        if (SafetyEventStatus::tryFrom($status) instanceof SafetyEventStatus) {
            $query->where('status', $status);
        }
        $severity = (string) $request->query('severity', '');
        if (SafetyEventSeverity::tryFrom($severity) instanceof SafetyEventSeverity) {
            $query->where('severity', $severity);
        }
        if ($request->query('open') === '1') {
            $query->open();
        }

        $events = $query->paginate(30)->withQueryString();

        return view('safety-events.index', [
            'events' => $events,
            'kind' => $kind,
            'status' => $status,
            'severity' => $severity,
            'onlyOpen' => $request->query('open') === '1',
            'canManage' => $this->canManage(),
            'canCreate' => Gate::allows('create', SafetyEvent::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', SafetyEvent::class);

        return view('safety-events._form_dialog', [
            'event' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', SafetyEvent::class);

        $data = $this->validateData($request);

        /** @var User $reporter */
        $reporter = Auth::user();

        $event = $this->service->create($reporter, $data);

        return redirect()
            ->route('safety-events.show', $event)
            ->with('success', __('safety.flash.created'));
    }

    public function show(SafetyEvent $safety_event): View {
        Gate::authorize('view', $safety_event);

        $safety_event->load([
            'reporter:id,name',
            'closer:id,name',
            'attachments',
            'subject',
            'openIssues' => fn($q) => $q->with(['assignee:id,name', 'events'])->latest(),
        ]);

        return view('safety-events.show', [
            'event' => $safety_event,
            'canManage' => Gate::allows('update', $safety_event),
        ]);
    }

    public function edit(SafetyEvent $safety_event): View {
        Gate::authorize('update', $safety_event);

        return view('safety-events._form_dialog', [
            'event' => $safety_event,
        ]);
    }

    public function update(Request $request, SafetyEvent $safety_event): RedirectResponse {
        Gate::authorize('update', $safety_event);

        $data = $this->validateData($request, true);

        $this->service->update($safety_event, $data);

        return redirect()
            ->route('safety-events.show', $safety_event)
            ->with('success', __('safety.flash.updated'));
    }

    public function transition(Request $request, SafetyEvent $safety_event): RedirectResponse {
        Gate::authorize('update', $safety_event);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', SafetyEventStatus::values())],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $target = SafetyEventStatus::from((string) $validated['status']);

        $this->service->transition($safety_event, $target, $actor);

        return redirect()
            ->route('safety-events.show', $safety_event)
            ->with('success', __('safety.flash.status.' . $target->value));
    }

    public function followUp(Request $request, SafetyEvent $safety_event): RedirectResponse {
        Gate::authorize('update', $safety_event);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        $this->service->createFollowUpIssue(
            $safety_event,
            $actor,
            (string) $validated['title'],
            (string) ($validated['description'] ?? ''),
        );

        return redirect()
            ->route('safety-events.show', $safety_event)
            ->with('success', __('safety.flash.followup_created'));
    }

    public function destroy(SafetyEvent $safety_event): RedirectResponse {
        Gate::authorize('delete', $safety_event);

        $this->service->delete($safety_event);

        return redirect()
            ->route('safety-events.index')
            ->with('success', __('safety.flash.deleted'));
    }

    private function canManage(): bool {
        $user = Auth::user();

        return $user instanceof User
            && ($user->isAdmin() || $user->can(Permission::SafetyManage->value));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, bool $isUpdate = false): array {
        $rules = [
            'kind' => ['required', 'string', 'in:' . implode(',', SafetyEventKind::values())],
            'severity' => ['required', 'string', 'in:' . implode(',', SafetyEventSeverity::values())],
            'occurred_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:180'],
            'affected_person' => ['nullable', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'immediate_action' => ['nullable', 'string', 'max:10000'],
            'subject_kind' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::SUBJECT_MAP))],
            'subject_id' => ['nullable', 'string'],
        ];
        if ($isUpdate) {
            $rules['root_cause'] = ['nullable', 'string', 'max:10000'];
        }

        $data = $request->validate($rules);

        $data['occurred_at'] = Tz::parse((string) $data['occurred_at'])->format('Y-m-d H:i:s');

        $subjectKind = (string) ($data['subject_kind'] ?? '');
        if ($subjectKind !== '' && filled($data['subject_id'] ?? null)) {
            $subjectClass = self::SUBJECT_MAP[$subjectKind];
            $subjectId = Sqid::decodeOrNumeric($subjectClass, (string) $data['subject_id']);
            if ($subjectId !== null && $subjectClass::query()->whereKey($subjectId)->exists()) {
                $data['subject_type'] = $subjectClass;
                $data['subject_id'] = $subjectId;
            }
        }
        unset($data['subject_kind']);
        if (! isset($data['subject_type'])) {
            unset($data['subject_id']);
        }

        return $data;
    }
}
