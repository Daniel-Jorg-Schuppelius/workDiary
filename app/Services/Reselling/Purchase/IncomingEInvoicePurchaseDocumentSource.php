<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoicePurchaseDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Purchase;

use App\Models\{Document, IncomingEInvoice, Organization};
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Eingangs-E-Rechnungen als Eingangsbelege (Feature 152, Review 2026-09-11,
 * Einkauf): `incoming_einvoices` der Organisation, zuteilbar erst nach
 * fachlicher Freigabe bzw. Zahlungsfreigabe — Empfang, Rückfrage und
 * Ablehnung sind keine Belege. Belegdatum ist das Rechnungsdatum, sonst der
 * Eingang; Belegnummer die Rechnungsnummer, sonst die Ersatznummer `ER-<id>`
 * (auch suchbar).
 */
final class IncomingEInvoicePurchaseDocumentSource implements PurchaseDocumentSource {
    public const KEY = 'incoming_einvoice';

    public const NUMBER_PREFIX = 'ER-';

    private const ACCEPTED = [IncomingEInvoice::STATUS_APPROVED, IncomingEInvoice::STATUS_PAYMENT_RELEASED];

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new IncomingEInvoice)->getMorphClass();
    }

    public function search(Organization $organization, ?string $query, ?CarbonImmutable $from, int $limit): Collection {
        $builder = $this->candidates($organization);
        if ($from !== null) {
            $builder->where(static fn(Builder $w) => $w->where('issue_date', '>=', DateRange::day($from))
                ->orWhere(static fn(Builder $n) => $n->whereNull('issue_date')->where('received_at', '>=', DateRange::dayStart($from))));
        }
        $term = trim((string) $query);
        if ($term !== '') {
            $fallbackId = PurchaseDocument::idFromFallbackNumber(self::NUMBER_PREFIX, $term);
            $builder->where(static function (Builder $w) use ($term, $fallbackId): void {
                $w->whereLikeEscaped('invoice_number', $term)->orWhereLikeEscaped('seller_name', $term);
                if ($fallbackId !== null) {
                    $w->orWhere('id', $fallbackId);
                }
            });
        }

        return $this->map($builder->orderByDesc('issue_date')->orderByDesc('id')->limit($limit)->get());
    }

    public function byKey(Organization $organization, string $key): ?PurchaseDocument {
        $id = Sqid::decode(IncomingEInvoice::class, $key);
        if ($id === null) {
            return null;
        }
        $invoice = $this->candidates($organization)->whereKey($id)->first();

        return $invoice === null ? null : $this->toDocument($invoice);
    }

    public function byMorph(Organization $organization, int $id): ?PurchaseDocument {
        return $this->byMorphIds($organization, [$id])->get($id);
    }

    public function byMorphIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }

        return $this->map($this->query($organization)->whereIn('id', $ids)->get())
            ->keyBy(static fn(PurchaseDocument $document): int => $document->morphId);
    }

    public function toDocument(IncomingEInvoice $invoice, ?bool $canOpen = null): PurchaseDocument {
        $currency = $invoice->currency ?? CurrencyCode::Euro;
        $number = trim((string) $invoice->invoice_number);

        return new PurchaseDocument(
            sourceKey: self::KEY,
            morphClass: $invoice->getMorphClass(),
            morphId: (int) $invoice->id,
            key: Sqid::encode(IncomingEInvoice::class, $invoice->id),
            number: $number !== '' ? $number : PurchaseDocument::fallbackNumber(self::NUMBER_PREFIX, (int) $invoice->id),
            date: self::date($invoice->issue_date) ?? self::date($invoice->received_at),
            vendorName: $invoice->seller_name,
            net: $invoice->amount_net ?? Money::zero($currency),
            currency: $currency,
            description: null,
            permalink: ($canOpen ?? Route::has('finance.incoming-invoices.show')) ? route('finance.incoming-invoices.show', Sqid::encode(Document::class, $invoice->document_id)) : null,
            previewUrl: null,
        );
    }

    /** @return Builder<IncomingEInvoice> */
    private function query(Organization $organization): Builder {
        return IncomingEInvoice::query()->withoutGlobalScopes()->where('organization_id', $organization->id);
    }

    /** @return Builder<IncomingEInvoice> */
    private function candidates(Organization $organization): Builder {
        return $this->query($organization)->whereIn('status', self::ACCEPTED);
    }

    /**
     * @param  Collection<int, IncomingEInvoice>  $rows
     * @return Collection<int, PurchaseDocument>
     */
    private function map(Collection $rows): Collection {
        $canOpen = Route::has('finance.incoming-invoices.show');

        return $rows->map(fn(IncomingEInvoice $invoice): PurchaseDocument => $this->toDocument($invoice, $canOpen))->values();
    }

    private static function date(mixed $value): ?CarbonImmutable {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }
}
