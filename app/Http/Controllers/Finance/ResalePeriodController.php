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
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Register\{LinkProposer, PeriodLinker};
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

    public function __construct(private readonly PeriodLinker $linker) {}

    public function index(Request $request): View {
        $today = CarbonImmutable::today();
        $filters = [
            'status' => (string) $request->query('status', 'problems'),
            'customer' => (string) $request->query('customer', ''),
            'q' => trim((string) $request->query('q', '')),
        ];
        $query = ResalePeriod::query()
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'links.linkable'])
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
            'unlinked' => $this->unlinkedLicenseLines(),
        ]);
    }

    /**
     * Lizenzpositionen (Microsoft-Artikel) im Belegspiegel ohne Bezug zu einer
     * Periode — Hinweis auf fehlende Abos oder falsche Halter.
     *
     * @return \Illuminate\Support\Collection<int, LexofficeVoucherLine>
     */
    private function unlinkedLicenseLines(): \Illuminate\Support\Collection {
        $classifier = new \App\Services\Reselling\Register\LicenseArticleClassifier;
        $articleIds = \App\Models\LexofficeArticle::query()->active()->get(['id', 'name', 'resale_role'])
            ->filter(static fn(\App\Models\LexofficeArticle $a): bool => $classifier->isLicense($a))
            ->pluck('id')
            ->all();
        if ($articleIds === []) {
            return collect();
        }
        $linked = ResalePeriodLink::query()->where('linkable_type', (new LexofficeVoucherLine)->getMorphClass())->pluck('linkable_id');

        return LexofficeVoucherLine::query()
            ->whereIn('lexoffice_article_id', $articleIds)
            ->whereNotIn('id', $linked)
            ->whereHas('voucher', static fn(Builder $v) => $v->where('voucher_type', 'invoice')->where('archived', false)->whereNotIn('voucher_status', ['draft', 'voided']))
            ->with(['voucher:id,voucher_number,voucher_date,customer_id,voucher_text', 'voucher.customer:id,name', 'article:id,name'])
            ->get()
            ->sortByDesc(static fn(LexofficeVoucherLine $l) => $l->voucher->voucher_date)
            ->values();
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
        $this->linker->settle($period, $request->user()?->id, (string) $request->input('note', ''));

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

        return redirect(url()->previous(route('finance.resale.periods.index')))->with('success', __('resale.link.flash.waived'));
    }

    public function reopen(ResalePeriod $period): RedirectResponse {
        $period->forceFill(['status' => PeriodStatus::Open, 'waived_reason' => null, 'decided_by_user_id' => null, 'decided_at' => null])->save();
        $period->links()->where('origin', LinkOrigin::Proposed->value)->delete();
        $period->unsetRelation('links');
        $this->linker->settle($period, null, null, false);

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
        $link = $this->linker->attach($period, $line, (float) $validated['months'], $validated['note'] ?? null, $request->user()?->id);

        return redirect()->route('finance.resale.show', $period->subscription->sqid)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }

    /**
     * Schnellzuordnung aus der Rechnungsliste des Abos: Position → gewählte Periode.
     */
    public function quickLink(Request $request, ResaleSubscription $subscription): RedirectResponse {
        $validated = $request->validate([
            'period_id' => ['required', 'string'],
            'line_id' => ['required', 'string'],
            'months' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ]);
        $periodId = Sqid::decode(ResalePeriod::class, (string) $validated['period_id']);
        $period = $periodId === null ? null : $subscription->periods()->whereKey($periodId)->first();
        $lineId = Sqid::decode(LexofficeVoucherLine::class, (string) $validated['line_id']);
        $line = $lineId === null ? null : LexofficeVoucherLine::query()->with('voucher')->find($lineId);
        if ($period === null || $line === null) {
            return redirect()->route('finance.resale.show', $subscription->sqid)->with('error', __('resale.link.error.line_missing'));
        }
        $link = $this->linker->attach($period, $line, (float) $validated['months'], null, $request->user()?->id);

        return redirect()->route('finance.resale.show', $subscription->sqid)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }

    public function linkDestroy(ResalePeriodLink $link): RedirectResponse {
        $period = $link->period;
        $link->delete();
        $period->unsetRelation('links');
        $this->linker->settle($period, null, null, false);

        return back()->with('success', __('resale.link.flash.unlinked'));
    }
}
