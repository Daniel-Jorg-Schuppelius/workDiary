<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceMirrorSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\User\Permission;
use App\Models\{Customer, LexofficeVoucher, LexofficeVoucherLine, Organization};
use App\Services\Reselling\Mirror\{InvoiceMirrorSource, MirrorLine, MirrorVoucher};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Gate, Route};

/**
 * Lexoffice-Belegspiegel als Quelle des Reselling-Registers (Feature 152,
 * Review 2026-09-10, Spiegel-Abstraktion): übersetzt `LexofficeVoucherLine`
 * in {@see MirrorLine}s. Empfänger sind die Kunden hinter den verknüpften
 * Kontakten ({@see LexofficeContactMap}); ein Empfängerfilter läuft über
 * diese Verknüpfung (B13). Für die Anzeige fällt der Empfänger auf den vom
 * Belegabgleich gesetzten `voucher.customer_id` zurück (G), sonst bleibt nur
 * der Kontakt (`contact:<id>`).
 */
final class LexofficeInvoiceMirrorSource implements InvoiceMirrorSource {
    public const KEY = 'lexoffice';

    public function __construct(private readonly LicenseArticleClassifier $classifier = new LicenseArticleClassifier()) {}

    /**
     * Leseseite je Aufruf neu: die Quelle lebt im Singleton-Spiegel, der
     * Artikel-Cache der Leseseite darf keinen Aufruf überdauern.
     */
    private function reader(): LexofficeRecipientInvoiceLines {
        return new LexofficeRecipientInvoiceLines($this->classifier);
    }

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new LexofficeVoucherLine)->getMorphClass();
    }

    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection {
        $map = LexofficeContactMap::forOrganization($organization);

        return $this->map($organization, $this->reader()->for($organization, $this->contacts($map, $recipientCustomerIds), $from, $to), $map);
    }

    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection {
        $map = LexofficeContactMap::forOrganization($organization);

        return $this->map($organization, $this->reader()->voided($organization, $this->contacts($map, $recipientCustomerIds)), $map);
    }

    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection {
        $map = LexofficeContactMap::forOrganization($organization);

        return $this->map($organization, $this->reader()->creditNotes($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from), $map);
    }

    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection {
        $map = LexofficeContactMap::forOrganization($organization);
        $vouchers = $this->reader()->vouchers($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from, $limit);
        $names = $this->customerNames($organization, $vouchers->flatMap(static fn(LexofficeVoucher $v): Collection => $v->lines), $map);
        $canPreview = self::canPreview();

        return $vouchers->map(function (LexofficeVoucher $voucher) use ($map, $names, $canPreview): MirrorVoucher {
            $lines = [];
            foreach ($voucher->lines as $line) {
                $lines[] = $this->toLine($line, $map, $names, $canPreview);
            }
            $customerId = $this->customerIdOf($voucher, $map);

            return new MirrorVoucher(
                sourceKey: self::KEY,
                voucherKey: (string) $voucher->id,
                voucherNumber: $voucher->voucher_number,
                voucherDate: self::date($voucher->voucher_date),
                voucherStatus: self::status($voucher),
                isCreditNote: (string) $voucher->voucher_type === 'creditnote',
                recipientCustomerId: $customerId,
                recipientName: self::recipientName($voucher, $customerId, $names),
                voucherText: $voucher->voucher_text,
                voucherTextHint: $voucher->voucherTextHint(),
                serviceFrom: self::date($voucher->service_starts_on),
                serviceTo: self::date($voucher->service_ends_on),
                permalink: $voucher->lexofficePermalink(),
                previewUrl: $canPreview ? self::previewUrl($voucher->id) : null,
                lines: $lines,
            );
        })->values();
    }

    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int {
        $map = LexofficeContactMap::forOrganization($organization);

        return $this->reader()->pendingCount($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from);
    }

    public function unlinkedLines(Organization $organization, int $limit): array {
        $query = $this->reader()->unlinkedQuery($organization);
        $total = (clone $query)->count();
        $rows = $query
            ->with(['voucher:id,external_id,contact_external_id,customer_id,voucher_type,voucher_number,voucher_date,voucher_status,service_starts_on,service_ends_on,voucher_text,recipient_name', 'article:id,name,unit_name,resale_role'])
            ->orderByDesc(LexofficeVoucher::query()->withoutGlobalScopes()->select('voucher_date')->whereColumn('lexoffice_vouchers.id', 'lexoffice_voucher_lines.voucher_id'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return ['total' => $total, 'lines' => $this->map($organization, $rows, LexofficeContactMap::forOrganization($organization))];
    }

    public function linesByIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }
        $rows = LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->with(['voucher', 'article:id,name,unit_name,resale_role'])
            ->get();

        return $this->map($organization, $rows, LexofficeContactMap::forOrganization($organization))->keyBy(static fn(MirrorLine $line): int => $line->morphId);
    }

    public function lineByKey(Organization $organization, string $key): ?MirrorLine {
        $id = Sqid::decode(LexofficeVoucherLine::class, $key);

        return $id === null ? null : $this->linesByIds($organization, [$id])->get($id);
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return LexofficeContactMap::forCustomer($recipient)->byCustomer($recipient->id) !== [];
    }

    public function constrainProposalLinks(Organization $organization, Builder $links): void {
        // Entwürfe werden nicht gespiegelt (B20): jeder Vorschlag auf eine Spiegelposition ist ersetzbar.
    }

    public function draftBecameInvoice(Organization $organization, string $reference): bool {
        // Lexoffice behält die ID beim Abschließen: gespiegelter Beleg mit der Entwurfs-ID, der kein Entwurf mehr ist.
        return LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('external_id', $reference)
            ->where(static fn(Builder $w) => $w->whereNull('voucher_status')->orWhere('voucher_status', '!=', 'draft'))
            ->exists();
    }

    /**
     * Eine Position als Spiegelzeile — auch ohne Kontaktkarte (Tests, Einzelfälle):
     * dann bleibt der Empfänger der Kunde laut Beleg, sonst nur der Kontakt.
     *
     * @param  array<int, string>  $customerNames  Kunden-ID → Name
     */
    public function toLine(LexofficeVoucherLine $line, ?LexofficeContactMap $map = null, array $customerNames = [], ?bool $canPreview = null): MirrorLine {
        $line->loadMissing('voucher');
        $voucher = $line->voucher;
        $customerId = $this->customerIdOf($voucher, $map);
        $article = $line->lexoffice_article_id !== null ? $line->article : null;

        return new MirrorLine(
            sourceKey: self::KEY,
            morphClass: $line->getMorphClass(),
            morphId: (int) $line->id,
            organizationId: (int) $line->organization_id,
            key: Sqid::encode(LexofficeVoucherLine::class, $line->id),
            voucherKey: (string) $voucher->id,
            voucherNumber: $voucher->voucher_number,
            voucherDate: self::date($voucher->voucher_date),
            voucherStatus: self::status($voucher),
            isCreditNote: (string) $voucher->voucher_type === 'creditnote',
            recipientCustomerId: $customerId,
            recipientKey: 'contact:' . (string) $voucher->contact_external_id,
            recipientName: self::recipientName($voucher, $customerId, $customerNames),
            articleKey: $line->lexoffice_article_id !== null ? 'lex:' . $line->lexoffice_article_id : null,
            articleName: $article?->name,
            articleIsLicence: $line->getAttribute('is_license') !== null ? (bool) $line->getAttribute('is_license') : $this->classifier->isLicense($article),
            name: $line->name,
            description: $line->description,
            quantity: (float) $line->quantity,
            unitName: $line->unit_name,
            unitNet: $line->unit_net,
            totalNet: $line->total_net,
            currency: $line->currency,
            serviceFrom: self::date($voucher->service_starts_on),
            serviceTo: self::date($voucher->service_ends_on),
            voucherText: $voucher->voucher_text,
            voucherTextHint: $voucher->voucherTextHint(),
            position: (int) $line->position,
            permalink: $voucher->lexofficePermalink(),
            previewUrl: ($canPreview ?? self::canPreview()) ? self::previewUrl((int) $voucher->id) : null,
        );
    }

    /**
     * Kontakte der Empfänger: null = alle; ohne verknüpften Kontakt [] (keine Positionen).
     *
     * @param  list<int>|null  $recipientCustomerIds
     * @return list<string>|null
     */
    private function contacts(LexofficeContactMap $map, ?array $recipientCustomerIds): ?array {
        if ($recipientCustomerIds === null) {
            return null;
        }
        $contacts = [];
        foreach ($recipientCustomerIds as $customerId) {
            foreach ($map->byCustomer($customerId) as $contact) {
                $contacts[$contact] = true;
            }
        }

        return array_keys($contacts);
    }

    /**
     * @param  Collection<int, LexofficeVoucherLine>  $rows
     * @return Collection<int, MirrorLine>
     */
    private function map(Organization $organization, Collection $rows, LexofficeContactMap $map): Collection {
        $names = $this->customerNames($organization, $rows, $map);
        $canPreview = self::canPreview();

        return $rows->map(fn(LexofficeVoucherLine $line): MirrorLine => $this->toLine($line, $map, $names, $canPreview))->values();
    }

    /**
     * Namen der Empfänger-Kunden der geladenen Positionen (eine Abfrage).
     *
     * @param  Collection<int, LexofficeVoucherLine>  $rows
     * @return array<int, string>
     */
    private function customerNames(Organization $organization, Collection $rows, LexofficeContactMap $map): array {
        $ids = [];
        foreach ($rows as $line) {
            $id = $this->customerIdOf($line->voucher, $map);
            if ($id !== null) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }
        /** @var array<int, string> $names */
        $names = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', array_keys($ids))->pluck('name', 'id')->all();

        return $names;
    }

    private function customerIdOf(LexofficeVoucher $voucher, ?LexofficeContactMap $map): ?int {
        $mapped = $map?->byContact((string) $voucher->contact_external_id);

        return $mapped ?? ($voucher->customer_id !== null ? (int) $voucher->customer_id : null);
    }

    /**
     * @param  array<int, string>  $customerNames
     */
    private static function recipientName(LexofficeVoucher $voucher, ?int $customerId, array $customerNames): ?string {
        $name = $customerId !== null ? ($customerNames[$customerId] ?? null) : null;
        if ($name === null) {
            $name = trim((string) $voucher->recipient_name);
        }

        return $name !== '' ? $name : null;
    }

    private static function status(LexofficeVoucher $voucher): string {
        return match ((string) $voucher->voucher_status) {
            'draft' => MirrorLine::STATUS_DRAFT,
            'voided' => MirrorLine::STATUS_VOIDED,
            'paid', 'paidoff' => MirrorLine::STATUS_PAID,
            default => MirrorLine::STATUS_ISSUED,
        };
    }

    private static function date(mixed $value): ?CarbonImmutable {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }

    private static function canPreview(): bool {
        return Gate::allows(Permission::VoucherViewAny->value);
    }

    private static function previewUrl(int $voucherId): ?string {
        return Route::has('lexoffice.vouchers.preview') ? route('lexoffice.vouchers.preview', Sqid::encode(LexofficeVoucher::class, $voucherId)) : null;
    }
}
