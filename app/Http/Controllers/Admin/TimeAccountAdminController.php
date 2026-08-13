<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\TimeAccount\{CarryoverPolicy, TimeAccountSource, TimeAccountUnit};
use App\Http\Controllers\Controller;
use App\Models\{Organization, ShiftType, TimeAccount, TimeAccountRule, User};
use App\Services\TimeAccount\TimeAccountPostingService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Zeitkonten-Pflege (MVP-526) — admin-gebunden wie Rollpläne/Zeitdimensionen:
 * Kontenstamm, Bebuchungsregeln, manuelle Sonderbuchungen, Sofort-Lauf.
 */
class TimeAccountAdminController extends Controller {
    public function index(): View {
        $this->authorizeAdmin();

        return view('admin.time-accounts.index', [
            'accounts' => TimeAccount::query()
                ->with('rules')
                ->orderBy('name')
                ->get(),
            'shiftTypes' => ShiftType::query()->where('is_active', true)->orderBy('name')->get(),
            'members' => User::query()
                ->where('organization_id', $this->organizationId())
                ->orderBy('name')
                ->get(['id', 'name']),
            'sources' => TimeAccountSource::cases(),
        ]);
    }

    public function create(): View {
        $this->authorizeAdmin();

        return view('admin.time-accounts._form_dialog', ['isDialog' => true]);
    }

    public function store(Request $request): RedirectResponse {
        $this->authorizeAdmin();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(TimeAccountUnit::class)],
            'warn_threshold' => ['nullable', 'numeric', 'min:0'],
            'critical_threshold' => ['nullable', 'numeric', 'min:0'],
            'carryover_policy' => ['required', Rule::enum(CarryoverPolicy::class)],
            'cap_amount' => ['nullable', 'numeric', 'min:0', 'required_if:carryover_policy,cap'],
            'show_on_terminal' => ['nullable', 'boolean'],
        ]);

        $exists = TimeAccount::query()->where('code', $data['code'])->exists();
        if ($exists) {
            return back()->withInput()->with('error', __('Konto-Code ist bereits vergeben.'));
        }

        $account = TimeAccount::query()->create([
            'organization_id' => $this->organizationId(),
            'code' => $data['code'],
            'name' => $data['name'],
            'unit' => $data['unit'],
            'warn_threshold' => $data['warn_threshold'] ?? null,
            'critical_threshold' => $data['critical_threshold'] ?? null,
            'carryover_policy' => $data['carryover_policy'],
            'cap_amount' => $data['cap_amount'] ?? null,
            'show_on_terminal' => (bool) ($data['show_on_terminal'] ?? false),
            'is_active' => true,
        ]);
        $account->audit('timeAccount.created', ['code' => $account->code, 'unit' => $account->unit->value]);

        return redirect()->route('admin.time-accounts.index')->with('status', __('Zeitkonto angelegt.'));
    }

    public function toggle(TimeAccount $account): RedirectResponse {
        $this->authorizeAdmin();

        $account->update(['is_active' => ! $account->is_active]);
        $account->audit($account->is_active ? 'timeAccount.activated' : 'timeAccount.deactivated', ['code' => $account->code]);

        return back()->with('status', __('Zeitkonto aktualisiert.'));
    }

    public function storeRule(Request $request, TimeAccount $account): RedirectResponse {
        $this->authorizeAdmin();

        $data = $request->validate([
            'source_type' => ['required', Rule::enum(TimeAccountSource::class)],
            'match_value' => ['nullable', 'string', 'max:128'],
            'factor' => ['required', 'numeric', 'min:-100', 'max:100', 'not_in:0'],
        ]);

        // ShiftType-Match kommt als Sqid aus dem Formular → numerische ID speichern.
        $match = $data['match_value'] ?? null;
        if ($data['source_type'] === TimeAccountSource::ShiftTypeCount->value && filled($match)) {
            $shiftTypeId = Sqid::decodeOrNumeric(ShiftType::class, (string) $match);
            if (! ShiftType::query()->whereKey($shiftTypeId)->exists()) {
                return back()->with('error', __('Unbekannter Schichttyp.'));
            }
            $match = (string) $shiftTypeId;
        }

        $account->rules()->create([
            'source_type' => $data['source_type'],
            'match_value' => filled($match) ? $match : null,
            'factor' => $data['factor'],
        ]);
        $account->audit('timeAccount.ruleAdded', ['code' => $account->code, 'source' => $data['source_type'], 'match' => $match]);

        return back()->with('status', __('Bebuchungsregel gespeichert.'));
    }

    public function destroyRule(TimeAccount $account, TimeAccountRule $rule): RedirectResponse {
        $this->authorizeAdmin();
        abort_unless((int) $rule->time_account_id === (int) $account->getKey(), 404);

        $rule->delete();
        $account->audit('timeAccount.ruleRemoved', ['code' => $account->code, 'rule_id' => (int) $rule->getKey()]);

        return back()->with('status', __('Bebuchungsregel entfernt.'));
    }

    /** Manuelle Sonderbuchung (auditiert, Pflichtbegründung). */
    public function manualEntry(Request $request, TimeAccount $account, TimeAccountPostingService $service): RedirectResponse {
        $this->authorizeAdmin();

        if ($request->filled('user_id')) {
            $request->merge(['user_id' => Sqid::decodeOrNumeric(User::class, (string) $request->input('user_id'))]);
        }
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'booking_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $target = User::query()
            ->where('organization_id', $this->organizationId())
            ->findOrFail((int) $data['user_id']);

        $service->manualEntry(
            $account,
            $target,
            CarbonImmutable::parse((string) $data['booking_date']),
            (float) $data['quantity'],
            (string) $data['note'],
            $actor,
        );

        return back()->with('status', __('Sonderbuchung erfasst.'));
    }

    /** Bebuchung sofort ausführen (zusätzlich zum täglichen Lauf). */
    public function post(Request $request, TimeAccountPostingService $service): RedirectResponse {
        $this->authorizeAdmin();

        $validated = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:400']]);
        $days = (int) ($validated['days'] ?? 40);
        $org = app('currentOrganization');
        if (! $org instanceof Organization) {
            abort(404);
        }

        $stats = $service->postRange($org, CarbonImmutable::now()->subDays($days), CarbonImmutable::now());

        return back()->with('status', __(':posted Buchungen, :skipped übersprungen, :capped Kappungen.', [
            'posted' => $stats['posted'],
            'skipped' => $stats['skipped'],
            'capped' => $stats['capped'],
        ]));
    }

    private function authorizeAdmin(): void {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user !== null && $user->isAdmin(), 403);
    }

    private function organizationId(): int {
        /** @var User $user */
        $user = Auth::user();

        return (int) $user->organization_id;
    }
}
