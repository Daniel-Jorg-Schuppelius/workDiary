<?php

namespace App\Http\Controllers;

use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use App\Models\Legacy\LegacyUser;
use App\Support\LegacyRoleResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class LegacyDiaryController extends Controller {
    public function index(Request $request): View {
        $sortableColumns = [
            'id' => 'id',
            'status' => 'gelesen',
            'von' => 'von',
            'bis' => 'bis',
        ];

        $sort = (string) $request->query('sort', 'von');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = $sortableColumns[$sort] ?? $sortableColumns['von'];

        $query = LegacyDiaryEntry::query()->with('author:id,uname')->orderBy($sortColumn, $dir);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('gelesen', (int) $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('von', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('bis', '<=', $request->to);
        }

        $zeitpunkt = (int) $request->query('zeitpunkt', 0);
        $today = Carbon::now()->startOfDay();
        if ($zeitpunkt === 1) {
            $query->where('bis', '>=', $today);
        } elseif ($zeitpunkt === 2) {
            $query->where('von', '<=', $today);
        }

        if ($request->boolean('mine') && Auth::user()?->legacy_user_id) {
            $query->where('user', (int) Auth::user()->legacy_user_id);
        }

        $entries = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => LegacyDiaryEntry::count(),
            'open' => LegacyDiaryEntry::where('gelesen', 2)->count(),
            'alert' => LegacyDiaryEntry::where('gelesen', 3)->count(),
            'done' => LegacyDiaryEntry::where('gelesen', -1)->count(),
        ];

        return view('legacy.diary.index', [
            'entries' => $entries,
            'counts' => $counts,
            'filters' => $request->only('status', 'from', 'to', 'mine', 'zeitpunkt', 'sort', 'dir'),
            'sort' => array_key_exists($sort, $sortableColumns) ? $sort : 'von',
            'dir' => $dir,
        ]);
    }

    public function week(Request $request): View {
        $weekOffset   = (int) $request->query('week', 0);
        $weekDate     = trim((string) $request->query('week_date', ''));
        $legacyUserId = (int) (Auth::user()?->legacy_user_id ?? 0);
        $isAdmin      = $legacyUserId > 0 && $legacyUserId <= 3;

        $baseMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $monday = $baseMonday->copy()->addWeeks($weekOffset);
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekDate, $matches) === 1) {
            $isoYear = (int) $matches[1];
            $isoWeek = (int) $matches[2];
            $monday = Carbon::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
            $weekOffset = $baseMonday->diffInWeeks($monday, false);
        }
        $sunday = $monday->copy()->addDays(6)->endOfDay();

        $users = ($legacyUserId === 0 || $isAdmin)
            ? LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname'])
            : LegacyUser::query()->where('id', $legacyUserId)->get(['id', 'uname']);

        $allEntries = LegacyDiaryEntry::query()
            ->where('bis', '>', $monday->copy()->startOfDay())
            ->where('von', '<', $sunday)
            ->get();

        $oncalls = LegacyOnCall::query()
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        $notdiensts = LegacyNotdienst::query()
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        // Pre-index entries by [userId][dateKey]
        $entriesByUserDay = [];
        foreach ($allEntries as $entry) {
            if (! $entry->von || ! $entry->bis) {
                continue;
            }
            $uid    = (int) $entry->user;
            $cursor = $entry->von->copy()->startOfDay();
            $endDay = $entry->bis->copy()->startOfDay();
            while ($cursor->lte($endDay)) {
                $entriesByUserDay[$uid][$cursor->format('Y-m-d')][] = $entry;
                $cursor->addDay();
            }
        }

        // Pre-index Bereitschaft by [userId][dateKey]
        $oncallByUserDay = [];
        foreach ($oncalls as $oc) {
            $uid    = (int) $oc->user;
            $cursor = Carbon::parse($oc->von);
            $end    = Carbon::parse($oc->bis);
            while ($cursor->lte($end)) {
                $oncallByUserDay[$uid][$cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        // Pre-index Notdienst by [userId][dateKey]
        $notdienstByUserDay = [];
        foreach ($notdiensts as $nd) {
            $uid    = (int) $nd->user;
            $cursor = Carbon::parse($nd->von);
            $end    = Carbon::parse($nd->bis);
            while ($cursor->lte($end)) {
                $notdienstByUserDay[$uid][$cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        return view('legacy.diary.week', [
            'users'              => $users,
            'monday'             => $monday,
            'sunday'             => $sunday,
            'weekOffset'         => $weekOffset,
            'selectedWeek'       => $monday->format('o-\\WW'),
            'isAdmin'            => $isAdmin,
            'legacyUserId'       => $legacyUserId,
            'entriesByUserDay'   => $entriesByUserDay,
            'oncallByUserDay'    => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
        ]);
    }

    public function create(): View {
        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);

        return view('legacy.diary.form', [
            'entry' => null,
            'isEdit' => false,
            'isAdmin' => $isAdmin,
            'users' => $isAdmin
                ? LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname'])
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);

        $data = $request->validate([
            'inhalt' => ['required', 'string', 'max:65535'],
            'antwort' => ['nullable', 'string', 'max:65535'],
            'gelesen' => ['required', 'integer', 'in:-1,1,2,3'],
            'von' => ['nullable', 'date'],
            'bis' => ['nullable', 'date', 'after_or_equal:von'],
            'sms' => ['nullable', 'in:j'],
            'user' => ['nullable', 'integer', 'min:4', 'exists:legacy.user,id'],
        ]);

        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        if ($legacyUserId <= 0) {
            return back()->withErrors([
                'inhalt' => 'Kein Legacy-Benutzerkonto verknüpft. Bitte Admin kontaktieren.',
            ])->withInput();
        }

        $entryUserId = $isAdmin
            ? (int) ($data['user'] ?? $legacyUserId)
            : $legacyUserId;

        $entry = LegacyDiaryEntry::query()->create([
            'aktuell' => now(),
            'user' => $entryUserId,
            'von' => $data['von'] ?? null,
            'bis' => $data['bis'] ?? null,
            'inhalt' => $data['inhalt'],
            'antwort' => $data['antwort'] ?? null,
            'gelesen' => (int) $data['gelesen'],
            'sms' => $request->has('sms') ? 'j' : '',
        ]);

        $this->sendLegacySmsMail($entry);

        return redirect()->route('legacy.diary.show', $entry)->with('success', __('Legacy-Eintrag gespeichert.'));
    }

    public function show(LegacyDiaryEntry $entry): View {
        $entry->load('author:id,uname');

        return view('legacy.diary.show', [
            'entry' => $entry,
        ]);
    }

    public function edit(LegacyDiaryEntry $entry): View {
        $this->ensureCanModify($entry);

        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        return view('legacy.diary.form', [
            'entry' => $entry,
            'isEdit' => true,
            'isAdmin' => $isAdmin,
            'users' => $isAdmin
                ? LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname'])
                : collect(),
        ]);
    }

    public function update(Request $request, LegacyDiaryEntry $entry): RedirectResponse {
        $this->ensureCanModify($entry);

        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        $data = $request->validate([
            'inhalt' => ['required', 'string', 'max:65535'],
            'antwort' => ['nullable', 'string', 'max:65535'],
            'gelesen' => ['required', 'integer', 'in:-1,1,2,3'],
            'von' => ['nullable', 'date'],
            'bis' => ['nullable', 'date', 'after_or_equal:von'],
            'sms' => ['nullable', 'in:j'],
            'user' => ['nullable', 'integer', 'min:4', 'exists:legacy.user,id'],
        ]);

        $entryUserId = $isAdmin
            ? (int) ($data['user'] ?? $entry->user)
            : $legacyUserId;

        $entry->update([
            'aktuell' => now(),
            'user' => $entryUserId,
            'von' => $data['von'] ?? null,
            'bis' => $data['bis'] ?? null,
            'inhalt' => $data['inhalt'],
            'antwort' => $data['antwort'] ?? null,
            'gelesen' => (int) $data['gelesen'],
            'sms' => $request->has('sms') ? 'j' : '',
        ]);

        $this->sendLegacySmsMail($entry->fresh(['author']));

        return redirect()->route('legacy.diary.show', $entry)->with('success', __('Legacy-Eintrag aktualisiert.'));
    }

    public function destroy(LegacyDiaryEntry $entry): RedirectResponse {
        $this->ensureCanModify($entry);

        $entry->delete();

        return redirect()->route('legacy.diary.index')->with('success', __('Legacy-Eintrag gelöscht.'));
    }

    public function bulk(Request $request): RedirectResponse {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'string', 'in:status_open,status_alert,status_progress,status_done,delete'],
        ]);

        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        $query = LegacyDiaryEntry::query()->whereIn('id', $data['ids']);
        if (! $isAdmin) {
            if ($legacyUserId <= 0) {
                abort(403);
            }
            $query->where('user', $legacyUserId);
        }

        $entries = $query->get();
        $affected = 0;

        if ($data['action'] === 'delete') {
            foreach ($entries as $entry) {
                $entry->delete();
                $affected++;
            }
        } else {
            $statusMap = [
                'status_open' => 2,
                'status_alert' => 3,
                'status_progress' => 1,
                'status_done' => -1,
            ];
            $newStatus = $statusMap[$data['action']];
            foreach ($entries as $entry) {
                $entry->update([
                    'gelesen' => $newStatus,
                    'aktuell' => now(),
                ]);
                $affected++;
            }
        }

        $skipped = count($data['ids']) - $affected;
        $msg = trans_choice('{1} :count Eintrag aktualisiert.|[2,*] :count Einträge aktualisiert.', $affected, ['count' => $affected]);
        if ($skipped > 0) {
            $msg .= ' ' . __(':count übersprungen (keine Berechtigung).', ['count' => $skipped]);
        }

        return redirect()->back()->with('success', $msg);
    }

    private function ensureCanModify(LegacyDiaryEntry $entry): void {
        $authUser = Auth::user();

        if (LegacyRoleResolver::isAdmin($authUser)) {
            return;
        }

        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        abort_if($legacyUserId <= 0 || (int) $entry->user !== $legacyUserId, 403);
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
