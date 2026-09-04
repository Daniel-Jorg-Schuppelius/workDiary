<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleInvoiceDraftService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Numbering\NumberScope;
use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, Organization, User};
use App\Models\Invoice;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeDraftInvoiceService};
use App\Services\Finance\BillingModeResolver;
use App\Services\Invoicing\TaxResolver;
use App\Services\Numbering\NumberSequenceService;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rechnungsvorschlag als Lexoffice-Entwurf (Feature 152, MVP-764): alle
 * offenen Perioden eines Rechnungsempfängers werden zu Positionen — eine
 * Position je Abo und Zeitraum, bei Partnern mit Endkundennennung in der
 * Beschreibung, Menge in Monaten bei Monatsartikeln. Nichts wird lokal
 * fakturiert und nichts festgeschrieben; die Perioden merken sich den
 * Entwurf in der Bemerkung.
 */
final class ResaleInvoiceDraftService {
    public function __construct(
        private readonly LinkProposer $proposer,
        private readonly TaxResolver $taxes,
        private readonly BillingModeResolver $billingModes,
        private readonly NumberSequenceService $numbers,
    ) {}

    /**
     * Offene und teilweise Perioden je Rechnungsempfänger.
     *
     * @return Collection<int, ResalePeriod>
     */
    public function openPeriodsFor(Customer $recipient, ?CarbonImmutable $reference = null): Collection {
        $reference ??= CarbonImmutable::today();

        return ResalePeriod::query()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($reference))
            ->whereHas('subscription', static fn($s) => $s->where('is_own_holding', false)->where(static fn($w) => $w->where('customer_id', $recipient->id)
                ->orWhereIn('foreign_customer_id', \App\Models\ForeignCustomer::query()->where('customer_id', $recipient->id)->select('id'))))
            ->with(['subscription.foreignCustomer', 'subscription.lexofficeArticle', 'subscription.article', 'links'])
            ->orderBy('starts_on')
            ->get();
    }

    /**
     * Rechnungsvorschlag je nach Rechnungshoheit des Empfängers: lokal ein
     * Rechnungsentwurf mit Positionen und vorgeschlagenen Bezügen, extern ein
     * Lexoffice-Entwurf.
     *
     * @return array{draft_id: string, lines: int, net: float, periods: int, local: bool}
     */
    public function draft(Organization $organization, Customer $recipient, ?User $user = null, ?LexofficeDraftInvoiceService $service = null): array {
        if (! $this->billingModes->effectiveFor($recipient)->isExternal()) {
            return $this->draftLocal($organization, $recipient, $user) + ['local' => true];
        }

        return $this->draftLexoffice($organization, $recipient, $user, $service) + ['local' => false];
    }

    /**
     * Lokale Rechnungshoheit: Rechnungsentwurf (Feature 152, MVP-764) mit einer
     * Position je Abo und Zeitraum; die Perioden bekommen einen vorgeschlagenen
     * Bezug auf die Rechnungsposition — beim Ausstellen der Rechnung bestätigt
     * der Betreiber die Perioden wie sonst auch.
     *
     * @return array{draft_id: string, lines: int, net: float, periods: int}
     */
    private function draftLocal(Organization $organization, Customer $recipient, ?User $user): array {
        $periods = $this->openPeriodsFor($recipient);
        $lines = [];
        foreach ($periods as $period) {
            $line = $this->lineFor($period);
            if ($line !== null) {
                $lines[] = ['period' => $period, 'line' => $line];
            }
        }
        if ($lines === []) {
            throw new RuntimeException((string) __('resale.draft.error.nothing_open'));
        }

        return DB::transaction(function () use ($organization, $recipient, $user, $lines): array {
            $tax = $this->taxes->resolve($organization, $recipient);
            $invoice = Invoice::create([
                'organization_id' => $organization->id,
                'customer_id' => $recipient->id,
                'number' => $this->numbers->next($organization->id, NumberScope::Invoice, now()),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_INVOICE,
                'category' => 'resale',
                'currency' => $recipient->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $tax['note'],
                'created_by' => $user?->id,
            ]);
            $net = 0.0;
            $position = 0;
            foreach ($lines as $entry) {
                $position++;
                /** @var ResalePeriod $period */
                $period = $entry['period'];
                $line = $entry['line'];
                $item = $invoice->items()->create([
                    'organization_id' => $organization->id,
                    'service_date' => $period->starts_on->toDateString(),
                    'description' => trim($line['name'] . ' · ' . $line['description']),
                    'quantity' => (string) $line['quantity'],
                    'unit' => $line['unit_name'],
                    'unit_price' => (string) $line['unit_net'],
                    'tax_category' => $tax['category'],
                    'position' => $position,
                    'article_id' => $period->subscription->article_id,
                ]);
                $months = max(0.0, $period->requiredMonths() - $period->coveredMonths());
                ResalePeriodLink::query()->create([
                    'organization_id' => $organization->id,
                    'period_id' => $period->id,
                    'subscription_id' => $period->subscription_id,
                    'linkable_type' => $item->getMorphClass(),
                    'linkable_id' => $item->id,
                    'voucher_number' => $invoice->number,
                    'voucher_date' => now()->toDateString(),
                    'quantity' => round($months / $period->termMonths(), 3),
                    'months' => round($months, 2),
                    'amount' => round($line['quantity'] * $line['unit_net'], 2),
                    'currency' => $recipient->currency->value,
                    'origin' => LinkOrigin::Proposed,
                    'note' => (string) __('resale.draft.local_note', ['number' => $invoice->number]),
                    'created_by_user_id' => $user?->id,
                ]);
                $period->forceFill(['status' => PeriodStatus::Billed, 'note' => (string) __('resale.draft.local_note', ['number' => $invoice->number])])->save();
                $net += $line['quantity'] * $line['unit_net'];
            }
            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return ['draft_id' => (string) $invoice->number, 'lines' => count($lines), 'net' => round($net, 2), 'periods' => count($lines)];
        });
    }

    /**
     * @return array{draft_id: string, lines: int, net: float, periods: int}
     */
    private function draftLexoffice(Organization $organization, Customer $recipient, ?User $user, ?LexofficeDraftInvoiceService $service): array {
        $config = LexofficeConfig::resolve($organization->id);
        if ($config['enabled'] !== true || ! is_string($config['api_key']) || $config['api_key'] === '') {
            throw new RuntimeException((string) __('resale.draft.error.lexoffice'));
        }
        $contacts = $this->proposer->contactsForCustomer($recipient);
        if ($contacts === []) {
            throw new RuntimeException((string) __('resale.link.no_contacts'));
        }
        $periods = $this->openPeriodsFor($recipient);
        $lines = [];
        $net = 0.0;
        foreach ($periods as $period) {
            $line = $this->lineFor($period);
            if ($line === null) {
                continue;
            }
            $lines[] = $line;
            $net += $line['quantity'] * $line['unit_net'];
        }
        if ($lines === []) {
            throw new RuntimeException((string) __('resale.draft.error.nothing_open'));
        }

        $tax = $this->taxes->resolve($organization, $recipient);
        $service ??= new LexofficeDraftInvoiceService((string) $config['api_key'], (string) $config['base_url']);
        $draftId = $service->createDraft(
            $contacts[0],
            $lines,
            (string) __('resale.draft.title'),
            (string) __('resale.draft.introduction', ['count' => count($lines)]),
            (string) ($tax['note'] ?? ''),
            (float) $tax['rate'],
            $recipient->currency->value,
            (string) $config['defaults']['default_tax_type'],
        );

        $stamp = (string) __('resale.draft.note', ['id' => $draftId, 'date' => CarbonImmutable::today()->format('d.m.Y'), 'user' => $user !== null ? $user->name : '']);
        foreach ($periods as $period) {
            $period->forceFill(['note' => trim($stamp)])->save();
        }

        return ['draft_id' => $draftId, 'lines' => count($lines), 'net' => round($net, 2), 'periods' => $periods->count()];
    }

    /**
     * @return array{name: string, description: string, quantity: float, unit_name: string, unit_net: float}|null
     */
    private function lineFor(ResalePeriod $period): ?array {
        $subscription = $period->subscription;
        $sale = $subscription->sale_unit_price;
        if ($sale === null) {
            return null; // ohne Verkaufspreis kein Vorschlag
        }
        $openMonths = max(0.0, $period->requiredMonths() - $period->coveredMonths());
        if ($openMonths <= 0.001) {
            return null;
        }
        $termMonths = $period->termMonths();
        $monthly = $subscription->lexofficeArticle !== null && mb_strtolower(trim((string) $subscription->lexofficeArticle->unit_name)) === 'monat';
        $holder = $subscription->foreignCustomer !== null ? (string) __('resale.draft.end_customer', ['name' => $subscription->foreignCustomer->name]) . ' · ' : '';

        return [
            'name' => $subscription->lexofficeArticle !== null ? $subscription->lexofficeArticle->name : $subscription->label,
            'description' => $holder . $period->label() . ($period->quantity > 1 ? ' · ' . $period->quantity . ' × ' : ''),
            'quantity' => $monthly ? round($openMonths, 2) : round($openMonths / $termMonths, 3),
            'unit_name' => $monthly ? 'Monat' : (string) __('resale.draft.unit_piece'),
            'unit_net' => $monthly ? round($sale->toFloat() / $termMonths, 4) : round($sale->toFloat(), 2),
        ];
    }
}
