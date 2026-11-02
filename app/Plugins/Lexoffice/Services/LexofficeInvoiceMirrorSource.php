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
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Services\Reselling\Mirror\{InvoiceMirrorSource, MirrorLine, MirrorVoucher};
use App\Services\Reselling\Plan\PlanTemplate;
use App\Services\Reselling\Register\{LicenseArticleClassifier, LicenseMonths, ResaleRequestMemo};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
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
 *
 * Belegabhängige Werte (Empfänger, Status, Belegtext-Hinweis, Permalink,
 * Vorschau-Route, Zeitraum) entstehen einmal je Beleg, nicht je Position;
 * Leseseite und Kontaktkarte leben je Request im {@see ResaleRequestMemo}
 * (Leistungsanalyse 2026-11-02, perfc-02/perfc-11).
 *
 * @phpstan-type VoucherContext array{customer_id: int|null, recipient_key: string, recipient_name: string|null, voucher_key: string, external_id: string, plan_key: string|null, number: string|null, date: CarbonImmutable|null, status: string, credit_note: bool, service_from: CarbonImmutable|null, service_to: CarbonImmutable|null, text: string|null, text_hint: string|null, permalink: string|null, preview_url: string|null}
 */
final class LexofficeInvoiceMirrorSource implements InvoiceMirrorSource {
    public const KEY = 'lexoffice';

    /**
     * Leseseite je Request: die Quelle lebt im Singleton-Spiegel, der
     * Artikel-Cache der Leseseite gilt für alle Aufrufe eines Requests und
     * keinen darüber hinaus.
     */
    private function reader(): LexofficeRecipientInvoiceLines {
        return $this->memo()->remember('lexoffice-reader', static fn(): LexofficeRecipientInvoiceLines => new LexofficeRecipientInvoiceLines);
    }

    /** Kontaktkarte der Organisation je Request einmal (eine Mappe fragt sie fünf- bis siebenmal). */
    private function contactMap(Organization $organization): LexofficeContactMap {
        return $this->memo()->remember('lexoffice-contact-map:' . $organization->id, static fn(): LexofficeContactMap => LexofficeContactMap::forOrganization($organization));
    }

