<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Event\{EventStatus, EventType, EventVisibility, ParticipantRole, ParticipantStatus};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{Customer, Event, EventCategory, Room, User};
use App\Services\Event\EventService;
use App\Support\{LookupCache, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class EventController extends Controller {
    use ResolvesGlobalDateRange;

    private const ALLOWED_SORTS = ['title', 'event_type', 'started_at', 'status'];

    public function __construct(
        private readonly EventService $events,
    ) {}

    // ── Index / Calendar ────────────────────────────────────────────────────

    public function index(Request $request): View {
        Gate::authorize('viewAny', Event::class);
        /** @var User $auth */
        $auth = Auth::user();

        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'started_at';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $rawCategoryId = $request->query('category_id');
        $categoryId = Sqid::decodeOrNumeric(EventCategory::class, $rawCategoryId);

        $view = $request->query('view', 'list');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'event_type' => $request->query('event_type'),
            'status' => $request->query('status'),
            'visibility' => $request->query('visibility'),
            'category_id' => $categoryId,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'only_mandatory' => $request->boolean('only_mandatory'),
        ];

        $query = Event::query()
            ->with(['category', 'responsibleUser', 'customer', 'rooms'])
            ->when($filters['q'] !== '', fn($q) => $q->where(function ($w) use ($filters): void {
                $w->where('title', 'like', '%' . $filters['q'] . '%')
                    ->orWhere('topic', 'like', '%' . $filters['q'] . '%');
            }))
            ->when($filters['event_type'], fn($q) => $q->where('event_type', $filters['event_type']))
            ->when($filters['status'], fn($q) => $q->where('status', $filters['status']))
            ->when($filters['visibility'], fn($q) => $q->where('visibility', $filters['visibility']))
            ->when($filters['category_id'], fn($q) => $q->where('category_id', $filters['category_id']))
            ->when($filters['from'], fn($q) => $q->where('ended_at', '>=', $filters['from']))
            ->when($filters['to'], fn($q) => $q->where('started_at', '<=', $filters['to']))
            ->when($filters['only_mandatory'], fn($q) => $q->where('is_mandatory', true))
            ->orderBy($sort, $dir);

        $events = $query->paginate(25)->withQueryString();

        $counts = [
            'upcoming' => Event::query()->where('started_at', '>=', now())->whereNull('cancelled_at')->count(),
            'today' => Event::query()->whereDate('started_at', today())->count(),
            'mandatory' => Event::query()->where('is_mandatory', true)->where('started_at', '>=', now())->count(),
            'total' => Event::query()->count(),
        ];

        return view('events.index', [
            'events' => $events,
            'counts' => $counts,
            'filters' => $filters,
            'auth' => $auth,
            'view' => $view,
            'sort' => $sort,
            'dir' => $dir,
            'categories' => EventCategory::query()->active()->orderBy('name')->get(),
            'types' => EventType::options(),
            'statuses' => EventStatus::options(),
            'visibilities' => EventVisibility::options(),
        ]);
    }

    public function calendar(Request $request, \App\Services\HolidayService $holidays): View {
        Gate::authorize('viewAny', Event::class);

        // Globaler Header-Zeitraum (analog Schichtplan). Bei Mehrmonats-Range
        // gibt es Monats-Tabs; aktive Monat per ?activeMonth=Y-m.
        $range     = $this->globalDateRange();
        $rangeFrom = $range['from'];
        $rangeTo   = $range['to'];

        $months         = $this->buildMonthsInRange($rangeFrom, $rangeTo);
        $activeMonthKey = (string) $request->query('activeMonth', '');
        $activeMonth    = collect($months)->firstWhere('key', $activeMonthKey) ?? $months[0];
        $activeMonthKey = $activeMonth['key'];

        $monthStart = CarbonImmutable::create($activeMonth['year'], $activeMonth['month'], 1)
            ?->startOfMonth()
            ?? CarbonImmutable::now()->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $gridStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd   = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);

        $events = Event::query()
            ->with(['category', 'rooms'])
            ->whereBetween('started_at', [$gridStart, $gridEnd])
            ->orderBy('started_at')
            ->get();

        $eventsByDay = $events->groupBy(fn(Event $e) => $e->started_at->format('Y-m-d'));

        return view('events.calendar', [
            'monthStart'     => $monthStart,
            'monthEnd'       => $monthEnd,
            'eventsByDay'    => $eventsByDay,
            'holidays'       => $holidays,
            'months'         => $months,
            'activeMonthKey' => $activeMonthKey,
        ]);
    }

    public function show(Event $event): View {
        Gate::authorize('view', $event);
        $event->load(['category', 'responsibleUser', 'customer', 'rooms', 'participants', 'reminders', 'series']);

        return view('events.show', compact('event'));
    }

    // ── Create / Store ──────────────────────────────────────────────────────

    public function create(Request $request): View {
        Gate::authorize('create', Event::class);

        return view('events._form_dialog', $this->formData(null, $request));
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Event::class);
        $data = $this->validateEvent($request);
        $rooms = $this->extractRooms($request);
        $participants = $this->extractParticipants($request);

        try {
            $event = $this->events->create($data, $rooms, $participants);
        } catch (RuntimeException $e) {
            return back()->withErrors(['rooms' => $e->getMessage()])->withInput();
        }

        return redirect()->route('events.show', $event)
            ->with('success', __('Veranstaltung angelegt.'));
    }

    // ── Edit / Update ───────────────────────────────────────────────────────

    public function edit(Event $event, Request $request): View {
        Gate::authorize('update', $event);

        return view('events._form_dialog', $this->formData($event, $request));
    }

    public function update(Request $request, Event $event): RedirectResponse {
        Gate::authorize('update', $event);
        $data = $this->validateEvent($request);
        $rooms = $this->extractRooms($request);
        $participants = $this->extractParticipants($request);

        try {
            $this->events->update($event, $data, $rooms, $participants);
        } catch (RuntimeException $e) {
            return back()->withErrors(['rooms' => $e->getMessage()])->withInput();
        }

        return redirect()->route('events.show', $event)
            ->with('success', __('Veranstaltung aktualisiert.'));
    }

    public function destroy(Event $event): RedirectResponse {
        Gate::authorize('delete', $event);
        $event->delete();

        return redirect()->route('events.index')
            ->with('success', __('Veranstaltung gelöscht.'));
    }

    public function cancel(Request $request, Event $event): RedirectResponse {
        Gate::authorize('cancel', $event);
        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->events->cancel($event, $data['cancel_reason'] ?? null);

        return back()->with('success', __('Veranstaltung abgesagt.'));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateEvent(Request $request): array {
        /** @var User $auth */
        $auth = Auth::user();

        $rawCategoryId = $request->input('category_id');
        $categoryId = Sqid::decodeOrNumeric(EventCategory::class, $rawCategoryId);

        $rawResponsibleUserId = $request->input('responsible_user_id');
        $responsibleUserId = Sqid::decodeOrNumeric(User::class, $rawResponsibleUserId);

        $rawCustomerId = $request->input('customer_id');
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomerId);

        $request->merge([
            'category_id' => $categoryId,
            'responsible_user_id' => $responsibleUserId,
            'customer_id' => $customerId,
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'topic' => ['nullable', 'string', 'max:200'],
            'event_type' => ['required', Rule::enum(EventType::class)],
            'category_id' => ['nullable', 'integer', 'exists:event_categories,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'is_all_day' => ['sometimes', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'visibility' => ['required', Rule::enum(EventVisibility::class)],
            'responsible_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'external_contact_note' => ['nullable', 'string', 'max:255'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'certificate_valid_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'recurrence_rule' => ['nullable', 'string', 'max:1000'],
            'series_until' => ['nullable', 'date'],
            'reminder_overrides' => ['nullable', 'array'],
            'reminder_overrides.*' => ['integer', 'min:0'],
        ]);

        // Pflichtschulungs-Flag separat absichern.
        if (! empty($data['is_mandatory']) && ! Gate::forUser($auth)->allows('manageMandatory', Event::class)) {
            $data['is_mandatory'] = false;
        }

        $data['is_all_day'] ??= false;
        $data['is_mandatory'] ??= false;

        return $data;
    }

    /** @return array<int, array{room_id: int, started_at?: \Illuminate\Support\Carbon|string, ended_at?: \Illuminate\Support\Carbon|string, setup_minutes_before?: int, teardown_minutes_after?: int}> */
    private function extractRooms(Request $request): array {
        $rows = (array) $request->input('rooms', []);
        $out = [];
        foreach ($rows as $row) {
            if (empty($row['room_id'])) {
                continue;
            }

            $roomId = Sqid::decodeOrNumeric(Room::class, $row['room_id']);
            if ($roomId === null) {
                continue;
            }

            $entry = [
                'room_id' => $roomId,
                'setup_minutes_before' => (int) ($row['setup_minutes_before'] ?? 0),
                'teardown_minutes_after' => (int) ($row['teardown_minutes_after'] ?? 0),
            ];
            if (! empty($row['started_at'])) {
                $entry['started_at'] = (string) $row['started_at'];
            }
            if (! empty($row['ended_at'])) {
                $entry['ended_at'] = (string) $row['ended_at'];
            }
            $out[] = $entry;
        }

        return $out;
    }

    /** @return array<int, array{user_id: int, role?: string, status?: string}> */
    private function extractParticipants(Request $request): array {
        $rows = (array) $request->input('participants', []);
        $out = [];
        foreach ($rows as $row) {
            if (empty($row['user_id'])) {
                continue;
            }

            $participantUserId = Sqid::decodeOrNumeric(User::class, $row['user_id']);
            if ($participantUserId === null) {
                continue;
            }

            $out[] = [
                'user_id' => $participantUserId,
                'role' => $row['role'] ?? ParticipantRole::Attendee->value,
                'status' => $row['status'] ?? ParticipantStatus::Invited->value,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function formData(?Event $event, Request $request): array {
        return [
            'event' => $event,
            'isEdit' => $event !== null,
            'types' => EventType::options(),
            'statuses' => EventStatus::options(),
            'visibilities' => EventVisibility::options(),
            'roles' => ParticipantRole::options(),
            'categories' => EventCategory::query()->active()->orderBy('name')->get(),
            'rooms' => Room::query()->active()->orderBy('name')->get(),
            'users' => LookupCache::userDropdown(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'prefillStart' => $request->query('start') ?? '',
            'prefillEnd' => $request->query('end') ?? '',
        ];
    }
}
