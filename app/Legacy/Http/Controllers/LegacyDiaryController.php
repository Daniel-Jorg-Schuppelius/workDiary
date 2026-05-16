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
use App\Legacy\Models\LegacyDiaryEntry;
use App\Legacy\Models\LegacyNotdienst;
use App\Legacy\Models\LegacyOnCall;
use App\Legacy\Models\LegacyUser;
use App\Legacy\Services\LegacyDashboardService;
use App\Legacy\Services\LegacyWeekCalendarService;
use App\Legacy\Support\LegacyRoleResolver;
use App\Models\User;
use App\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class LegacyDiaryController extends Controller
{
    use RequiresLegacyAdmin;

    public function index(Request $request, LegacyDashboardService $dashboard): View
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        $data = $dashboard->buildIndexData($request, $currentUser);
        $data['users'] = $this->legacyUsersForSelect();

        return view('legacy.diary.index', $data);
    }

    public function week(Request $request, LegacyWeekCalendarService $calendar, HolidayService $holidays): View
    {
        $weekOffset = (int) $request->query('week', 0);
        $weekDate = trim((string) $request->query('week_date', ''));
        $legacyUserId = (int) (Auth::user()->legacy_user_id ?? 0);
        $isAdmin = $legacyUserId > 0 && $legacyUserId <= 3;

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
            'users' => $users,
            'monday' => $monday,
            'sunday' => $sunday,
            'weekOffset' => $weekOffset,
            'selectedWeek' => $selectedWeek,
            'isAdmin' => $isAdmin,
            'legacyUserId' => $legacyUserId,
            'entriesByUserDay' => $entriesByUserDay,
            'oncallByUserDay' => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
            'holidays' => $holidays,
        ]);
    }

    public function create(): View|Response
    {
        $data = $this->formData(null, false);

        return response(view('legacy.diary._form_dialog', $data));
    }

    public function store(SaveLegacyDiaryEntryRequest $request): RedirectResponse|JsonResponse
    {
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

    public function show(LegacyDiaryEntry $entry): View|Response
    {
        $entry->load('author:id,uname');

        if (request()->boolean('dialog')) {
            return response(view('legacy.diary._show_dialog', ['entry' => $entry]));
        }

        return view('legacy.diary.show', [
            'entry' => $entry,
        ]);
    }

    public function edit(LegacyDiaryEntry $entry): View|Response
    {
        $this->ensureCanModify($entry);

        $data = $this->formData($entry, true);

        return response(view('legacy.diary._form_dialog', $data));
    }

    public function update(SaveLegacyDiaryEntryRequest $request, LegacyDiaryEntry $entry): RedirectResponse|JsonResponse
    {
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

    public function destroy(LegacyDiaryEntry $entry): RedirectResponse
    {
        $this->ensureCanModify($entry);

        $entry->delete();

        return redirect()->route('legacy.diary.index')->with('success', 'Legacy-Eintrag geloescht.');
    }

    private function ensureCanModify(LegacyDiaryEntry $entry): void
    {
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
    private function entryPayload(array $data, int $entryUserId, Request $request): array
    {
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
    private function formData(?LegacyDiaryEntry $entry, bool $isEdit): array
    {
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        return [
            'entry' => $entry,
            'isEdit' => $isEdit,
            'isAdmin' => $isAdmin,
            'users' => $isAdmin ? $this->legacyUsersForSelect() : collect(),
        ];
    }

    private function sendLegacySmsMail(?LegacyDiaryEntry $entry): void
    {
        if (! $entry || (string) $entry->sms !== 'j') {
            return;
        }

        $entry->loadMissing('author:id,uname,email');

        $mail = (string) optional($entry->author)->email;

        if ($mail === '') {
            return;
        }

        $subject = 'WorkDiary Legacy Hinweis #'.$entry->id;
        $body = "Ein neuer Legacy-Eintrag wurde gespeichert.\n\n"
            .'ID: '.$entry->id."\n"
            .'Mitarbeiter: '.(optional($entry->author)->uname ?? 'Unbekannt')."\n"
            .'Status: '.$entry->statusLabel()."\n"
            .'Von: '.($entry->von?->format('d.m.Y H:i') ?? '-')."\n"
            .'Bis: '.($entry->bis?->format('d.m.Y H:i') ?? '-')."\n\n"
            .'Inhalt:'."\n"
            .(string) $entry->inhalt;

        Mail::raw($body, function ($message) use ($mail, $subject): void {
            $message->to($mail)->subject($subject);
        });
    }
}
