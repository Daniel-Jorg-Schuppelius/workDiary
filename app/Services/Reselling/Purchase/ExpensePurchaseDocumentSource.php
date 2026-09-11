<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpensePurchaseDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Purchase;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, Organization};
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Gate, Route};

/**
 * Lokale Ausgaben als Eingangsbelege (Feature 152, Review 2026-09-11, Einkauf):
 * `expenses` der Organisation mit Nettobetrag — Entwürfe, abgelehnte und
 * stornierte sind keine Belege. Belegnummer ist die Erstattungsreferenz,
 * sonst gilt die Beschreibung als Kennung.
 */
final class ExpensePurchaseDocumentSource implements PurchaseDocumentSource {
    public const KEY = 'expense';

    private const EXCLUDED = [ExpenseStatus::Draft, ExpenseStatus::Rejected, ExpenseStatus::Cancelled];

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new Expense)->getMorphClass();
    }

    public function search(Organization $organization, ?string $query, ?CarbonImmutable $from, int $limit): Collection {
        $builder = $this->candidates($organization);
        if ($from !== null) {
            $builder->where('date', '>=', DateRange::day($from));
        }
        $term = trim((string) $query);
        if ($term !== '') {
            $builder->where(static fn(Builder $w) => $w->whereLikeEscaped('vendor', $term)->orWhereLikeEscaped('description', $term)->orWhereLikeEscaped('reimbursement_reference', $term));
        }

        return $this->map($builder->orderByDesc('date')->orderByDesc('id')->limit($limit)->get());
    }

    public function byKey(Organization $organization, string $key): ?PurchaseDocument {
        $id = Sqid::decode(Expense::class, $key);
        if ($id === null) {
            return null;
        }
        $expense = $this->candidates($organization)->whereKey($id)->first();

        return $expense === null ? null : $this->toDocument($expense);
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

    public function toDocument(Expense $expense, ?bool $canOpen = null): PurchaseDocument {
        $currency = $expense->currency;
        $reference = trim((string) $expense->reimbursement_reference);

        return new PurchaseDocument(
            sourceKey: self::KEY,
            morphClass: $expense->getMorphClass(),
            morphId: (int) $expense->id,
            key: Sqid::encode(Expense::class, $expense->id),
            number: $reference !== '' ? $reference : null,
            date: self::date($expense->date),
            vendorName: $expense->vendor,
            net: $expense->amount_net ?? Money::zero($currency),
            currency: $currency,
            description: $expense->description,
            permalink: ($canOpen ?? Route::has('expenses.edit')) && Gate::allows('view', $expense) ? route('expenses.edit', $expense) : null,
            previewUrl: null,
        );
    }

    /** @return Builder<Expense> */
    private function query(Organization $organization): Builder {
        return Expense::query()->withoutGlobalScopes()->where('organization_id', $organization->id);
    }

    /** @return Builder<Expense> */
    private function candidates(Organization $organization): Builder {
        return $this->query($organization)
            ->whereNotIn('status', array_map(static fn(ExpenseStatus $status): string => $status->value, self::EXCLUDED))
            ->whereNotNull('amount_net');
    }

    /**
     * @param  Collection<int, Expense>  $rows
     * @return Collection<int, PurchaseDocument>
     */
    private function map(Collection $rows): Collection {
        $canOpen = Route::has('expenses.edit');

        return $rows->map(fn(Expense $expense): PurchaseDocument => $this->toDocument($expense, $canOpen))->values();
    }

    private static function date(mixed $value): ?CarbonImmutable {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }
}
