<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchSuggestionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{DiaryEntry, User};
use App\Services\Dispatch\GapFillSuggester;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * Leerzeit-/Lückenfüller-Vorschläge (Epic 14.2, MVP-245): Vorschlagsliste
 * je Mitarbeiter/Tag mit Begründung und Konfliktwarnungen; Übernahme und
 * Ablehnung sind bewusste Disponenten-Aktionen (dispatch.manage) und
 * landen im Auftragsverlauf.
 */
class DispatchSuggestionController extends Controller {
    public function __construct(private readonly GapFillSuggester $suggester) {}

    public function index(Request $request): View {
        abort_unless(Auth::user()?->can(Permission::DispatchViewAny->value) ?? false, 403);

        $request->merge(['user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'date' => ['nullable', 'date'],
        ]);

        $users = User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']);
        $selected = isset($data['user_id'])
            ? $users->firstWhere('id', (int) $data['user_id'])
            : null;
        $date = CarbonImmutable::parse((string) ($data['date'] ?? now()->toDateString()));

        return view('dispatch.suggestions', [
            'users' => $users,
            'selected' => $selected,
            'date' => $date,
            'slots' => $selected !== null ? $this->suggester->freeSlots($selected, $date) : [],
            'suggestions' => $selected !== null ? $this->suggester->suggestFor($selected, $date) : [],
        ]);
    }

    public function apply(Request $request, DiaryEntry $entry): RedirectResponse {
        abort_unless(Auth::user()?->can(Permission::DispatchManage->value) ?? false, 403);

        $request->merge(['user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);
        $data = $request->validate([
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'date' => ['required', 'date'],
            'start' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'min:15', 'max:720'],
        ]);

        /** @var User $assignee */
        $assignee = User::query()->findOrFail($data['user_id']);
        /** @var User $dispatcher */
        $dispatcher = Auth::user();
        $this->suggester->apply($entry, $dispatcher, $assignee, CarbonImmutable::parse($data['date']), $data['start'], (int) $data['duration']);

        return back()->with('status', __('Vorschlag übernommen — Auftrag disponiert (:user, :date :time).', [
            'user' => $assignee->name, 'date' => $data['date'], 'time' => $data['start'],
        ]));
    }

    public function dismiss(Request $request, DiaryEntry $entry): RedirectResponse {
        abort_unless(Auth::user()?->can(Permission::DispatchManage->value) ?? false, 403);

        $request->merge(['user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);
        $data = $request->validate([
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $assignee */
        $assignee = User::query()->findOrFail($data['user_id']);
        /** @var User $dispatcher */
        $dispatcher = Auth::user();
        $this->suggester->dismiss($entry, $dispatcher, $assignee, CarbonImmutable::parse($data['date']), $data['reason'] ?? null);

        return back()->with('status', __('Vorschlag abgelehnt und protokolliert.'));
    }
}
