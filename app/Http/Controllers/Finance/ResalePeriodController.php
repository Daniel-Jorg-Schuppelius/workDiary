<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriodController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\{Customer, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};
use App\Services\Reselling\Register\LinkProposer;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Abrechnungsperioden und ihre Rechnungsbezüge (Feature 152, MVP-761):
 * Vorschlagslauf, Bestätigen, Verzichten, manuelle Bezüge — die Antwort auf
 * „was wurde berechnet und was nicht".
 */
class ResalePeriodController extends Controller {
    use ResolvesCurrentOrganization;

    private const PER_PAGE = 50;

    public function index(Request $request): View {
        $today = CarbonImmutable::today();
        $filters = [
            'status' => (string) $request->query('status', 'problems'),
            'customer' => (string) $request->query('customer', ''),
            'q' => trim((string) $request->query('q', '')),
        ];
        $query = ResalePeriod::query()
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'links'])
            ->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
        if ($filters['status'] === 'problems') {
            // Probleme = offen/teilweise/strittig ODER nur vorgeschlagen (noch nicht bestätigt).
            $query->where(static fn(Builder $w) => $w->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value, PeriodStatus::Disputed->value])
                ->orWhere(static fn(Builder $p) => $p->where('status', PeriodStatus::Billed->value)->whereNull('decided_at')->whereHas('links')));
        } elseif (PeriodStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        $customerId = $filters['customer'] !== '' ? Sqid::decode(Customer::class, $filters['customer']) : null;
        $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
        if ($customer !== null) {
            $query->whereHas('subscription', static fn(Builder $s) => $s->where(static fn(Builder $w) => $w->where('customer_id', $customer->id)
                ->orWhereIn('foreign_customer_id', \App\Models\ForeignCustomer::query()->where('customer_id', $customer->id)->select('id'))));
        }
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('subscription', static fn(Builder $s) => $s->where(static fn(Builder $w) => $w->whereLikeEscaped('label', $q)
                ->orWhereHas('customer', static fn(Builder $c) => $c->whereLikeEscaped('name', $q))
                ->orWhereHas('foreignCustomer', static fn(Builder $f) => $f->whereLikeEscaped('name', $q))));
        }
        $periods = $query->orderBy('starts_on')->orderBy('subscription_id')->paginate(self::PER_PAGE)->withQueryString();

        $counts = ResalePeriod::query()->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false))
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->all();

        return view('finance.resale.periods', [
            'periods' => $periods,
            'filters' => $filters,
            'filterCustomer' => $customer,
            'counts' => $counts,
            'statuses' => PeriodStatus::cases(),
            'today' => $today,
        ]);
    }

    public function propose(LinkProposer $proposer): RedirectResponse {
        $organization = $this->currentOrganizationOrNull();
        if ($organization === null) {
            abort(404);
        }
        $result = $proposer->propose($organization);

        return back()->with('success', __('resale.link.flash.proposed', $result));
    }

    public function confirm(Request $request, ResalePeriod $period): RedirectResponse {
        $period->links()->where('origin', LinkOrigin::Proposed->value)->update(['origin' => LinkOrigin::Confirmed->value, 'confirmed_at' => now()]);
        $period->unsetRelation('links');
        $this->settle($period, $request->user()?->id, (string) $request->input('note', ''));

        return back()->with('success', __('resale.link.flash.confirmed'));
    }

    public function waiveCreate(ResalePeriod $period): View {
        return view('finance.resale._waive_dialog', ['period' => $period->load('subscription')]);
    }

    public function waive(Request $request, ResalePeriod $period): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', 'in:waived,disputed'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $period->forceFill([
            'status' => $validated['decision'] === 'waived' ? PeriodStatus::Waived : PeriodStatus::Disputed,
            'waived_reason' => $validated['reason'],
            'decided_by_user_id' => $request->user()?->id,
            'decided_at' => now(),
        ])->save();

        return redirect()->route('finance.resale.periods.index')->with('success', __('resale.link.flash.waived'));
    }

    public function reopen(ResalePeriod $period): RedirectResponse {
        $period->forceFill(['status' => PeriodStatus::Open, 'waived_reason' => null, 'decided_by_user_id' => null, 'decided_at' => null])->save();
        $period->links()->where('origin', LinkOrigin::Proposed->value)->delete();
        $period->unsetRelation('links');
        $this->settle($period, null, null, false);

        return back()->with('success', __('resale.link.flash.reopened'));
    }

    public function linkCreate(ResalePeriod $period, LinkProposer $proposer): View {
        $period->load(['subscription.customer', 'subscription.foreignCustomer.customer', 'links']);
        $contacts = $proposer->contactsFor($period->subscription);
        $lines = $contacts === [] ? collect() : LexofficeVoucherLine::query()
            ->whereHas('voucher', static fn(Builder $q) => $q->whereIn('contact_external_id', $contacts)->where('voucher_type', 'invoice')->where('archived', false)
                ->where('voucher_date', '>=', DateRange::day($period->starts_on->subDays(LinkProposer::WINDOW_BEFORE)))
                ->where('voucher_date', '<', DateRange::dayAfter($period->starts_on->addDays(LinkProposer::WINDOW_AFTER))))
            ->with('voucher:id,voucher_number,voucher_date,contact_external_id')
            ->get()
            ->sortByDesc(static fn(LexofficeVoucherLine $l) => $l->voucher->voucher_date)
            ->values();
        $linked = $period->links->pluck('linkable_id')->all();

        return view('finance.resale._link_dialog', [
            'period' => $period,
            'lines' => $lines,
            'linkedIds' => $linked,
            'needed' => max(0.0, $period->requiredMonths() - $period->coveredMonths()),
            'hasContacts' => $contacts !== [],
        ]);
    }

    public function linkStore(Request $request, ResalePeriod $period): RedirectResponse {
        $validated = $request->validate([
            'line_id' => ['required', 'string'],
            'months' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $lineId = Sqid::decode(LexofficeVoucherLine::class, (string) $validated['line_id']);
        $line = $lineId === null ? null : LexofficeVoucherLine::query()->with('voucher')->find($lineId);
        if ($line === null) {
            return back()->withErrors(['line_id' => __('resale.link.error.line_missing')]);
        }
        $months = (float) $validated['months'];
        $termMonths = $period->termMonths();
        $link = ResalePeriodLink::query()->updateOrCreate(
            ['period_id' => $period->id, 'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id],
            [
                'organization_id' => $period->organization_id,
                'subscription_id' => $period->subscription_id,
                'voucher_number' => $line->voucher->voucher_number,
                'voucher_date' => $line->voucher->voucher_date,
                'quantity' => round($months / $termMonths, 3),
                'months' => round($months, 2),
                'amount' => $line->unit_net->times($this->unitsFor($line, $months, $termMonths))->withScale(2),
                'currency' => $line->currency->value,
                'origin' => LinkOrigin::Manual,
                'note' => $validated['note'] ?? null,
                'created_by_user_id' => $request->user()?->id,
                'confirmed_at' => now(),
            ],
        );
        $period->unsetRelation('links');
        $this->settle($period, $request->user()?->id, null);

        return redirect()->route('finance.resale.show', $period->subscription->sqid)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }

    public function linkDestroy(ResalePeriodLink $link): RedirectResponse {
        $period = $link->period;
        $link->delete();
        $period->unsetRelation('links');
        $this->settle($period, null, null, false);

        return back()->with('success', __('resale.link.flash.unlinked'));
    }

    /** Positionsmenge, die $months Lizenzmonaten entspricht (Monatspreis: 1 je Monat, sonst Laufzeit je Stück). */
    private function unitsFor(LexofficeVoucherLine $line, float $months, int $termMonths): float {
        $unit = mb_strtolower(trim((string) $line->unit_name));
        $monthly = in_array($unit, ['monat', 'monate', 'month'], true) || ($unit === '' && $line->unit_net->toFloat() < 30.0);

        return $monthly ? $months : $months / $termMonths;
    }

    /** Status aus der Deckung ableiten; entschieden = Nutzer hat bestätigt/verknüpft. */
    private function settle(ResalePeriod $period, ?int $userId, ?string $note, bool $decided = true): void {
        $period->load('links');
        $covered = $period->coveredMonths();
        $status = $covered >= $period->requiredMonths() - 0.001 ? PeriodStatus::Billed : ($covered > 0.001 ? PeriodStatus::Partial : PeriodStatus::Open);
        $period->forceFill([
            'status' => $status,
            'decided_by_user_id' => $decided ? $userId : null,
            'decided_at' => $decided ? now() : null,
            'note' => $note !== null && $note !== '' ? $note : $period->note,
        ])->save();
    }
}
