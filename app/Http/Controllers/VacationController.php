<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{User, Vacation};
use App\Services\Absence\VacationBalanceService;
use App\Support\LookupCache;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VacationController extends Controller {
    public function __construct(private readonly VacationBalanceService $balanceService) {}

    // ── Create / Store ──────────────────────────────────────────────────────

    public function create(Request $request): View {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('create', Vacation::class);

        $prefillStart = (string) ($request->query('start_date') ?? '');
        $balanceYear = $this->yearOf($prefillStart);

        return view('vacations._form_dialog', [
            'vacation' => null,
            'isEdit' => false,
            'isDialog' => true,
            'canAssignOthers' => $auth->isAdmin(),
            'assignableUsers' => $auth->isAdmin() ? LookupCache::userDropdown() : collect(),
            'prefillStart' => $prefillStart,
            'prefillEnd' => $request->query('end_date') ?? '',
            // MVP-413: Saldo des vorbelegten Antragstellers (Dropdown-Wechsel ändert die Anzeige nicht).
            'balance' => $this->balanceService->balanceFor((int) $auth->id, $balanceYear),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('create', Vacation::class);

        $data = $this->validateVacation($request);

        if (! $auth->isAdmin() || empty($data['user_id'])) {
            $data['user_id'] = $auth->id;
        }
        $data['status'] = VacationStatus::Pending;

        Vacation::create($data);

        return redirect()->route('duties.index', ['tab' => 'urlaub'])->with('success', __('Urlaubsantrag gestellt.'));
    }

    // ── Edit / Update ───────────────────────────────────────────────────────

    public function edit(Vacation $vacation): View {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('update', $vacation);

        return view('vacations._form_dialog', [
            'vacation' => $vacation,
            'isEdit' => true,
            'isDialog' => true,
            'canAssignOthers' => $auth->isAdmin(),
            'assignableUsers' => $auth->isAdmin() ? LookupCache::userDropdown() : collect(),
            'prefillStart' => '',
            'prefillEnd' => '',
            'balance' => $this->balanceService->balanceFor((int) $vacation->user_id, (int) $vacation->start_date->year),
        ]);
    }

    public function update(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('update', $vacation);

        $data = $this->validateVacation($request);

        // Nur Admins dürfen user_id ändern; leerer Wert würde den Antrag verwaisen.
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin() || empty($data['user_id'])) {
            unset($data['user_id']);
        }

        $vacation->update($data);

        return redirect()->route('duties.index', ['tab' => 'urlaub'])->with('success', __('Urlaubsantrag aktualisiert.'));
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    public function destroy(Vacation $vacation): RedirectResponse {
        Gate::authorize('delete', $vacation);

        $vacation->delete();

        return redirect()->route('duties.index', ['tab' => 'urlaub'])->with('success', __('Urlaubsantrag gelöscht.'));
    }

    // ── Admin actions ────────────────────────────────────────────────────────

    public function rejectForm(Vacation $vacation): View {
        Gate::authorize('decide', $vacation);

        return view('vacations._reject_dialog', [
            'vacation' => $vacation,
        ]);
    }

    public function approve(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('decide', $vacation);

        /** @var User $auth */
        $auth = Auth::user();

        // MVP-523: zweistufige Genehmigung (Vier-Augen), org-konfigurierbar.
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $stages = (int) data_get($org?->settings, 'vacation.approval_stages', 1);
        if ($stages >= 2 && $vacation->status === VacationStatus::Pending) {
            if ($vacation->first_approved_by === null) {
                $vacation->update([
                    'first_approved_by' => $auth->id,
                    'first_approved_at' => now(),
                ]);

                return redirect()->route('duties.index', ['tab' => 'urlaub'])
                    ->with('success', __('Erste Freigabe erfasst — die zweite Freigabe muss durch eine andere Person erfolgen.'));
            }
            if ((int) $vacation->first_approved_by === (int) $auth->id) {
                return redirect()->route('duties.index', ['tab' => 'urlaub'])
                    ->with('error', __('Die zweite Freigabe muss durch eine andere Person erfolgen (Vier-Augen-Prinzip).'));
            }
        }

        $vacation->update([
            'status' => VacationStatus::Approved,
            'decided_by' => $auth->id,
            'decided_at' => now(),
            'reject_reason' => null,
        ]);

        // MVP-413: Überbuchung warnen, nie blockieren — die Entscheidung bleibt beim Genehmiger.
        if ($vacation->type->countsAgainstEntitlement()) {
            $balance = $this->balanceService->balanceFor((int) $vacation->user_id, (int) $vacation->start_date->year);
            if ($balance->hasEntitlement && $balance->remainingDays() < 0) {
                return redirect()->route('duties.index', ['tab' => 'urlaub'])
                    ->with('warning', __('Urlaubsantrag genehmigt — der Jahresanspruch ist damit um :days Tage überschritten.', [
                        'days' => number_format(abs($balance->remainingDays()), 1, ',', '.'),
                    ]));
            }
        }

        return redirect()->route('duties.index', ['tab' => 'urlaub'])->with('success', __('Urlaubsantrag genehmigt.'));
    }

    public function reject(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('decide', $vacation);

        $data = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $auth */
        $auth = Auth::user();

        $vacation->update([
            'status' => VacationStatus::Rejected,
            'decided_by' => $auth->id,
            'decided_at' => now(),
            'reject_reason' => $data['reject_reason'] ?? null,
        ]);

        return redirect()->route('duties.index', ['tab' => 'urlaub'])->with('success', __('Urlaubsantrag abgelehnt.'));
    }

    public function cancel(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('cancel', $vacation);

        $vacation->update([
            'status' => VacationStatus::Cancelled,
            'decided_by' => null,
            'decided_at' => null,
        ]);

        return redirect()->route('duties.index', ['tab' => 'urlaub'])->with('success', __('Urlaubsantrag storniert.'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Jahr eines Datums-Strings, Fallback: aktuelles Jahr. */
    private function yearOf(string $date): int {
        if ($date === '') {
            return (int) now()->year;
        }

        try {
            return (int) Carbon::parse($date)->year;
        } catch (\Throwable) {
            return (int) now()->year;
        }
    }

    /** @return array<string, mixed> */
    private function validateVacation(Request $request): array {
        $rawUserId = $request->input('user_id');
        $userId = \App\Support\Sqid::decodeOrNumeric(\App\Models\User::class, $rawUserId);

        $request->merge([
            'user_id' => $userId,
        ]);

        return $request->validate([
            'user_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization()],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'gte:start_date'],
            'type' => ['required', Rule::enum(VacationType::class)->only([
                VacationType::Vacation,
                VacationType::Special,
                VacationType::Unpaid,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
