<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyDiaryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\RequiresLegacyAdmin;
use App\Legacy\Http\Requests\SaveLegacyDiaryEntryRequest;
use App\Legacy\Models\{LegacyDiaryEntry, LegacyNotdienst, LegacyOnCall, LegacyUser};
use App\Legacy\Services\{LegacyDashboardService, LegacyWeekCalendarService};
use App\Legacy\Support\{LegacyClock, LegacyRoleResolver, LegacyView};
use App\Models\Platform\User;
use App\Services\Calendar\HolidayService;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Mail};
use Illuminate\View\View;

class LegacyDiaryController extends Controller {
    use RequiresLegacyAdmin;

    /** Die klassische Ansicht blättert nicht wie das Altportal; die Grenze schützt nur vor Riesenseiten. */
    private const int CLASSIC_PER_PAGE = 500;

    public function index(Request $request, LegacyDashboardService $dashboard): View {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        $classic = LegacyView::isClassic($request);

        // Das Altportal zeigt Aufträge und Dienste neueste zuerst.
        if ($classic && ! $request->filled('sort')) {
            $request->query->add(['sort' => 'von', 'dir' => 'desc']);
        }

        $data = $dashboard->buildIndexData($request, $currentUser, $classic ? self::CLASSIC_PER_PAGE : null);
        $data['users'] = $this->legacyUsersForSelect();

        // Urlaub kennt das Altportal nicht; der Reiter bleibt in der neuen Ansicht.
        $view = match (true) {
            ! $classic, $data['tab'] === 'urlaub' => 'legacy.diary.index',
            $data['tab'] === 'auftraege' => 'legacy.classic.diary.index',
            default => 'legacy.classic.diary.duties',
        };

        return view($view, $data);
    }

    public function week(Request $request, LegacyWeekCalendarService $calendar, HolidayService $holidays): View {
        $weekOffset = (int) $request->query('week', 0);
        $weekDate = trim((string) $request->query('week_date', ''));
        /** @var User $authUser */
        $authUser = Auth::user();
        $legacyUserId = (int) ($authUser->legacy_user_id ?? 0);
        $isAdmin = $legacyUserId > 0 && $legacyUserId <= 3;
        $canViewAll = $authUser->canViewAllLegacyData();

        ['monday' => $monday, 'sunday' => $sunday, 'weekOffset' => $weekOffset, 'selectedWeek' => $selectedWeek] = $calendar->resolveWindow($weekOffset, $weekDate);

        $users = $canViewAll
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

        return view(LegacyView::pick($request, 'legacy.diary.week', 'legacy.classic.diary.week'), [
            'users' => $users,
            'monday' => $monday,
            'sunday' => $sunday,
            'weekOffset' => $weekOffset,
            'selectedWeek' => $selectedWeek,
            'isAdmin' => $isAdmin,
            'canViewAll' => $canViewAll,
            'legacyUserId' => $legacyUserId,
            'entriesByUserDay' => $entriesByUserDay,
            'oncallByUserDay' => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
            'holidays' => $holidays,
        ]);
    }

    public function create(Request $request): View|Response {
        // Ein Eintrag gehört einem Altsystem-Nutzer; ohne verknüpftes Legacy-Konto ließe er sich nicht speichern.
        abort_if(LegacyRoleResolver::resolveLegacyUserId(Auth::user()) <= 0, 403, __('Kein Legacy-Benutzerkonto verknüpft. Bitte wenden Sie sich an die Verwaltung.'));

        $data = $this->formData(null, false);

        return response(view(LegacyView::pick($request, 'legacy.diary._form_dialog', 'legacy.classic.diary.form'), $data));
    }

    public function store(SaveLegacyDiaryEntryRequest $request): RedirectResponse|JsonResponse {
        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);

        $data = $request->validated();

        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        if ($legacyUserId <= 0) {
            if ($request->hasHeader('X-Entry-Dialog')) {
                return response()->json(['errors' => ['inhalt' => __('Kein Legacy-Benutzerkonto verknüpft. Bitte wenden Sie sich an die Verwaltung.')]], 422);
            }

            return back()->withErrors([
                'inhalt' => __('Kein Legacy-Benutzerkonto verknüpft. Bitte wenden Sie sich an die Verwaltung.'),
            ])->withInput();
        }

        $entryUserId = $isAdmin
            ? (int) ($data['user'] ?? $legacyUserId)
            : $legacyUserId;

        $entry = LegacyDiaryEntry::query()->create($this->entryPayload($data, $entryUserId));

        $this->sendLegacySmsMail($entry, $legacyUserId);

        if ($request->hasHeader('X-Entry-Dialog')) {
            return response()->json(['redirect' => route('legacy.diary.index')]);
        }

