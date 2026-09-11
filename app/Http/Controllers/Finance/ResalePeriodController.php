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
use App\Http\Requests\Finance\Resale\{LinkResalePeriodRequest, QuickLinkResalePeriodRequest, WaiveResalePeriodRequest};
use App\Models\Customer;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use App\Services\Reselling\Register\{LicenseMonths, LinkProposer, PeriodLinker};
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Abrechnungsperioden und ihre Rechnungsbezüge (Feature 152, MVP-761):
 * Vorschlagslauf, Bestätigen, Verzichten, manuelle Bezüge — die Antwort auf
 * „was wurde berechnet und was nicht".
 */
class ResalePeriodController extends Controller {
    use ResolvesCurrentOrganization;

    private const PER_PAGE = 50;

    /** Lizenzpositionen ohne Abo: mehr zeigt die Periodenseite nicht (C12). */
    private const UNLINKED_LIMIT = 100;

    public function __construct(private readonly PeriodLinker $linker, private readonly InvoiceMirror $mirror) {}

    public function index(Request $request): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $today = ResalePeriod::today();
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
        $this->mirror->preload($organization, $periods->getCollection()->flatMap(static fn(ResalePeriod $p) => $p->links));

        $counts = ResalePeriod::query()->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false))
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->all();

        // Lizenzpositionen ohne Bezug zu einer Periode (alle Quellen) — Hinweis auf
        // fehlende Abos oder falsche Halter; gezählt einmal, geladen begrenzt (C12).
        $unlinked = $this->mirror->unlinkedLines($organization, self::UNLINKED_LIMIT);

        return view('finance.resale.periods', [
            'periods' => $periods,
            'filters' => $filters,
            'filterCustomer' => $customer,
            'counts' => $counts,
            'statuses' => PeriodStatus::cases(),
            'today' => $today,
            'unlinkedTotal' => $unlinked['total'],
            'unlinked' => $unlinked['lines'],
        ]);
    }

    public function propose(LinkProposer $proposer): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        try {
            $result = $proposer->propose($organization);
        } catch (RuntimeException $e) {
            // Lauf läuft schon (Sperre je Organisation): Hinweis statt 500.
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', __('resale.link.flash.proposed', $result));
    }

    public function confirm(Request $request, ResalePeriod $period): RedirectResponse {
        $period->links()->where('origin', LinkOrigin::Proposed->value)->update(['origin' => LinkOrigin::Confirmed->value, 'confirmed_at' => now()]);
        $period->unsetRelation('links');
        $this->linker->settle($period, $request->user()?->id, (string) $request->input('note', ''));
        $period->audit('resale_period.confirmed', ['status' => $period->status->value, 'vouchers' => $period->links->pluck('voucher_number')->all()]);

        return back()->with('success', __('resale.link.flash.confirmed'));
    }

    public function waiveCreate(ResalePeriod $period): View {
        return view('finance.resale._waive_dialog', ['period' => $period->load('subscription')]);
    }

    public function waive(WaiveResalePeriodRequest $request, ResalePeriod $period): RedirectResponse {
        $period->forceFill([
            'status' => $request->status(),
            'waived_reason' => $request->reason(),
            'decided_by_user_id' => $request->user()?->id,
            'decided_at' => now(),
        ])->save();
        $event = $request->status() === PeriodStatus::Waived ? 'resale_period.waived' : 'resale_period.disputed';
        $period->audit($event, ['reason' => $request->reason()]);

        return redirect(url()->previous(route('finance.resale.periods.index')))->with('success', __('resale.link.flash.waived'));
    }

    public function reopen(ResalePeriod $period): RedirectResponse {
        $previous = ['status' => $period->status->value, 'reason' => $period->waived_reason];
        $period->forceFill(['status' => PeriodStatus::Open, 'waived_reason' => null, 'decided_by_user_id' => null, 'decided_at' => null])->save();
        $period->links()->where('origin', LinkOrigin::Proposed->value)->delete();
        $period->unsetRelation('links');
        $this->linker->settle($period, null, null, false);
        $period->audit('resale_period.reopened', $previous + ['status_now' => $period->status->value]);

        return back()->with('success', __('resale.link.flash.reopened'));
    }

    /**
     * Dialog für den manuellen Bezug: nur Abo-Positionen (Artikel laut
     * Einstufung) des Empfängers im Fenster der Periode, je Position
     * Lizenzen × Monate und der noch freie Rest; verbrauchte Positionen
     * bleiben sichtbar, aber gesperrt. Support-Stunden und Hardware haben
     * hier nichts verloren — sie waren die lange Liste.
     */
    public function linkCreate(ResalePeriod $period): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $period->load(['subscription.customer', 'subscription.foreignCustomer.customer', 'links']);
        $billedTo = $period->subscription->billedTo();
        $lines = $billedTo === null ? collect() : $this->mirror->candidatesFor($organization, $billedTo->id, $period);
        // Bezüge an DIESER Periode zählen nicht: ein erneuter Bezug ersetzt sie; sie werden nur markiert.
        $consumed = $this->linker->consumed($lines, $period);
        $linked = $period->links->map(static fn(ResalePeriodLink $l): string => $l->mirrorIdentity())->all();
        $rows = [];
        foreach ($lines as $line) {
            $split = LicenseMonths::split($line);
            $months = $split['licences'] * $split['months'];
            $free = max(0.0, $months - ($consumed[$line->identity()]['months'] ?? 0.0));
            $rows[] = [
                'line' => $line,
                'licences' => $split['licences'],
                'per_licence' => $split['months'],
                'free' => $free,
                'used' => in_array($line->identity(), $linked, true) || $free <= 0.001,
                'partly' => $free > 0.001 && $free < $months - 0.001,
            ];
        }

        return view('finance.resale._link_dialog', [
            'period' => $period,
            'rows' => $rows,
            'needed' => $period->openMonths(),
            'hasSource' => $billedTo !== null && $this->mirror->coversRecipient($organization, $billedTo),
        ]);
    }

    public function linkStore(LinkResalePeriodRequest $request, ResalePeriod $period): RedirectResponse {
        $line = $request->line();
        if ($line === null) {
            throw ValidationException::withMessages(['line_id' => (string) __('resale.link.error.line_missing')]);
        }
        $link = $this->attach($period, $line, $request->months(), $request->note(), $request->user()?->id);

        return redirect()->route('finance.resale.show', $period->subscription->sqid)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }

    /**
     * Schnellzuordnung aus der Rechnungsliste des Abos: Position → gewählte Periode.
     */
    public function quickLink(QuickLinkResalePeriodRequest $request, ResaleSubscription $subscription): RedirectResponse {
        $period = $request->period();
        $line = $request->line();
        if ($period === null || $line === null) {
            throw ValidationException::withMessages(['line_id' => (string) __('resale.link.error.line_missing')]);
        }
        $link = $this->attach($period, $line, $request->months(), null, $request->user()?->id);

        return redirect()->route('finance.resale.show', $subscription->sqid)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }

    /** Bezug schreiben; „mehr als frei" wird zum Feldfehler an `months`. */
    private function attach(ResalePeriod $period, MirrorLine $line, float $months, ?string $note, ?int $userId): ResalePeriodLink {
        try {
            return $this->linker->attach($period, $line, $months, $note, $userId);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['months' => $e->getMessage()]);
        }
    }

    public function linkDestroy(ResalePeriodLink $link): RedirectResponse {
        $period = $link->period;
        $link->delete();
        $period->unsetRelation('links');
        $this->linker->settle($period, null, null, false);
        $period->audit('resale_period.link_removed', ['voucher_number' => $link->voucher_number, 'months' => $link->months, 'origin' => $link->origin->value, 'status_now' => $period->status->value]);

        return back()->with('success', __('resale.link.flash.unlinked'));
    }
}
