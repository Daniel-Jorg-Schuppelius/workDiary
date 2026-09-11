<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalInvoiceMirrorSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Mirror;

use App\Enums\Reselling\ResaleArticleRole;
use App\Models\{Article, Customer, Invoice, InvoiceItem, Organization};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Services\Finance\BillingModeResolver;
use App\Services\Reselling\Marketplace\ProductNameMatcher;
use App\Services\Reselling\Register\LicenseArticleClassifier;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Lokale Rechnungen als Spiegelquelle (Feature 152, Review 2026-09-10):
 * `invoices`/`invoice_items` bei lokaler Rechnungshoheit
 * ({@see BillingModeResolver}). Lizenzposition: die Einstufung des lokalen
 * Artikels (`articles.resale_role`, {@see LicenseArticleClassifier})
 * entscheidet zuerst; ohne Einstufung gilt der Artikel eines Abos
 * (`resale_subscriptions.article_id`) oder der Namensmatch der Position gegen
 * die Abo-Labels der Organisation ({@see ProductNameMatcher}). Entwürfe sind
 * keine Kandidaten; Bezüge auf Entwurfspositionen (lokaler Rechnungsentwurf)
 * bleiben beim Vorschlagslauf stehen.
 *
 * Leistungszeitraum: `service_from`/`service_to` der Position (der lokale
 * Rechnungsentwurf schreibt die Abo-Periode), sonst nur das Leistungsdatum
 * (`serviceTo` leer, Lizenzmonate aus Menge/Einheit).
 */
final class LocalInvoiceMirrorSource implements InvoiceMirrorSource {
    public const KEY = 'local';

    private const KIND_INVOICE = 'invoice';

    private const KIND_VOIDED = 'voided';

    private const KIND_CREDIT_NOTE = 'creditnote';

    private const ISSUED = [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID];

    private const NOT_INVOICES = [Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION, Invoice::TYPE_PROFORMA];