        return redirect()->route('legacy.diary.show', $entry)->with('success', __('Legacy-Eintrag gespeichert.'));
    }

    public function show(Request $request, LegacyDiaryEntry $entry): View|Response {
        abort_unless(LegacyArchiveController::mayViewEntry((int) $entry->user), 404);
        $entry->load('author:id,uname');

        if ($request->boolean('dialog')) {
            return response(view('legacy.diary._show_dialog', ['entry' => $entry]));
        }

        return view(LegacyView::pick($request, 'legacy.diary.show', 'legacy.classic.diary.show'), [
            'entry' => $entry,
            'isAdmin' => LegacyRoleResolver::isAdmin(Auth::user()),
            'isOwn' => (int) $entry->user === LegacyRoleResolver::resolveLegacyUserId(Auth::user()),
        ]);
    }

    public function edit(Request $request, LegacyDiaryEntry $entry): View|Response {
        $this->ensureCanModify($entry);

        $data = $this->formData($entry, true);

        return response(view(LegacyView::pick($request, 'legacy.diary._form_dialog', 'legacy.classic.diary.form'), $data));
    }

    public function update(SaveLegacyDiaryEntryRequest $request, LegacyDiaryEntry $entry): RedirectResponse|JsonResponse {
        $this->ensureCanModify($entry);

        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        $data = $request->validated();

        $entryUserId = $isAdmin
            ? (int) ($data['user'] ?? $entry->user)
            : $legacyUserId;

        $entry->update($this->entryPayload($data, $entryUserId));

        $this->sendLegacySmsMail($entry->fresh(['author']), $legacyUserId);

        if ($request->hasHeader('X-Entry-Dialog')) {
            return response()->json(['redirect' => route('legacy.diary.index')]);
        }

        return redirect()->route('legacy.diary.show', $entry)->with('success', __('Legacy-Eintrag aktualisiert.'));
    }

    public function destroy(Request $request, LegacyDiaryEntry $entry): RedirectResponse {
        $this->ensureCanModify($entry);

        $entry->delete();

        return $this->backToDiary($request)->with('success', __('Legacy-Eintrag gelöscht.'));
    }

    public function bulk(Request $request): RedirectResponse {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:status_open,status_alert,status_progress,status_done,delete'],
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $authUser = Auth::user();
        $isAdmin = LegacyRoleResolver::isAdmin($authUser);
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($authUser);

        // Gleiche Berechtigungsgrenze wie ensureCanModify: Admin alles,
        // sonst nur eigene Eintraege (fremde IDs werden still ignoriert).
        $query = LegacyDiaryEntry::query()->whereIn('id', $data['ids']);

        if (! $isAdmin) {
            abort_if($legacyUserId <= 0, 403);
            $query->where('user', $legacyUserId);
        }

        $entries = $query->get();

        if ($data['action'] === 'delete') {
            foreach ($entries as $entry) {
                $entry->delete();
            }

            return $this->backToDiary($request)
                ->with('success', __(':count Legacy-Einträge gelöscht.', ['count' => $entries->count()]));
        }

        $gelesen = match ((string) $data['action']) {
            'status_open' => 2,
            'status_alert' => 3,
            'status_progress' => 1,
            'status_done' => -1,
            // Von der Validierung ausgeschlossen — nur für die Exhaustivität.
            default => abort(422),
        };

        foreach ($entries as $entry) {
            $entry->update(['gelesen' => $gelesen, 'aktuell' => LegacyClock::now()]);
        }

        return $this->backToDiary($request)
            ->with('success', __(':count Legacy-Einträge aktualisiert.', ['count' => $entries->count()]));
    }

    /** Die klassische Ansicht kehrt wie das Altportal ins Tagebuch der zuletzt gezeigten Woche zurück. */
    private function backToDiary(Request $request): RedirectResponse {
        return LegacyView::isClassic($request)
            ? redirect()->toList('legacy.diary.week')
            : redirect()->route('legacy.diary.index');
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
    private function entryPayload(array $data, int $entryUserId): array {
        return [
            'aktuell' => LegacyClock::now(),
            'user' => $entryUserId,
            'von' => $data['von'] ?? null,
            'bis' => $data['bis'] ?? null,
            'inhalt' => $data['inhalt'],
            'antwort' => $data['antwort'] ?? null,
            'gelesen' => (int) $data['gelesen'],
            // Das Häkchen sendet „j“ wie im Altsystem; boolean() kennt diesen Wert nicht.
            'sms' => ($data['sms'] ?? null) === 'j' ? 'j' : '',
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

    private function sendLegacySmsMail(?LegacyDiaryEntry $entry, int $actorLegacyUserId): void {
        // Wie im Altsystem geht der Hinweis an den Mitarbeiter, wenn jemand anderes speichert — nie für die eigene Antwort.
        if (! $entry || (string) $entry->sms !== 'j' || (int) $entry->user === $actorLegacyUserId) {
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
