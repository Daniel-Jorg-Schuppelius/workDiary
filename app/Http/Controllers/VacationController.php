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

use App\Enums\Notification\NotificationEvent;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{User, Vacation};
use App\Services\Absence\VacationBalanceService;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Notification\NotificationDispatcher;
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

        $vacation = Vacation::create($data);

        // MVP-538: Antragseingang an die Entscheider (Org-Regel, Default Teamleitung).
        $owner = $vacation->user;
        if ($owner !== null) {
            app(NotificationDispatcher::class)->notify(
                NotificationEvent::VacationRequested,
                $vacation,
                $owner,
                [
                    'title' => (string) __('notification.message.vacation_requested_title', [
                        'user' => (string) ($owner->name ?? '–'),
                        'from' => $vacation->start_date->format('d.m.Y'),
                        'to' => $vacation->end_date->format('d.m.Y'),
                    ]),
                    'title_key' => 'notification.message.vacation_requested_title',
                    'title_params' => [
                        'user' => (string) ($owner->name ?? '–'),
                        'from' => $vacation->start_date->toDateString(),
                        'to' => $vacation->end_date->toDateString(),
                    ],
                    'message' => (string) ($vacation->note ?? ''),
                    'url' => route('duties.index', ['tab' => 'urlaub']),
                ],
            );
        }

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

        // MVP-531: Stufen übers Antragsverfahren-Framework (MVP-523-Verhalten
        // unverändert; Alt-Freigaben ohne Step werden nachgetragen).
        if ($vacation->status === VacationStatus::Pending) {
            $flow = app(ApprovalFlowService::class);
            if ($vacation->first_approved_by !== null) {
                $flow->backfillStage($vacation, (int) $vacation->first_approved_by, $vacation->first_approved_at);
            }
            try {
                $progress = $flow->approveStage($vacation, ApprovalFlowService::TYPE_VACATION, $auth);
            } catch (\Illuminate\Validation\ValidationException) {
                return redirect()->route('duties.index', ['tab' => 'urlaub'])
                    ->with('error', __('Die zweite Freigabe muss durch eine andere Person erfolgen (Vier-Augen-Prinzip).'));
            }
            if (! $progress->isFinal()) {
                $vacation->update([
                    'first_approved_by' => $auth->id,
                    'first_approved_at' => now(),
                ]);

                return redirect()->route('duties.index', ['tab' => 'urlaub'])
                    ->with('success', __('Erste Freigabe erfasst — die zweite Freigabe muss durch eine andere Person erfolgen.'));
            }
        }

        $vacation->update([
            'status' => VacationStatus::Approved,
            'decided_by' => $auth->id,
            'decided_at' => now(),
            'reject_reason' => null,
        ]);

        $this->notifyDecided($vacation, approved: true);

        // MVP-413: Überbuchung warnen, nie blockieren — die Entscheidung bleibt beim Genehmiger.
        if ($vacation->type->countsAgainstEntitlement()) {
            $balance = $this->balanceService->balanceFor((int) $vacation->user_id, (int) $vacation->start_date->year);
            if ($balance->hasEntitlement && $balance->remainingDays() < 0) {
                return redirect()->route('duties.index', ['tab' => 'urlaub'])
                    ->with('warning', __('Urlaubsantrag genehmigt — der Jahresanspruch ist damit um :days Tage überschritten.', [
                        'days' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(abs($balance->remainingDays()), 1, withThousandsSeparator: true),
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

        $this->notifyDecided($vacation, approved: false);

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

    /** MVP-538: finale Entscheidung an die antragstellende Person (nie die Vier-Augen-Zwischenstufe). */
    private function notifyDecided(Vacation $vacation, bool $approved): void {
        $owner = $vacation->user;
        if ($owner === null) {
            return;
        }

        $params = [
            'from' => $vacation->start_date->toDateString(),
            'to' => $vacation->end_date->toDateString(),
        ];
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::VacationDecided,
            $vacation,
            $owner,
            [
                'title' => (string) __('notification.message.vacation_decided_title', [
                    'from' => $vacation->start_date->format('d.m.Y'),
                    'to' => $vacation->end_date->format('d.m.Y'),
                ]),
                'title_key' => 'notification.message.vacation_decided_title',
                'title_params' => $params,
                'message' => (string) __(
                    $approved ? 'notification.message.vacation_approved' : 'notification.message.vacation_rejected',
                    ['note' => (string) ($vacation->reject_reason ?? '')],
                ),
                'message_key' => $approved ? 'notification.message.vacation_approved' : 'notification.message.vacation_rejected',
                'message_params' => ['note' => (string) ($vacation->reject_reason ?? '')],
                'url' => route('duties.index', ['tab' => 'urlaub']),
            ],
        );
    }

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
