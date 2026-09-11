<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficePurchaseDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\User\Permission;
use App\Models\{LexofficeVoucher, Organization};
use App\Services\Reselling\Purchase\{PurchaseDocument, PurchaseDocumentSource};
use App\Support\Billing\VoucherTypes;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Gate, Route};

/**
 * Lexoffice-Eingangsbelege als Quelle des Reselling-Registers (Feature 152,
 * Review 2026-09-11, Einkauf): Einkaufsbelege des Spiegels
 * ({@see VoucherTypes::EXPENSES}, nicht archiviert, weder Entwurf noch
 * storniert). Nettobetrag nur nach Detailabruf — sonst zählt die Belegsumme.
 * Der Formularschlüssel ist die Sqid des Belegs; bestehende Lexoffice-Sqids
 * bleiben gültig.
 */
final class LexofficePurchaseDocumentSource implements PurchaseDocumentSource {
    public const KEY = 'lexoffice';

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new LexofficeVoucher)->getMorphClass();
    }

    public function search(Organization $organization, ?string $query, ?CarbonImmutable $from, int $limit): Collection {
        $builder = $this->candidates($organization);
        if ($from !== null) {
            $builder->where('voucher_date', '>=', DateRange::day($from));
        }
        $term = trim((string) $query);
        if ($term !== '') {
            $builder->where(static fn(Builder $w) => $w->whereLikeEscaped('voucher_number', $term)
                ->orWhereLikeEscaped('recipient_name', $term)
                ->orWhereHas('supplier', static fn(Builder $s) => $s->whereLikeEscaped('name', $term)));
        }

        return $this->map($builder->orderByDesc('voucher_date')->orderByDesc('id')->limit($limit)->get());
    }

    public function byKey(Organization $organization, string $key): ?PurchaseDocument {
        $id = Sqid::decode(LexofficeVoucher::class, $key);
        if ($id === null) {
            return null;
        }
        $voucher = $this->candidates($organization)->whereKey($id)->first();

        return $voucher === null ? null : $this->toDocument($voucher);
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

    public function toDocument(LexofficeVoucher $voucher, ?bool $canPreview = null): PurchaseDocument {
        $currency = $voucher->currency;
        $recipient = trim((string) $voucher->recipient_name);

        return new PurchaseDocument(
            sourceKey: self::KEY,
            morphClass: $voucher->getMorphClass(),
            morphId: (int) $voucher->id,
            key: Sqid::encode(LexofficeVoucher::class, $voucher->id),
            number: $voucher->voucher_number,
            date: self::date($voucher->voucher_date),
            vendorName: $voucher->supplier->name ?? ($recipient !== '' ? $recipient : null),
            net: $voucher->net_amount ?? $voucher->total_amount ?? Money::zero($currency),
            currency: $currency,
            description: $voucher->voucherTextHint(),
            permalink: $voucher->lexofficePermalink(),
            previewUrl: ($canPreview ?? self::canPreview()) ? self::previewUrl((int) $voucher->id) : null,
        );
    }

    /** @return Builder<LexofficeVoucher> */
    private function query(Organization $organization): Builder {
        return LexofficeVoucher::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->with('supplier:id,name');
    }

    /** @return Builder<LexofficeVoucher> */
    private function candidates(Organization $organization): Builder {
        return $this->query($organization)
            ->whereIn('voucher_type', VoucherTypes::EXPENSES)
            ->where('archived', false)
            ->whereNotIn('voucher_status', VoucherTypes::IGNORED_STATUSES);
    }

    /**
     * @param  Collection<int, LexofficeVoucher>  $rows
     * @return Collection<int, PurchaseDocument>
     */
    private function map(Collection $rows): Collection {
        $canPreview = self::canPreview();

        return $rows->map(fn(LexofficeVoucher $voucher): PurchaseDocument => $this->toDocument($voucher, $canPreview))->values();
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
