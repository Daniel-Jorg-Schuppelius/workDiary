<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherMirrorSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Mirror;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\{AccountingVoucherState, BillingMode};
use App\Models\Customer\Customer;
use App\Models\Finance\{AccountingVoucher, AccountingVoucherLine};
use App\Models\Platform\Organization;
use App\Services\Billing\BillingModeResolver;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Reselling\Register\LicenseArticleClassifier;
use App\Support\{MorphMap, Sqid};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Spiegelquelle über den allgemeinen Belegspiegel (Feature 185, MVP-1424):
 * eine Quelle für alle Programme, deren Puller Positionen liefern (sevDesk,
 * easybill, Invoice Ninja, Akaunting, InvoicePlane). Empfänger ist der
 * Kunde, den der Spiegel am Beleg aufgelöst hat (`customer_id`); Beleg-
 * datum ist das einzige Datum (kein Leistungszeitraum im Spiegel).
 */
final class AccountingVoucherMirrorSource implements InvoiceMirrorSource {
    public const KEY = 'accounting';

    /** Kandidaten für „ohne Bezug“: jüngste Positionen, die geprüft werden, bevor das Limit greift. */
    private const UNLINKED_SCAN = 500;

    public function __construct(
        private readonly BillingModeResolver $billingModes,
        private readonly VoucherPullerRegistry $pullers,
    ) {}

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        // Alias = Tabellenname (Morph-Map-Konvention) — ohne Morph-Map-Zugriff, damit die Registrierung beim Boot
        // auch läuft, während `morph-map:generate` die Karte gerade neu schreibt.
        return (new AccountingVoucherLine)->getTable();
    }

    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, bool $licenceOnly = true): Collection {
        $query = $this->lineQuery($organization, $recipientCustomerIds, [DocumentKind::Invoice, DocumentKind::DownPayment, DocumentKind::Other], false, $from, $to);

        return $licenceOnly ? $this->licenceLines($organization, $query->get()) : $this->map($organization, $query->orderBy('voucher_id')->orderBy('position')->get())->values();
    }

    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection {
        $query = $this->lineQuery($organization, $recipientCustomerIds, [DocumentKind::Invoice, DocumentKind::DownPayment, DocumentKind::Other], true, null, null);

        return $this->licenceLines($organization, $query->get());
    }

    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection {
        if ($recipientCustomerIds === []) {
            return collect();
        }
        $query = $this->lineQuery($organization, $recipientCustomerIds, [DocumentKind::CreditNote], false, $from, null);

        return $this->licenceLines($organization, $query->get());
    }

    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection {
        if ($recipientCustomerIds === []) {
            return collect();
        }
        $vouchers = $this->voucherQuery($organization, $recipientCustomerIds, [DocumentKind::Invoice, DocumentKind::DownPayment, DocumentKind::Other, DocumentKind::CreditNote], false)
            ->where('voucher_date', '>=', DateRange::day($from))
            ->with('lines')
            ->orderByDesc('voucher_date')->orderByDesc('id')
            ->limit($limit)
            ->get();
        $classifier = app(LicenseArticleClassifier::class);
        $names = $this->customerNames($organization, array_values(array_map('intval', $vouchers->pluck('customer_id')->filter()->unique()->all())));

        return $vouchers->map(function (AccountingVoucher $voucher) use ($classifier, $names): MirrorVoucher {
            $lines = [];
            foreach ($voucher->lines as $line) {
                $lines[] = $this->toLine($line, $voucher, $classifier, $names);
            }

            return new MirrorVoucher(
                sourceKey: self::KEY,
                voucherKey: (string) $voucher->id,
                voucherNumber: $voucher->voucher_number,
                voucherDate: self::date($voucher->voucher_date),
                voucherStatus: self::status($voucher),
                isCreditNote: self::isCreditNote($voucher),
                recipientCustomerId: $voucher->customer_id !== null ? (int) $voucher->customer_id : null,
                recipientName: $voucher->customer_id !== null ? ($names[(int) $voucher->customer_id] ?? null) : null,
                voucherText: null,
                voucherTextHint: null,
                serviceFrom: null,
                serviceTo: null,
                permalink: null,
                previewUrl: null,
                lines: $lines,
            );
        })->values();
    }

    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int {
        if ($recipientCustomerIds === []) {
            return 0;
        }

        $query = $this->voucherQuery($organization, $recipientCustomerIds, [DocumentKind::Invoice, DocumentKind::DownPayment, DocumentKind::Other], false)
            ->whereNull('lines_synced_at');
        if ($from !== null) {
            $query->where('voucher_date', '>=', DateRange::day($from));
        }

        return $query->count();
    }

    public function unlinkedLines(Organization $organization, int $limit): array {
        $alias = MorphMap::alias(AccountingVoucherLine::class);
        $rows = $this->lineQuery($organization, null, [DocumentKind::Invoice, DocumentKind::DownPayment, DocumentKind::Other], false, null, null)
            ->whereNotExists(static function ($sub) use ($organization, $alias): void {
                $sub->from('resale_period_links')
                    ->whereColumn('resale_period_links.linkable_id', 'accounting_voucher_lines.id')
                    ->where('resale_period_links.linkable_type', $alias)
                    ->where('resale_period_links.organization_id', $organization->id);
            })
            ->orderByDesc('accounting_voucher_lines.id')
            ->limit(self::UNLINKED_SCAN)
            ->get();
        $lines = $this->licenceLines($organization, $rows);

        return ['total' => $lines->count(), 'lines' => $lines->take($limit)->values()];
    }

    public function linesByIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }
        $rows = AccountingVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->with('voucher')
            ->get();

        return $this->map($organization, $rows)->keyBy(static fn(MirrorLine $line): int => $line->morphId);
    }

    public function lineByKey(Organization $organization, string $key): ?MirrorLine {
        $id = Sqid::decode(AccountingVoucherLine::class, $key);

        return $id === null ? null : $this->linesByIds($organization, [$id])->get($id);
    }

    /**
     * Ein Empfänger mit externer Hoheit (außer Lexoffice, das seine eigene
     * Quelle hat) und einem eingerichteten Belegabruf — oder mit schon
     * gespiegelten Ausgangsbelegen.
     */
    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        $mode = $this->billingModes->effectiveFor($recipient);
        if ($mode->isExternal() && $mode !== BillingMode::Lexoffice && $this->pullers->configuredFor((int) $organization->id) !== []) {
            return true;
        }

        return AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $recipient->id)
            ->where('direction', DocumentDirection::Outgoing->value)
            ->exists();
    }

    public function draftBecameInvoice(Organization $organization, string $reference): bool {
        return AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(static fn(Builder $w) => $w->where('external_id', $reference)->orWhereLikeEscaped('external_id', ':' . $reference, 'suffix'))
            ->where(static fn(Builder $w) => $w->whereNull('voucher_state')->orWhere('voucher_state', '!=', AccountingVoucherState::Draft->value))
            ->exists();
    }

    public function constrainProposalLinks(Organization $organization, Builder $links): void {
        // Gespiegelte Positionen sind nie Entwürfe des Registers: jeder Vorschlag darauf ist ersetzbar.
    }

    /**
     * Positionen ausgehender Belege der Arten, gültig (weder Entwurf noch storniert) oder storniert.
     *
     * @param  list<int>|null  $recipientCustomerIds
     * @param  list<DocumentKind>  $kinds
     * @return Builder<AccountingVoucherLine>
     */
    private function lineQuery(Organization $organization, ?array $recipientCustomerIds, array $kinds, bool $voided, ?CarbonImmutable $from, ?CarbonImmutable $to): Builder {
        $vouchers = $this->voucherQuery($organization, $recipientCustomerIds, $kinds, $voided);
        if ($from !== null) {
            $vouchers->where('voucher_date', '>=', DateRange::day($from));
        }
        if ($to !== null) {
            $vouchers->where('voucher_date', '<', DateRange::dayAfter($to));
        }

        return AccountingVoucherLine::query()->withoutGlobalScopes()
            ->where('accounting_voucher_lines.organization_id', $organization->id)
            ->whereIn('voucher_id', $vouchers->select('accounting_vouchers.id'))
            ->with('voucher');
    }

    /**
     * @param  list<int>|null  $recipientCustomerIds
     * @param  list<DocumentKind>  $kinds
     * @return Builder<AccountingVoucher>
     */
    private function voucherQuery(Organization $organization, ?array $recipientCustomerIds, array $kinds, bool $voided): Builder {
        $query = AccountingVoucher::query()->withoutGlobalScopes()
            ->where('accounting_vouchers.organization_id', $organization->id)
            ->where('direction', DocumentDirection::Outgoing->value)
            ->whereNull('source_deleted_at')
            ->where('is_cancellation', false)
            ->whereIn('document_kind', array_map(static fn(DocumentKind $k): string => $k->value, $kinds));
        if ($voided) {
            $query->where('voucher_state', AccountingVoucherState::Cancelled->value);
        } else {
            $query->whereNotIn('voucher_state', [AccountingVoucherState::Draft->value, AccountingVoucherState::Cancelled->value]);
        }
        if ($recipientCustomerIds !== null) {
            $query->whereIn('customer_id', $recipientCustomerIds);
        }

        return $query;
    }

    /**
     * Nur Lizenzpositionen (Einstufung des Artikels bzw. Name).
     *
     * @param  Collection<int, AccountingVoucherLine>  $rows
     * @return Collection<int, MirrorLine>
     */
    private function licenceLines(Organization $organization, Collection $rows): Collection {
        return $this->map($organization, $rows)->filter(static fn(MirrorLine $line): bool => $line->articleIsLicence)->values();
    }

    /**
     * @param  Collection<int, AccountingVoucherLine>  $rows
     * @return Collection<int, MirrorLine>
     */
    private function map(Organization $organization, Collection $rows): Collection {
        $classifier = app(LicenseArticleClassifier::class);
        $ids = [];
        foreach ($rows as $line) {
            if ($line->voucher->customer_id !== null) {
                $ids[(int) $line->voucher->customer_id] = true;
            }
        }
        $names = $this->customerNames($organization, array_keys($ids));
        $lines = [];
        foreach ($rows as $line) {
            $lines[] = $this->toLine($line, $line->voucher, $classifier, $names);
        }

        return collect($lines);
    }

    /** @param  array<int, string>  $names */
    private function toLine(AccountingVoucherLine $line, AccountingVoucher $voucher, LicenseArticleClassifier $classifier, array $names): MirrorLine {
        $currency = CurrencyCode::tryFrom((string) $line->currency) ?? CurrencyCode::Euro;
        $isCreditNote = self::isCreditNote($voucher);
        $total = Money::of((string) $line->total_net, $currency);
        $customerId = $voucher->customer_id !== null ? (int) $voucher->customer_id : null;

        return new MirrorLine(
            sourceKey: self::KEY,
            morphClass: $line->getMorphClass(),
            morphId: (int) $line->id,
            organizationId: (int) $line->organization_id,
            key: Sqid::encode(AccountingVoucherLine::class, $line->id),
            voucherKey: (string) $voucher->id,
            voucherExternalId: (string) $voucher->external_id,
            voucherNumber: $voucher->voucher_number,
            voucherDate: self::date($voucher->voucher_date),
            voucherStatus: self::status($voucher),
            isCreditNote: $isCreditNote,
            recipientCustomerId: $customerId,
            recipientKey: $customerId !== null ? 'customer:' . $customerId : 'contact:' . $voucher->plugin_id . ':' . (string) $voucher->contact_external_id,
            recipientName: $customerId !== null ? ($names[$customerId] ?? null) : null,
            articleKey: $line->article_ref,
            articleName: null,
            articleIsLicence: $classifier->isLicense((int) $line->organization_id, $line->article_ref, $line->name),
            name: $line->name,
            description: $line->description,
            quantity: (float) $line->quantity,
            unitName: $line->unit_name,
            unitNet: Money::of((string) $line->unit_net, $currency, 4),
            totalNet: $isCreditNote && ! $total->isNegative() ? $total->negated() : $total,
            currency: $currency,
            serviceFrom: null,
            serviceTo: null,
            voucherText: null,
            voucherTextHint: null,
            position: (int) $line->position,
            permalink: null,
            previewUrl: null,
        );
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function customerNames(Organization $organization, array $ids): array {
        if ($ids === []) {
            return [];
        }
        /** @var array<int, string> $names */
        $names = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $names;
    }

    private static function isCreditNote(AccountingVoucher $voucher): bool {
        return (DocumentKind::tryFrom((string) $voucher->document_kind) ?? DocumentKind::Other) === DocumentKind::CreditNote;
    }

    private static function status(AccountingVoucher $voucher): string {
        return match ($voucher->voucher_state) {
            AccountingVoucherState::Draft => MirrorLine::STATUS_DRAFT,
            AccountingVoucherState::Cancelled => MirrorLine::STATUS_VOIDED,
            AccountingVoucherState::Paid => MirrorLine::STATUS_PAID,
            default => MirrorLine::STATUS_ISSUED,
        };
    }

    private static function date(mixed $value): ?CarbonImmutable {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }
}