    public function __construct(
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
        private readonly BillingModeResolver $billingModes = new BillingModeResolver(),
        private readonly LicenseArticleClassifier $classifier = new LicenseArticleClassifier(),
    ) {}

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new InvoiceItem)->getMorphClass();
    }

    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection {
        if ($recipientCustomerIds === []) {
            return collect();
        }

        return $this->licenceLines($organization, $this->invoiceQuery($organization, $recipientCustomerIds, $from, $to, self::KIND_INVOICE));
    }

    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection {
        if ($recipientCustomerIds === []) {
            return collect();
        }

        return $this->licenceLines($organization, $this->invoiceQuery($organization, $recipientCustomerIds, null, null, self::KIND_VOIDED));
    }

    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection {
        if ($recipientCustomerIds === []) {
            return collect();
        }

        return $this->licenceLines($organization, $this->invoiceQuery($organization, $recipientCustomerIds, $from, null, self::KIND_CREDIT_NOTE));
    }

    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection {
        if ($recipientCustomerIds === []) {
            return collect();
        }
        $products = $this->products($organization);
        $invoices = $this->invoiceQuery($organization, $recipientCustomerIds, $from, null, self::KIND_INVOICE)
            ->with(['customer:id,name', 'items.article:id,name,resale_role'])
            ->orderByDesc('issued_on')->orderByDesc('id')->limit($limit)->get();

        return $invoices->map(function (Invoice $invoice) use ($products): MirrorVoucher {
            $lines = [];
            $serviceTo = null;
            foreach ($invoice->items as $item) {
                $item->setRelation('invoice', $invoice); // Währung der Positionsbeträge kommt vom Beleg (MoneyCast)
                $lines[] = $this->toLine($item, $invoice, $products);
                $to = self::date($item->service_to);
                if ($to !== null && ($serviceTo === null || $to->greaterThan($serviceTo))) {
                    $serviceTo = $to;
                }
            }

            return new MirrorVoucher(
                sourceKey: self::KEY,
                voucherKey: (string) $invoice->id,
                voucherNumber: $invoice->number,
                voucherDate: self::date($invoice->issued_on),
                voucherStatus: self::status($invoice),
                isCreditNote: $invoice->isCreditNote(),
                recipientCustomerId: (int) $invoice->customer_id,
                recipientName: $invoice->customer->name ?? null,
                voucherText: null,
                voucherTextHint: null,
                serviceFrom: self::date($invoice->serviceDateFrom()),
                serviceTo: $serviceTo,
                permalink: self::permalink($invoice),
                previewUrl: null,
                lines: $lines,
            );
        })->values();
    }

    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int {
        return 0;
    }

    public function unlinkedLines(Organization $organization, int $limit): array {
        $products = $this->products($organization);
        $query = $this->itemQuery($organization, $this->invoiceQuery($organization, null, null, null, self::KIND_INVOICE), $products);
        if ($query === null) {
            return ['total' => 0, 'lines' => collect()];
        }
        $query->whereNotIn('id', ResalePeriodLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('linkable_type', $this->morphClass())->select('linkable_id'));
        $rows = (clone $query)
            ->orderByDesc(Invoice::query()->withoutGlobalScopes()->select('issued_on')->whereColumn('invoices.id', 'invoice_items.invoice_id'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
        $lines = $this->map($rows, $products)->filter(static fn(MirrorLine $line): bool => $line->articleIsLicence)->values();

        return ['total' => $query->count(), 'lines' => $lines];
    }

    public function linesByIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }
        $rows = InvoiceItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->with(['invoice.customer:id,name', 'article:id,name,resale_role'])
            ->get();

        return $this->map($rows, $this->products($organization))->keyBy(static fn(MirrorLine $line): int => $line->morphId);
    }

    public function lineByKey(Organization $organization, string $key): ?MirrorLine {
        $id = Sqid::decode(InvoiceItem::class, $key);

        return $id === null ? null : $this->linesByIds($organization, [$id])->get($id);
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return ! $this->billingModes->effectiveFor($recipient)->isExternal();
    }

    public function constrainProposalLinks(Organization $organization, Builder $links): void {
        // Nur Positionen ausgestellter Rechnungen — der Entwurfsbezug (draftLocal) ist kein Vorschlag des Laufs.
        $links->whereIn('linkable_id', InvoiceItem::query()->withoutGlobalScopes()->select('id')
            ->where('organization_id', $organization->id)
            ->whereIn('invoice_id', Invoice::query()->withoutGlobalScopes()->select('id')->where('organization_id', $organization->id)->where('status', '!=', Invoice::STATUS_DRAFT)));
    }

    public function draftBecameInvoice(Organization $organization, string $reference): bool {
        // Der lokale Entwurf trägt schon die Rechnungsnummer: zur Rechnung geworden = nicht mehr Entwurf.
        return Invoice::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('number', $reference)
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->exists();
    }

    /**
     * Rechnungen der Empfänger im Fenster: gültige Ausgangsrechnungen, gültige
     * Gutschriften oder — für die Erklärung einer Lücke — stornierte Rechnungen.
     *
     * @param  list<int>|null  $recipientCustomerIds
     * @param  self::KIND_*  $kind
     * @return Builder<Invoice>
     */
    private function invoiceQuery(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from, ?CarbonImmutable $to, string $kind): Builder {
        $query = Invoice::query()->withoutGlobalScopes()->where('organization_id', $organization->id);
        if ($kind === self::KIND_VOIDED) {
            $query->whereNotIn('type', self::NOT_INVOICES)->where(static fn(Builder $w) => $w->where('status', Invoice::STATUS_CANCELLED)->orWhereNotNull('cancelled_at'));
        } elseif ($kind === self::KIND_CREDIT_NOTE) {
            $query->where('type', Invoice::TYPE_CREDIT_NOTE)->whereIn('status', self::ISSUED);
        } else {
            $query->whereNotIn('type', self::NOT_INVOICES)->whereIn('status', self::ISSUED);
        }
        if ($recipientCustomerIds !== null) {
            $query->whereIn('customer_id', $recipientCustomerIds);
        }
        if ($from !== null) {
            $query->where(static fn(Builder $w) => $w->where('issued_on', '>=', DateRange::day($from))
                ->orWhereHas('items', static fn(Builder $i) => $i->where('service_date', '>=', DateRange::day($from))));
        }
        if ($to !== null) {
            $query->where('issued_on', '<', DateRange::dayAfter($to));
        }

        return $query;
    }

    /**
     * Lizenzpositionen der Rechnungen: Vorfilter in SQL (Abo-Artikel, als
     * Abo-Produkt eingestufte Artikel, Artikel- oder Positionstext mit
     * Abo-Label; ausgeschlossene Artikel nie), Entscheidung in `isLicence()`.
     *
     * @param  Builder<Invoice>  $invoices
     * @return Collection<int, MirrorLine>
     */
    private function licenceLines(Organization $organization, Builder $invoices): Collection {
        $products = $this->products($organization);
        $query = $this->itemQuery($organization, $invoices, $products);
        if ($query === null) {
            return collect();
        }

        return $this->map($query->orderBy('invoice_id')->orderBy('position')->get(), $products)
            ->filter(static fn(MirrorLine $line): bool => $line->articleIsLicence)
            ->values();
    }

    /**
     * @param  Builder<Invoice>  $invoices
     * @param  array{articles: array<int, true>, labels: list<string>, excluded: array<int, true>}  $products
     * @return Builder<InvoiceItem>|null null, wenn die Organisation keine Abo-Produkte kennt
     */
    private function itemQuery(Organization $organization, Builder $invoices, array $products): ?Builder {
        if ($products['articles'] === [] && $products['labels'] === []) {
            return null;
        }
        $labels = $products['labels'];
        $query = InvoiceItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('invoice_id', $invoices->select('id'));
        if ($products['excluded'] !== []) {
            $query->where(static fn(Builder $w) => $w->whereNull('article_id')->orWhereNotIn('article_id', array_keys($products['excluded'])));
        }

        return $query
            ->where(static function (Builder $w) use ($products, $labels, $organization): void {
                $w->whereIn('article_id', $products['articles'] === [] ? [0] : array_keys($products['articles']));
                foreach ($labels as $label) {
                    $w->orWhereLikeEscaped('description', $label);
                }
                if ($labels !== []) {
                    $w->orWhereIn('article_id', Article::query()->withoutGlobalScopes()->select('id')->where('organization_id', $organization->id)
                        ->where(static function (Builder $a) use ($labels): void {
                            foreach ($labels as $label) {
                                $a->orWhereLikeEscaped('name', $label);
                            }
                        }));
                }
            })
            ->with(['invoice.customer:id,name', 'article:id,name,resale_role']);
    }

    /**
     * Abo-Produkte der Organisation: lokale Artikel der Abos, als Abo-Produkt
     * eingestufte Artikel, die Abo-Labels — und die ausgeschlossenen Artikel.
     *
     * @return array{articles: array<int, true>, labels: list<string>, excluded: array<int, true>}
     */
    private function products(Organization $organization): array {
        $articles = [];
        $labels = [];
        $excluded = [];
        $rows = ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get(['article_id', 'label']);
        foreach ($rows as $subscription) {
            if ($subscription->article_id !== null) {
                $articles[(int) $subscription->article_id] = true;
            }
            $label = trim($subscription->label);
            if ($label !== '') {
                $labels[ProductNameMatcher::normalize($label)] = $label;
            }
        }
        $classified = Article::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNotNull('resale_role')->get(['id', 'resale_role']);
        foreach ($classified as $article) {
            if ($article->resale_role === ResaleArticleRole::License) {
                $articles[(int) $article->id] = true;
            } elseif ($article->resale_role === ResaleArticleRole::Excluded) {
                $excluded[(int) $article->id] = true;
                unset($articles[(int) $article->id]);
            }
        }

        return ['articles' => $articles, 'labels' => array_values($labels), 'excluded' => $excluded];
    }

    /**
     * @param  Collection<int, InvoiceItem>  $rows
     * @param  array{articles: array<int, true>, labels: list<string>, excluded: array<int, true>}  $products
     * @return Collection<int, MirrorLine>
     */
    private function map(Collection $rows, array $products): Collection {
        /** @var list<MirrorLine> $lines */
        $lines = [];
        foreach ($rows as $item) {
            $invoice = $item->invoice;
            if ($invoice !== null) {
                $lines[] = $this->toLine($item, $invoice, $products);
            }
        }

        return collect($lines);
    }

    /**
     * @param  array{articles: array<int, true>, labels: list<string>, excluded: array<int, true>}  $products
     */
    private function toLine(InvoiceItem $item, Invoice $invoice, array $products): MirrorLine {
        $currency = $invoice->currencyCode();
        $articleName = $item->article?->name;
        $isCreditNote = $invoice->isCreditNote();
        $total = $item->amount ?? Money::of('0', $currency);

        return new MirrorLine(
            sourceKey: self::KEY,
            morphClass: $item->getMorphClass(),
            morphId: (int) $item->id,
            organizationId: (int) ($item->organization_id ?? $invoice->organization_id),
            key: Sqid::encode(InvoiceItem::class, $item->id),
            voucherKey: (string) $invoice->id,
            voucherNumber: $invoice->number,
            voucherDate: self::date($invoice->issued_on),
            voucherStatus: self::status($invoice),
            isCreditNote: $isCreditNote,
            recipientCustomerId: (int) $invoice->customer_id,
            recipientKey: 'customer:' . $invoice->customer_id,
            recipientName: $invoice->customer->name ?? null,
            articleKey: $item->article_id !== null ? 'art:' . $item->article_id : null,
            articleName: $articleName,
            articleIsLicence: $this->isLicence($item, $products),
            name: (string) $item->description,
            description: null,
            quantity: (float) $item->quantity,
            unitName: $item->unit,
            unitNet: $item->unit_price ?? Money::of('0', $currency, 4),
            totalNet: $isCreditNote && ! $total->isNegative() ? $total->negated() : $total,
            currency: $currency,
            serviceFrom: self::date($item->service_from ?? $item->service_date),
            serviceTo: self::date($item->service_to),
            voucherText: null,
            voucherTextHint: null,
            position: (int) $item->position,
            permalink: self::permalink($invoice),
            previewUrl: null,
        );
    }

    /**
     * Einstufung des Artikels zuerst (Betreiber-Override), dann Abo-Artikel, dann Namensmatch.
     *
     * @param  array{articles: array<int, true>, labels: list<string>, excluded: array<int, true>}  $products
     */
    private function isLicence(InvoiceItem $item, array $products): bool {
        $article = $item->article;
        if ($article !== null && $article->resale_role !== null) {
            return $this->classifier->isLicense($article);
        }
        if ($item->article_id !== null && isset($products['articles'][(int) $item->article_id])) {
            return true;
        }
        $text = trim((string) ($item->article?->name) . ' ' . (string) $item->description);
        if ($text === '') {
            return false;
        }
        foreach ($products['labels'] as $label) {
            if ($this->matcher->matches($label, $text)) {
                return true;
            }
        }

        return false;
    }

    private static function status(Invoice $invoice): string {
        return match (true) {
            $invoice->status === Invoice::STATUS_DRAFT => MirrorLine::STATUS_DRAFT,
            $invoice->status === Invoice::STATUS_CANCELLED || $invoice->cancelled_at !== null => MirrorLine::STATUS_VOIDED,
            $invoice->status === Invoice::STATUS_PAID => MirrorLine::STATUS_PAID,
            default => MirrorLine::STATUS_ISSUED,
        };
    }

    private static function date(mixed $value): ?CarbonImmutable {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }

    private static function permalink(Invoice $invoice): ?string {
        return Route::has('invoices.show') ? route('invoices.show', $invoice) : null;
    }
}
