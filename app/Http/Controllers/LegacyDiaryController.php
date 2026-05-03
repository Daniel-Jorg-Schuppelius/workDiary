<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Http\Requests\SaveLegacyDiaryEntryRequest;
use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use App\Models\Legacy\LegacyUser;
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
        $sortableColumns = [
            'id' => 'id',
            'status' => 'gelesen',
            'von' => 'von',
            'bis' => 'bis',
            'aktuell' => 'aktuell',
        ];

        $sort = (string) $request->query('sort', 'aktuell');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = $sortableColumns[$sort] ?? $sortableColumns['aktuell'];

        $query = LegacyDiaryEntry::query()
            ->select(['id', 'user', 'von', 'bis', 'gelesen', 'aktuell', 'inhalt', 'antwort'])
            ->with('author:id,uname')
            ->orderBy($sortColumn, $dir);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('gelesen', (int) $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('von', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('bis', '<=', $request->to);
        }

        if ($request->boolean('mine') && Auth::user()?->legacy_user_id) {
            $query->where('user', (int) Auth::user()->legacy_user_id);
        }

        $entries = $query->paginate(20)->withQueryString();

        $counts = LegacyDiaryEntry::query()->selectRaw(
            'COUNT(*) as cnt_all,' .
                'SUM(gelesen = 2) as cnt_open,' .
                'SUM(gelesen = 3) as cnt_alert,' .
                'SUM(gelesen = -1) as cnt_done'
        )->first()?->getAttributes() ?? [];

        return view('legacy.diary.index', [
            'entries' => $entries,
            'counts' => [
                'all' => (int) ($counts['cnt_all'] ?? 0),
                'open' => (int) ($counts['cnt_open'] ?? 0),
                'alert' => (int) ($counts['cnt_alert'] ?? 0),
                'done' => (int) ($counts['cnt_done'] ?? 0),
            ],
            'filters' => $request->only('status', 'from', 'to', 'mine', 'sort', 'dir'),
            'sort' => array_key_exists($sort, $sortableColumns) ? $sort : 'aktuell',
            'dir' => $dir,
        ]);
    }

    public function week(Request $request, LegacyWeekCalendarService $calendar): View {
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
        ]);
    }

    public function create(): View|\Illuminate\Http\Response {
        $data = $this->formData(null, false);

        if (request()->boolean('dialog')) {
            return response(view('legacy.diary._form_dialog', $data));
        }

        return view('legacy.diary.form', $data);
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

        if (request()->boolean('dialog')) {
            return response(view('legacy.diary._form_dialog', $data));
        }

        return view('legacy.diary.form', $data);
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