    private function memo(): ResaleRequestMemo {
        return app(ResaleRequestMemo::class);
    }

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new LexofficeVoucherLine)->getMorphClass();
    }

    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, bool $licenceOnly = true): Collection {
        $map = $this->contactMap($organization);

        return $this->map($organization, $this->reader()->for($organization, $this->contacts($map, $recipientCustomerIds), $from, $to, $licenceOnly), $map);
    }

    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection {
        $map = $this->contactMap($organization);

        return $this->map($organization, $this->reader()->voided($organization, $this->contacts($map, $recipientCustomerIds)), $map);
    }

    public function planLines(Organization $organization, array $planKeys, CarbonImmutable $from): Collection {
        $prefix = LexofficeRecurringPlanSource::KEY . ':';
        $ids = [];
        foreach ($planKeys as $key) {
            if (str_starts_with($key, $prefix) && strlen($key) > strlen($prefix)) {
                $ids[] = substr($key, strlen($prefix));
            }
        }
        if ($ids === []) {
            return collect();
        }

        return $this->map($organization, $this->reader()->forTemplates($organization, array_values(array_unique($ids)), $from), $this->contactMap($organization));
    }

    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection {
        $map = $this->contactMap($organization);

        return $this->map($organization, $this->reader()->creditNotes($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from), $map);
    }

    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection {
        $map = $this->contactMap($organization);
        $vouchers = $this->reader()->vouchers($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from, $limit);
        $names = $this->customerNames($organization, $vouchers->flatMap(static fn(LexofficeVoucher $v): Collection => $v->lines), $map);
        $canPreview = self::canPreview();
        $classifier = app(LicenseArticleClassifier::class);

        return $vouchers->map(fn(LexofficeVoucher $voucher): MirrorVoucher => $this->toVoucher($voucher, $map, $names, $canPreview, $classifier))->values();
    }

    public function voucherByKey(Organization $organization, string $voucherKey): ?MirrorVoucher {
        if (! is_numeric($voucherKey)) {
            return null;
        }
        $voucher = LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with('lines.article:id,name,unit_name')
            ->find((int) $voucherKey);
        if ($voucher === null) {
            return null;
        }
        $map = $this->contactMap($organization);

        return $this->toVoucher($voucher, $map, $this->customerNames($organization, $voucher->lines, $map), self::canPreview(), app(LicenseArticleClassifier::class));
    }

    /** @param  array<int, string>  $names */
    private function toVoucher(LexofficeVoucher $voucher, LexofficeContactMap $map, array $names, bool $canPreview, LicenseArticleClassifier $classifier): MirrorVoucher {
        $context = self::voucherContext($voucher, $map, $names, $canPreview);
        $lines = [];
        foreach ($voucher->lines as $line) {
            $lines[] = $this->lineFrom($line, $context, $classifier);
        }

        return new MirrorVoucher(
            sourceKey: self::KEY,
            voucherKey: $context['voucher_key'],
            voucherNumber: $context['number'],
            voucherDate: $context['date'],
            voucherStatus: $context['status'],
            isCreditNote: $context['credit_note'],
            recipientCustomerId: $context['customer_id'],
            recipientName: $context['recipient_name'],
            voucherText: $context['text'],
            voucherTextHint: $context['text_hint'],
            serviceFrom: $context['service_from'],
            serviceTo: $context['service_to'],
            permalink: $context['permalink'],
            previewUrl: $context['preview_url'],
            lines: $lines,
        );
    }

    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int {
        $map = $this->contactMap($organization);

        return $this->reader()->pendingCount($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from);
    }

    public function unlinkedLines(Organization $organization, int $limit): array {
        $query = $this->reader()->unlinkedQuery($organization);
        $total = (clone $query)->count();
        $rows = $query
            ->with(['voucher:id,external_id,contact_external_id,customer_id,voucher_type,voucher_number,voucher_date,voucher_status,service_starts_on,service_ends_on,voucher_text,recipient_name,recurring_template_id', 'article:id,name,unit_name'])
            ->orderByDesc(LexofficeVoucher::query()->withoutGlobalScopes()->select('voucher_date')->whereColumn('lexoffice_vouchers.id', 'lexoffice_voucher_lines.voucher_id'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return ['total' => $total, 'lines' => $this->map($organization, $rows, $this->contactMap($organization))];
    }

    public function linesByIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }
        $rows = LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->with(['voucher', 'article:id,name,unit_name'])
            ->get();

        return $this->map($organization, $rows, $this->contactMap($organization))->keyBy(static fn(MirrorLine $line): int => $line->morphId);
    }

    public function lineByKey(Organization $organization, string $key): ?MirrorLine {
        $id = Sqid::decode(LexofficeVoucherLine::class, $key);

        return $id === null ? null : $this->linesByIds($organization, [$id])->get($id);
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return $this->contactMap($organization)->byCustomer($recipient->id) !== [];
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

        return $this->lineFrom($line, self::voucherContext($line->voucher, $map, $customerNames, $canPreview ?? self::canPreview()), app(LicenseArticleClassifier::class));
    }

    /**
     * Werte, die alle Positionen eines Belegs teilen — einmal je Beleg statt je Position.
     *
     * @param  array<int, string>  $customerNames
     * @return VoucherContext
     */
    private static function voucherContext(LexofficeVoucher $voucher, ?LexofficeContactMap $map, array $customerNames, bool $canPreview): array {
        $customerId = self::customerIdOf($voucher, $map);

        return [
            'customer_id' => $customerId,
            'recipient_key' => 'contact:' . (string) $voucher->contact_external_id,
            'recipient_name' => self::recipientName($voucher, $customerId, $customerNames),
            'voucher_key' => (string) $voucher->id,
            'external_id' => (string) $voucher->external_id,
            'plan_key' => $voucher->recurring_template_id !== null ? PlanTemplate::keyOf(LexofficeRecurringPlanSource::KEY, (string) $voucher->recurring_template_id) : null,
            'number' => $voucher->voucher_number,
            'date' => self::date($voucher->voucher_date),
            'status' => self::status($voucher),
            'credit_note' => (string) $voucher->voucher_type === 'creditnote',
            'service_from' => self::date($voucher->service_starts_on),
            'service_to' => self::date($voucher->service_ends_on),
            'text' => $voucher->voucher_text,
            'text_hint' => $voucher->voucherTextHint(),
            'permalink' => $voucher->lexofficePermalink(),
            'preview_url' => $canPreview ? self::previewUrl((int) $voucher->id) : null,
        ];
    }

    /** @param  VoucherContext  $context */
    private function lineFrom(LexofficeVoucherLine $line, array $context, LicenseArticleClassifier $classifier): MirrorLine {
        $article = $line->lexoffice_article_id !== null ? $line->article : null;
        $articleKey = $line->lexoffice_article_id !== null ? ArticleCatalog::key(LexofficeArticleCatalogSource::PREFIX, (int) $line->lexoffice_article_id) : null;
        // Sammelrechnung: der Belegzeitraum umspannt alle Positionen, der eigene steht im Text.
        $period = LicenseMonths::periodInText($line->description);

        return new MirrorLine(
            sourceKey: self::KEY,
            morphClass: $line->getMorphClass(),
            morphId: (int) $line->id,
            organizationId: (int) $line->organization_id,
            key: Sqid::encode(LexofficeVoucherLine::class, $line->id),
            voucherKey: $context['voucher_key'],
            voucherExternalId: $context['external_id'],
            planKey: $context['plan_key'],
            voucherNumber: $context['number'],
            voucherDate: $context['date'],
            voucherStatus: $context['status'],
            isCreditNote: $context['credit_note'],
            recipientCustomerId: $context['customer_id'],
            recipientKey: $context['recipient_key'],
            recipientName: $context['recipient_name'],
            articleKey: $articleKey,
            articleName: $article?->name,
            articleIsLicence: $line->getAttribute('is_license') !== null ? (bool) $line->getAttribute('is_license') : $classifier->isLicense((int) $line->organization_id, $articleKey, $article?->name),
            name: $line->name,
            description: $line->description,
            quantity: (float) $line->quantity,
            unitName: $line->unit_name,
            unitNet: $line->unit_net,
            totalNet: $line->total_net,
            currency: $line->currency,
            serviceFrom: $period['from'] ?? $context['service_from'],
            serviceTo: $period['to'] ?? $context['service_to'],
            voucherText: $context['text'],
            voucherTextHint: $context['text_hint'],
            position: (int) $line->position,
            permalink: $context['permalink'],
            previewUrl: $context['preview_url'],
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
     * Positionen zu Spiegelzeilen: Belegwerte einmal je Beleg, Klassifikator und
     * Vorschau-Recht einmal je Aufruf.
     *
     * @param  Collection<int, LexofficeVoucherLine>  $rows
     * @return Collection<int, MirrorLine>
     */
    private function map(Organization $organization, Collection $rows, LexofficeContactMap $map): Collection {
        if ($rows->isEmpty()) {
            return collect();
        }
        $names = $this->customerNames($organization, $rows, $map);
        $canPreview = self::canPreview();
        $classifier = app(LicenseArticleClassifier::class);
        /** @var array<int, VoucherContext> $contexts */
        $contexts = [];
        $lines = [];
        foreach ($rows as $line) {
            $voucher = $line->voucher;
            $contexts[(int) $voucher->id] ??= self::voucherContext($voucher, $map, $names, $canPreview);
            $lines[] = $this->lineFrom($line, $contexts[(int) $voucher->id], $classifier);
        }

        return collect($lines);
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
            $id = self::customerIdOf($line->voucher, $map);
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

    private static function customerIdOf(LexofficeVoucher $voucher, ?LexofficeContactMap $map): ?int {
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
        return Gate::allows(Permission::VoucherViewAny->value) && Route::has('lexoffice.vouchers.preview');
    }

    private static function previewUrl(int $voucherId): string {
        return route('lexoffice.vouchers.preview', Sqid::encode(LexofficeVoucher::class, $voucherId));
    }

    public function bookedNet(Organization $organization, string $voucherNumber): ?Money {
        $voucher = LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('voucher_number', $voucherNumber)
            ->where('voucher_status', '!=', 'voided')
            ->orderByDesc('voucher_date')
            ->first();

        return $voucher?->net_amount;
    }
}
