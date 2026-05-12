<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Http\Requests\SaveLegacyDiaryEntryRequest;
use App\Services\HolidayService;
use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use App\Models\Legacy\LegacyUser;
use App\Models\User;
use App\Models\Vacation;
use App\Support\LegacyRoleResolver;
use App\Services\Legacy\LegacyWeekCalendarService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class LegacyDiaryController extends Controller {
    use RequiresLegacyAdmin;

    public function index(Request $request): View {
        $tab = (string) $request->query('tab', 'auftraege');
        if (! in_array($tab, ['auftraege', 'bereitschaft', 'notdienst', 'urlaub'], true)) {
            $tab = 'auftraege';
        }

        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId(Auth::user());
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        /** @var \App\Models\User $currentUser */
        $currentUser     = Auth::user();
        $vacationIsAdmin = $currentUser->isAdmin();

        // ── Aufträge ─────────────────────────────────────────────────────────
        $sortableColumns = ['id' => 'id', 'status' => 'gelesen', 'von' => 'von', 'bis' => 'bis', 'aktuell' => 'aktuell'];
        $sort = (string) $request->query('sort', 'aktuell');
        $dir  = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = $sortableColumns[$sort] ?? $sortableColumns['aktuell'];

        $diaryQuery = LegacyDiaryEntry::query()
            ->select(['id', 'user', 'von', 'bis', 'gelesen', 'aktuell', 'inhalt', 'antwort'])
            ->with('author:id,uname')
            ->orderBy($sortColumn, $dir);

        if ($request->filled('status') && $request->status !== 'all') {
            $diaryQuery->where('gelesen', (int) $request->status);
        }
        if ($request->filled('from')) {
            $diaryQuery->whereDate('von', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $diaryQuery->whereDate('bis', '<=', $request->to);
        }
        if ($request->boolean('mine') && Auth::user()?->legacy_user_id) {
            $diaryQuery->where('user', (int) Auth::user()->legacy_user_id);
        }

        $countRow = LegacyDiaryEntry::query()->selectRaw(
            'COUNT(*) as cnt_all,' .
                'SUM(gelesen = 2) as cnt_open,' .
                'SUM(gelesen = 3) as cnt_alert,' .
                'SUM(gelesen = -1) as cnt_done'
        )->first()?->getAttributes() ?? [];

        $diaryCounts = [
            'all'   => (int) ($countRow['cnt_all']   ?? 0),
            'open'  => (int) ($countRow['cnt_open']  ?? 0),
            'alert' => (int) ($countRow['cnt_alert'] ?? 0),
            'done'  => (int) ($countRow['cnt_done']  ?? 0),
        ];

        // ── Bereitschaft ─────────────────────────────────────────────────────
        $oncallQuery = LegacyOnCall::query()->with('mitarbeiter:id,uname')->orderBy('von')->orderBy('user');
        if (! $isAdmin && $legacyUserId > 3) {
            $oncallQuery->where('user', $legacyUserId);
        } elseif ($request->filled('user')) {
            $oncallQuery->where('user', (int) $request->user);
        }
        if ($request->filled('from')) {
            $oncallQuery->whereDate('von', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $oncallQuery->whereDate('bis', '<=', $request->to);
        }

        // ── Urlaub ───────────────────────────────────────────────────────────
        $vacationQuery = Vacation::query()
            ->with('user:id,name')
            ->orderByDesc('start_date');

        if (! $vacationIsAdmin) {
            $vacationQuery->where('user_id', $currentUser->id);
        } elseif ($request->filled('user_id')) {
            $vacationQuery->where('user_id', (int) $request->user_id);
        } elseif ($request->boolean('mine')) {
            $vacationQuery->where('user_id', $currentUser->id);
        }
        if ($request->filled('vtype')) {
            $vacationQuery->where('type', $request->vtype);
        }
        if ($request->filled('vstatus')) {
            $vacationQuery->where('status', $request->vstatus);
        }
        if ($request->filled('from')) {
            $vacationQuery->where('end_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $vacationQuery->where('start_date', '<=', $request->to);
        }

        // ── Notdienst ────────────────────────────────────────────────────────
        $notdienstQuery = LegacyNotdienst::query()->with('mitarbeiter:id,uname')->orderBy('von')->orderBy('user');
        if (! $isAdmin && $legacyUserId > 3) {
            $notdienstQuery->where('user', $legacyUserId);
        } elseif ($request->filled('user')) {
            $notdienstQuery->where('user', (int) $request->user);
        }
        if ($request->filled('from')) {
            $notdienstQuery->whereDate('von', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $notdienstQuery->whereDate('bis', '<=', $request->to);
        }

        $today = now()->toDateString();

        $oncallCounts = [
            'all'      => (clone $oncallQuery)->count(),
            'today'    => (clone $oncallQuery)->whereDate('von', '<=', $today)->whereDate('bis', '>=', $today)->count(),
            'upcoming' => (clone $oncallQuery)->whereDate('von', '>', $today)->count(),
            'past'     => (clone $oncallQuery)->whereDate('bis', '<', $today)->count(),
        ];
        $notdienstCounts = [
            'all'      => (clone $notdienstQuery)->count(),
            'today'    => (clone $notdienstQuery)->whereDate('von', '<=', $today)->whereDate('bis', '>=', $today)->count(),
            'upcoming' => (clone $notdienstQuery)->whereDate('von', '>', $today)->count(),
            'past'     => (clone $notdienstQuery)->whereDate('bis', '<', $today)->count(),
        ];

        $tabCounts = [
            'auftraege'    => $diaryCounts['all'],
            'bereitschaft' => $oncallCounts['all'],
            'notdienst'    => $notdienstCounts['all'],
            'urlaub'       => (clone $vacationQuery)->count(),
        ];

        $vacationKpis = [
            'total'    => $tabCounts['urlaub'],
            'pending'  => (clone $vacationQuery)->where('status', Vacation::STATUS_PENDING)->count(),
            'approved' => (clone $vacationQuery)->where('status', Vacation::STATUS_APPROVED)
                ->where('end_date', '>=', now()->startOfYear())->count(),
            'rejected' => (clone $vacationQuery)->where('status', Vacation::STATUS_REJECTED)->count(),
        ];

        return view('legacy.diary.index', [
            'tab'             => $tab,
            'isAdmin'         => $isAdmin,
            'legacyUserId'    => $legacyUserId,
            'users'           => $this->legacyUsersForSelect(),
            'tabCounts'       => $tabCounts,
            // Aufträge
            'entries'         => $diaryQuery->paginate(20, ['*'], 'dpage')->withQueryString(),
            'diaryCounts'     => $diaryCounts,
            'filters'         => $request->only('status', 'from', 'to', 'mine', 'sort', 'dir', 'user', 'zeitpunkt', 'vtype', 'vstatus', 'user_id'),
            'vacations'       => $vacationQuery->paginate(15, ['*'], 'vpage')->withQueryString(),
            'vacationKpis'    => $vacationKpis,
            'vacationIsAdmin' => $vacationIsAdmin,
            'vacationUsers'   => $vacationIsAdmin ? User::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'sort'            => array_key_exists($sort, $sortableColumns) ? $sort : 'aktuell',
            'dir'             => $dir,
            // Bereitschaft
            'oncallItems'     => $oncallQuery->paginate(30, ['*'], 'opage')->withQueryString(),
            'oncallCounts'    => $oncallCounts,
            // Notdienst
            'notdienstItems'  => $notdienstQuery->paginate(30, ['*'], 'npage')->withQueryString(),
            'notdienstCounts' => $notdienstCounts,
        ]);
    }

    public function week(Request $request, LegacyWeekCalendarService $calendar, HolidayService $holidays): View {
        $weekOffset   = (int) $request->query('week', 0);
        $weekDate     = trim((string) $request->query('week_date', ''));
        $legacyUserId = (int) (Auth::user()->legacy_user_id ?? 0);
        $isAdmin      = $legacyUserId > 0 && $legacyUserId <= 3;

        ['monday' => $monday, 'sunday' => $sunday, 'weekOffset' => $weekOffset, 'selectedWeek' => $selectedWeek] = $calendar->resolveWindow($weekOffset, $weekDate);

        $users = ($legacyUserId === 0 || $isAdmin)
            ? LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname'])
            : LegacyUser::query()->where('id', $legacyUserId)->get(['id', 'uname']);

        $allEntries = LegacyDiaryEntry::query()
            ->select(['id', 'user', 'von', 'bis', 'inhalt', 'gelesen', 'aktuell'])
            ->where('bis', '>', $monday->copy()->startOfDay())
            ->where('von', '<', $sunday)
            ->get();

        $oncalls = LegacyOnCall::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        $notdiensts = LegacyNotdienst::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        [
            'entriesByUserDay' => $entriesByUserDay,
            'oncallByUserDay' => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
        ] = $calendar->buildWeekMaps($allEntries, $oncalls, $notdiensts);

        return view('legacy.diary.week', [
            'users'              => $users,
            'monday'             => $monday,
            'sunday'             => $sunday,
            'weekOffset'         => $weekOffset,
            'selectedWeek'       => $selectedWeek,
            'isAdmin'            => $isAdmin,
            'legacyUserId'       => $legacyUserId,
            'entriesByUserDay'   => $entriesByUserDay,
            'oncallByUserDay'    => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
            'holidays'           => $holidays,
        ]);
    }

    public function create(): View|\Illuminate\Http\Response {
        $data = $this->formData(null, false);

        return response(view('legacy.diary._form_dialog', $data));
    }

    public function store(SaveLegacyDiaryEntryRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse {
        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);

        $data = $request->validated();

        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        if ($legacyUserId <= 0) {
            if ($request->hasHeader('X-Entry-Dialog')) {
                return response()->json(['errors' => ['inhalt' => 'Kein Legacy-Benutzerkonto verknuepft. Bitte Admin kontaktieren.']], 422);
            }

            return back()->withErrors([
                'inhalt' => 'Kein Legacy-Benutzerkonto verknuepft. Bitte Admin kontaktieren.',
            ])->withInput();
        }

        $entryUserId = $isAdmin
            ? (int) ($data['user'] ?? $legacyUserId)
            : $legacyUserId;

        $entry = LegacyDiaryEntry::query()->create($this->entryPayload($data, $entryUserId, $request));

        $this->sendLegacySmsMail($entry);

        if ($request->hasHeader('X-Entry-Dialog')) {
            return response()->json(['redirect' => route('legacy.diary.index')]);
        }

        return redirect()->route('legacy.diary.show', $entry)->with('success', 'Legacy-Eintrag gespeichert.');
    }

    public function show(LegacyDiaryEntry $entry): View|\Illuminate\Http\Response {
        $entry->load('author:id,uname');

        if (request()->boolean('dialog')) {
            return response(view('legacy.diary._show_dialog', ['entry' => $entry]));
        }

        return view('legacy.diary.show', [
            'entry' => $entry,
        ]);
    }

    public function edit(LegacyDiaryEntry $entry): View|\Illuminate\Http\Response {
        $this->ensureCanModify($entry);

        $data = $this->formData($entry, true);

        return response(view('legacy.diary._form_dialog', $data));
    }

    public function update(SaveLegacyDiaryEntryRequest $request, LegacyDiaryEntry $entry): RedirectResponse|\Illuminate\Http\JsonResponse {
        $this->ensureCanModify($entry);

        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        $data = $request->validated();

        $entryUserId = $isAdmin
            ? (int) ($data['user'] ?? $entry->user)
            : $legacyUserId;

        $entry->update($this->entryPayload($data, $entryUserId, $request));

        $this->sendLegacySmsMail($entry->fresh(['author']));

        if ($request->hasHeader('X-Entry-Dialog')) {
            return response()->json(['redirect' => route('legacy.diary.index')]);
        }

        return redirect()->route('legacy.diary.show', $entry)->with('success', 'Legacy-Eintrag aktualisiert.');
    }

    public function destroy(LegacyDiaryEntry $entry): RedirectResponse {
        $this->ensureCanModify($entry);

        $entry->delete();

        return redirect()->route('legacy.diary.index')->with('success', 'Legacy-Eintrag geloescht.');
    }

    private function ensureCanModify(LegacyDiaryEntry $entry): void {
        $authUser = Auth::user();

        if (LegacyRoleResolver::isAdmin($authUser)) {
            return;
        }

        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        abort_if($legacyUserId <= 0 || (int) $entry->user !== $legacyUserId, 403);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function entryPayload(array $data, int $entryUserId, Request $request): array {
        return [
            'aktuell' => now(),
            'user' => $entryUserId,
            'von' => $data['von'] ?? null,
            'bis' => $data['bis'] ?? null,
            'inhalt' => $data['inhalt'],
            'antwort' => $data['antwort'] ?? null,
            'gelesen' => (int) $data['gelesen'],
            'sms' => $request->boolean('sms') ? 'j' : '',
        ];
    }

    /** @return array<string, mixed> */
    private function formData(?LegacyDiaryEntry $entry, bool $isEdit): array {
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        return [
            'entry' => $entry,
            'isEdit' => $isEdit,
            'isAdmin' => $isAdmin,
            'users' => $isAdmin ? $this->legacyUsersForSelect() : collect(),
        ];
    }

    private function sendLegacySmsMail(?LegacyDiaryEntry $entry): void {
        if (! $entry || (string) $entry->sms !== 'j') {
            return;
        }

        $entry->loadMissing('author:id,uname,email');

        $mail = (string) optional($entry->author)->email;

        if ($mail === '') {
            return;
        }

        $subject = 'WorkDiary Legacy Hinweis #' . $entry->id;
        $body = "Ein neuer Legacy-Eintrag wurde gespeichert.\n\n"
            . 'ID: ' . $entry->id . "\n"
            . 'Mitarbeiter: ' . (optional($entry->author)->uname ?? 'Unbekannt') . "\n"
            . 'Status: ' . $entry->statusLabel() . "\n"
            . 'Von: ' . ($entry->von?->format('d.m.Y H:i') ?? '-') . "\n"
            . 'Bis: ' . ($entry->bis?->format('d.m.Y H:i') ?? '-') . "\n\n"
            . 'Inhalt:' . "\n"
            . (string) $entry->inhalt;

        Mail::raw($body, function ($message) use ($mail, $subject): void {
            $message->to($mail)->subject($subject);
        });
    }
}
