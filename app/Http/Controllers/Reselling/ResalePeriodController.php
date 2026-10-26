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

namespace App\Http\Controllers\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reselling\{BulkWaiveResalePeriodsRequest, LinkResalePeriodRequest, QuickLinkResalePeriodRequest, WaiveResalePeriodRequest};
use App\Models\Customer\Customer;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use App\Services\Reselling\Register\{LicenseMonths, LinkProposer, PeriodLinker, ResaleInvoiceDraftService};
use App\Support\{ErrorText, Sqid};
use App\Support\Query\DateRange;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Log;
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
        // Standard „unbestätigte Vorschläge" (Feature 185, MVP-1416): sichere Treffer bestätigt der Lauf selbst, hier steht, was Prüfung braucht.
        $filters = [
            'status' => (string) $request->query('status', 'proposed'),
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
        } elseif ($filters['status'] === 'proposed') {
            $query->whereIn('status', [PeriodStatus::Billed->value, PeriodStatus::Partial->value])->whereNull('decided_at')
                ->whereHas('links', static fn(Builder $l) => $l->where('origin', LinkOrigin::Proposed->value));
        } elseif (PeriodStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        $customerId = $filters['customer'] !== '' ? Sqid::decode(Customer::class, $filters['customer']) : null;
        $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
        if ($customer !== null) {
            $query->whereHas('subscription', static fn(Builder $s) => $s->where(static fn(Builder $w) => $w->where('customer_id', $customer->id)
                ->orWhereIn('foreign_customer_id', \App\Models\Customer\ForeignCustomer::query()->where('customer_id', $customer->id)->select('id'))));
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
            return back()->with('warning', ErrorText::for($e));
        }

        return back()->with('success', __('resale.link.flash.proposed', $result));
    }

    public function confirm(Request $request, ResalePeriod $period): RedirectResponse {
        $this->confirmPeriod($period, $request->user()?->id, (string) $request->input('note', ''));

        return back()->with('success', __('resale.link.flash.confirmed'));
    }

    /** Vorschläge einer Periode bestätigen — Einzel- und Sammelaktion schreiben dasselbe Audit. */
    private function confirmPeriod(ResalePeriod $period, ?int $userId, string $note): void {
        $period->links()->where('origin', LinkOrigin::Proposed->value)->update(['origin' => LinkOrigin::Confirmed->value, 'confirmed_at' => now()]);
        $period->unsetRelation('links');
        $this->linker->settle($period, $userId, $note);
        $period->audit('resale_period.confirmed', ['status' => $period->status->value, 'vouchers' => $period->links->pluck('voucher_number')->all()]);
    }

    /**
     * Sammelaktion (Feature 185, MVP-1415): Vorschläge der gewählten Perioden bestätigen.
     * Übersprungen wird, was keinen offenen Vorschlag hat oder noch bevorsteht.
     */
    public function bulkConfirm(Request $request): RedirectResponse {
        $periods = $this->selectedPeriods($request);
        $done = 0;
        foreach ($periods as $period) {
            if (! $period->isProposedOnly() || $period->starts_on->greaterThan(ResalePeriod::today())) {
                continue;
            }
            $this->confirmPeriod($period, $request->user()?->id, '');
            $done++;
        }
        $skipped = $periods->count() - $done;

        return redirect()->toList('finance.resale.periods.index')
            ->with($done === 0 ? 'warning' : ($skipped > 0 ? 'warning' : 'success'), __('resale.bulk.flash.confirmed', ['done' => $done, 'skipped' => $skipped]));
    }

    public function bulkWaiveCreate(Request $request): View {
        $periods = $this->selectedPeriods($request)->load('subscription');

        return view('finance.resale._bulk_waive_dialog', ['periods' => $periods]);
    }

    /** Verzichten oder strittig stellen mit einem Grund für alle — je Periode dasselbe Audit wie einzeln. */
    public function bulkWaive(BulkWaiveResalePeriodsRequest $request): RedirectResponse {
        $periods = ResalePeriod::query()->whereKey($request->periodIds())->get();
        $event = $request->status() === PeriodStatus::Waived ? 'resale_period.waived' : 'resale_period.disputed';
        $done = 0;
        foreach ($periods as $period) {
            // Entschieden = Nutzerentscheidung (decided_at); ein unbestätigter Vorschlag ist keine. Teilweise bleibt entscheidbar.
            if (($period->decided_at !== null && $period->status !== PeriodStatus::Partial) || $period->starts_on->greaterThan(ResalePeriod::today())) {
                continue;
            }
            // Vorschläge sind keine Entscheidung: sie gehen mit dem Verzicht weg (wie beim Zurücknehmen).
            $period->links()->where('origin', LinkOrigin::Proposed->value)->delete();
            $period->forceFill([
                'status' => $request->status(),
                'waived_reason' => $request->reason(),
                'decided_by_user_id' => $request->user()?->id,
                'decided_at' => now(),
            ])->save();
            $period->audit($event, ['reason' => $request->reason()]);
            $done++;
        }
        $skipped = $periods->count() - $done;

        return redirect()->toList('finance.resale.periods.index')
            ->with($done === 0 ? 'warning' : 'success', __('resale.bulk.flash.waived', ['done' => $done, 'skipped' => $skipped]));
    }

    /** Rechnungsentwurf je Rechnungsempfänger der gewählten Perioden (alle offenen Perioden des Empfängers, wie der Dialog). */
    public function bulkDraft(Request $request, ResaleInvoiceDraftService $drafts): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $periods = $this->selectedPeriods($request)->load('subscription.customer', 'subscription.foreignCustomer.customer');
        $recipients = [];
        foreach ($periods as $period) {
            $recipient = $period->subscription->billedTo();
            if ($recipient !== null) {
                $recipients[$recipient->id] = $recipient;
            }
        }
        if ($recipients === []) {
            return redirect()->toList('finance.resale.periods.index')->with('warning', __('resale.bulk.flash.nothing'));
        }
        $lines = [];
        $failed = 0;
        foreach ($recipients as $recipient) {
            try {
                $result = $drafts->draft($organization, $recipient, $request->user());
                $lines[] = __($result['local'] ? 'resale.draft_flash.created_local' : 'resale.draft_flash.created', [
                    'customer' => $recipient->name, 'lines' => $result['lines'], 'net' => Money::ofFloat($result['net'], $recipient->currency, 2)->format(), 'id' => $result['draft_id'],
                    'target' => \App\Enums\Finance\TransferTarget::tryFrom((string) $result['target'])?->label() ?? $result['target'],
                ]);
            } catch (\Throwable $e) {
                $failed++;
                $lines[] = __('resale.bulk.flash.draft_failed', ['customer' => $recipient->name, 'error' => $e::class === RuntimeException::class ? ErrorText::for($e) : (string) __('resale.draft_flash.failed')]);
                if ($e::class !== RuntimeException::class) {
                    Log::warning('resale: Sammel-Rechnungsentwurf fehlgeschlagen', ['customer_id' => $recipient->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
                }
            }
        }

        return redirect()->toList('finance.resale.periods.index')->with($failed > 0 ? 'warning' : 'success', implode(' · ', $lines));
    }

    /**
     * Gewählte Perioden der Organisation aus `ids[]` (Sqids, höchstens 200).
     *
     * @return Collection<int, ResalePeriod>
     */
    private function selectedPeriods(Request $request): Collection {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:' . BulkWaiveResalePeriodsRequest::MAX_IDS],
            'ids.*' => ['string', 'max:64'],
        ]);
        $ids = array_values(array_filter(array_map(static fn(string $sqid): ?int => Sqid::decode(ResalePeriod::class, $sqid), $data['ids'])));

        return ResalePeriod::query()->whereKey($ids)->with('links')->orderBy('starts_on')->get();
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
        $period->links()->whereIn('origin', [LinkOrigin::Proposed->value, LinkOrigin::Auto->value])->delete();
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
        $hasSource = $billedTo !== null && $this->mirror->coversRecipient($organization, $billedTo);
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
            'hasSource' => $hasSource,
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
            throw ValidationException::withMessages(['months' => ErrorText::for($e)]);
        }
    }

    /**
     * Prüffall „zeitlich unpassend" (Feature 185, MVP-1403): Bezug auf eine
     * andere Periode DESSELBEN Abos verschieben — als Lösen plus neuer Bezug
     * mit denselben Monaten, damit die Überbuchungsprüfung greift.
     */
    public function linkMove(Request $request, ResalePeriodLink $link): RedirectResponse {
        $target = ResalePeriod::query()->find(\App\Support\Sqid::decode(ResalePeriod::class, (string) $request->input('period_id', '')) ?? 0);
        if ($target === null || $target->subscription_id !== $link->subscription_id || $target->id === $link->period_id) {
            throw ValidationException::withMessages(['period_id' => (string) __('resale.link.error.line_missing')]);
        }
        $line = $link->mirrorLine();
        if ($line === null) {
            throw ValidationException::withMessages(['period_id' => (string) __('resale.link.error.line_missing')]);
        }
        $source = $link->period;
        $months = (float) $link->months;
        $link->delete();
        $source->unsetRelation('links');
        $this->linker->settle($source, null, null, false);
        $moved = $this->attach($target, $line, abs($months), $link->note, $request->user()?->id);
        $target->audit('resale_period.link_moved', ['voucher_number' => $moved->voucher_number, 'months' => $moved->months, 'from' => $source->label(), 'status_now' => $target->status->value]);

        return back()->with('success', __('resale.account.finding.move_flash', ['period' => $target->label()]));
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
