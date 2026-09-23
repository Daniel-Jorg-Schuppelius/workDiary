<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubFeeExemptionKind, ClubFeeProration, ClubFeeTariffKind};
use App\Enums\Finance\RecurringInterval;
use App\Models\Club\{ClubFeeAccount, ClubFeeAssignment, ClubFeeExemption, ClubFeeSurcharge, ClubFeeTariff, ClubFeeTariffRate, ClubMember};
use App\Models\{Customer, Organization, User};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Beitragstarife, Sätze, Zuschläge, Beitragskonten, Zuordnungen und
 * Befreiungen (Feature 159, MVP-849) — einzige Schreibstelle. Kein Tarifsatz
 * im Code; Änderungen an Sätzen verändern keine freigegebenen Forderungen
 * (die entstehen erst in MVP-850 aus einem Schnappschuss).
 */
class ClubFeeService {
    public function defaultCurrency(): CurrencyCode {
        return CurrencyCode::tryFrom(strtoupper((string) config('invoicing.default_currency', 'EUR'))) ?? CurrencyCode::Euro;
    }

    // ── Tarife und Sätze ─────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function createTariff(Organization $organization, array $attributes): ClubFeeTariff {
        $tariff = ClubFeeTariff::query()->create(['organization_id' => $organization->id] + $this->tariffAttributes($attributes));
        $tariff->audit('club.fee.tariffCreated');

        return $tariff;
    }

    /** @param array<string, mixed> $attributes */
    public function updateTariff(ClubFeeTariff $tariff, array $attributes): ClubFeeTariff {
        $tariff->update($this->tariffAttributes($attributes));
        $tariff->audit('club.fee.tariffUpdated');

        return $tariff->refresh();
    }

    public function deleteTariff(ClubFeeTariff $tariff): void {
        if ($tariff->assignments()->exists()) {
            throw ValidationException::withMessages(['name' => __('club.fees.error.tariff_in_use')]);
        }
        $tariff->audit('club.fee.tariffDeleted');
        $tariff->delete();
    }

    /**
     * Satz je Gültigkeitsdatum; ein Datum trägt genau einen Satz.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function saveRate(ClubFeeTariff $tariff, array $attributes, ?ClubFeeTariffRate $rate = null): ClubFeeTariffRate {
        $validFrom = $this->date($attributes['valid_from'] ?? null);
        if ($validFrom === null) {
            throw ValidationException::withMessages(['valid_from' => __('club.fees.error.valid_from_required')]);
        }
        $interval = RecurringInterval::tryFrom((string) ($attributes['interval'] ?? '')) ?? RecurringInterval::Monthly;
        $values = [
            'organization_id' => $tariff->organization_id,
            'club_fee_tariff_id' => $tariff->id,
            'valid_from' => $validFrom->toDateString(),
            'interval' => $interval->value,
            'amount' => $this->amount($attributes['amount'] ?? null, 'amount'),
            'currency' => $this->defaultCurrency()->value,
            'anchor_month' => max(1, min(12, (int) ($attributes['anchor_month'] ?? 1))),
            'due_days' => max(0, (int) ($attributes['due_days'] ?? 14)),
            'proration' => (ClubFeeProration::tryFrom((string) ($attributes['proration'] ?? '')) ?? ClubFeeProration::FullPeriod)->value,
            'admission_fee' => $this->nullableAmount($attributes['admission_fee'] ?? null),
        ];
        $duplicate = $tariff->rates()->whereBetween('valid_from', DateRange::days($validFrom, $validFrom))->when($rate !== null, fn(Builder $q) => $q->whereKeyNot($rate?->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['valid_from' => __('club.fees.error.rate_exists')]);
        }
        if ($rate !== null) {
            $rate->update($values);
            $rate->audit('club.fee.rateUpdated', ['valid_from' => $values['valid_from']]);

            return $rate->refresh();
        }
        $created = ClubFeeTariffRate::query()->create($values);
        $created->audit('club.fee.rateCreated', ['valid_from' => $values['valid_from'], 'amount' => $values['amount']]);

        return $created;
    }

    public function deleteRate(ClubFeeTariffRate $rate): void {
        $rate->audit('club.fee.rateDeleted', ['valid_from' => $rate->valid_from->toDateString()]);
        $rate->delete();
    }

    /** @param array<string, mixed> $attributes */
    public function saveSurcharge(Organization $organization, array $attributes, ?ClubFeeSurcharge $surcharge = null): ClubFeeSurcharge {
        $validFrom = $this->date($attributes['valid_from'] ?? null);
        if ($validFrom === null) {
            throw ValidationException::withMessages(['valid_from' => __('club.fees.error.valid_from_required')]);
        }
        $validTo = $this->date($attributes['valid_to'] ?? null);
        if ($validTo !== null && $validTo->lessThan($validFrom)) {
            throw ValidationException::withMessages(['valid_to' => __('club.error.before_joined')]);
        }
        $values = [
            'organization_id' => $organization->id,
            'club_department_id' => (int) $attributes['club_department_id'],
            'name' => trim((string) $attributes['name']),
            'interval' => (RecurringInterval::tryFrom((string) ($attributes['interval'] ?? '')) ?? RecurringInterval::Monthly)->value,
            'amount' => $this->amount($attributes['amount'] ?? null, 'amount'),
            'currency' => $this->defaultCurrency()->value,
            'anchor_month' => max(1, min(12, (int) ($attributes['anchor_month'] ?? 1))),
            'valid_from' => $validFrom->toDateString(),
            'valid_to' => $validTo?->toDateString(),
        ];
        if ($surcharge !== null) {
            $surcharge->update($values);
            $surcharge->audit('club.fee.surchargeUpdated');

            return $surcharge->refresh();
        }
        $created = ClubFeeSurcharge::query()->create($values);
        $created->audit('club.fee.surchargeCreated');

        return $created;
    }

    public function deleteSurcharge(ClubFeeSurcharge $surcharge): void {
        $surcharge->audit('club.fee.surchargeDeleted');
        $surcharge->delete();
    }

    // ── Beitragskonten ───────────────────────────────────────────────────

    /**
     * Beitragskonto auf einem bestehenden Kunden oder mit neu angelegtem
     * Debitor (Name/E-Mail) — nie automatisch anhand gleicher Daten.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createAccount(Organization $organization, array $attributes, ?User $actor = null): ClubFeeAccount {
        return DB::transaction(function () use ($organization, $attributes, $actor): ClubFeeAccount {
            $customerId = $this->nullableInt($attributes['customer_id'] ?? null);
            $name = trim((string) ($attributes['name'] ?? ''));
            $email = $this->nullableString($attributes['email'] ?? null);
            if ($customerId !== null) {
                $customer = Customer::query()->whereKey($customerId)->where('organization_id', $organization->id)->first();
                if ($customer === null) {
                    throw ValidationException::withMessages(['customer_id' => __('club.error.user_foreign')]);
                }
                if (ClubFeeAccount::query()->where('customer_id', $customer->id)->exists()) {
                    throw ValidationException::withMessages(['customer_id' => __('club.fees.error.customer_has_account')]);
                }
                $name = $name !== '' ? $name : (string) $customer->name;
                $email ??= $customer->email;
            } else {
                if ($name === '') {
                    throw ValidationException::withMessages(['name' => __('club.fees.error.name_required')]);
                }
                $customer = Customer::query()->create([
                    'organization_id' => $organization->id,
                    'name' => $name,
                    'email' => $email,
                    'billable' => true,
                    'created_by' => $actor?->id,
                ]);
            }
            $account = ClubFeeAccount::query()->create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'name' => $name,
                'email' => $email,
                'notes' => $this->nullableString($attributes['notes'] ?? null),
            ]);
            $account->audit('club.fee.accountCreated', ['customer_id' => $customer->id]);

            return $account;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateAccount(ClubFeeAccount $account, array $attributes): ClubFeeAccount {
        $account->update([
            'name' => trim((string) $attributes['name']),
            'email' => $this->nullableString($attributes['email'] ?? null),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ]);
        $account->audit('club.fee.accountUpdated');

        return $account->refresh();
    }

    // ── Zuordnungen ──────────────────────────────────────────────────────

    /**
     * Mitglied einem Konto und Tarif zuordnen; je Mitglied höchstens eine
     * Zuordnung zur selben Zeit. Kein gleichzeitiger Familien- und Einzelgrundbeitrag.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function assign(ClubFeeAccount $account, ClubMember $member, ClubFeeTariff $tariff, array $attributes, ?ClubFeeAssignment $existing = null): ClubFeeAssignment {
        return DB::transaction(function () use ($account, $member, $tariff, $attributes, $existing): ClubFeeAssignment {
            if ($member->organization_id !== $account->organization_id || $tariff->organization_id !== $account->organization_id) {
                throw ValidationException::withMessages(['club_member_id' => __('club.error.member_foreign')]);
            }
            $from = $this->date($attributes['valid_from'] ?? null) ?? CarbonImmutable::instance($member->joined_on);
            $to = $this->date($attributes['valid_to'] ?? null);
            if ($to !== null && $to->lessThan($from)) {
                throw ValidationException::withMessages(['valid_to' => __('club.error.before_joined')]);
            }
            $upper = $to !== null ? DateRange::dayAfter($to) : null;
            $overlap = ClubFeeAssignment::query()
                ->where('club_member_id', $member->id)
                ->when($existing !== null, fn(Builder $q) => $q->whereKeyNot($existing?->id))
                ->when($upper !== null, fn(Builder $q) => $q->where('valid_from', '<', $upper))
                ->where(fn(Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($from)))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['valid_from' => __('club.fees.error.assignment_overlap')]);
            }
            $discount = $this->nullableString($attributes['discount_percent'] ?? null);
            if ($discount !== null && $tariff->isFamily()) {
                throw ValidationException::withMessages(['discount_percent' => __('club.fees.error.discount_family')]);
            }
            $values = [
                'organization_id' => $account->organization_id,
                'club_fee_account_id' => $account->id,
                'club_member_id' => $member->id,
                'club_fee_tariff_id' => $tariff->id,
                'valid_from' => $from->toDateString(),
                'valid_to' => $to?->toDateString(),
                'discount_percent' => $discount,
                'discount_reason' => $discount !== null ? $this->nullableString($attributes['discount_reason'] ?? null) : null,
            ];
            if ($discount !== null && $values['discount_reason'] === null) {
                throw ValidationException::withMessages(['discount_reason' => __('club.fees.error.discount_reason_required')]);
            }
            if ($existing !== null) {
                $existing->update($values + ['review_required_at' => null, 'review_note' => null]);
                $existing->audit('club.fee.assignmentUpdated', ['tariff' => $tariff->name]);

                return $existing->refresh();
            }
            $assignment = ClubFeeAssignment::query()->create($values);
            $assignment->audit('club.fee.assignmentCreated', ['tariff' => $tariff->name, 'valid_from' => $values['valid_from']]);

            return $assignment;
        });
    }

    /** Zuordnung beenden (Austritt, Kontowechsel); künftige Perioden entfallen, bestehende Forderungen bleiben. */
    public function endAssignment(ClubFeeAssignment $assignment, CarbonInterface $on): ClubFeeAssignment {
        $day = CarbonImmutable::instance($on)->startOfDay();
        if ($day->lessThan($assignment->valid_from)) {
            throw ValidationException::withMessages(['valid_to' => __('club.error.before_joined')]);
        }
        $assignment->update(['valid_to' => $day->toDateString(), 'review_required_at' => null, 'review_note' => null]);
        $assignment->audit('club.fee.assignmentEnded', ['valid_to' => $day->toDateString()]);

        return $assignment->refresh();
    }

    /** Tarifwechsel zum Wirksamkeitsdatum: alte Zuordnung endet am Vortag, neue beginnt — gleiches Konto. */
    public function changeTariff(ClubFeeAssignment $assignment, ClubFeeTariff $tariff, CarbonInterface $effectiveOn, ?User $actor = null): ClubFeeAssignment {
        return DB::transaction(function () use ($assignment, $tariff, $effectiveOn, $actor): ClubFeeAssignment {
            $day = CarbonImmutable::instance($effectiveOn)->startOfDay();
            if ($day->lessThanOrEqualTo($assignment->valid_from)) {
                throw ValidationException::withMessages(['effective_on' => __('club.fees.error.effective_after_start')]);
            }
            $account = $assignment->account()->firstOrFail();
            $member = $assignment->member()->firstOrFail();
            $assignment->update(['valid_to' => $day->subDay()->toDateString(), 'review_required_at' => null, 'review_note' => null]);
            $assignment->audit('club.fee.assignmentEnded', ['valid_to' => $day->subDay()->toDateString(), 'reason' => 'tariff_change', 'actor_id' => $actor?->id]);

            return $this->assign($account, $member, $tariff, ['valid_from' => $day->toDateString()]);
        });
    }

    /**
     * Altersbedingte Wechselvorschläge (täglicher Scan): Tarif mit Altersgrenzen,
     * Mitglied passt am Stichtag nicht mehr → Markierung, kein automatischer Wechsel.
     */
    public function flagAgeMismatches(Organization $organization, ?CarbonInterface $today = null): int {
        $day = CarbonImmutable::instance($today ?? CarbonImmutable::today())->startOfDay();
        $flagged = 0;
        $assignments = ClubFeeAssignment::query()
            ->where('organization_id', $organization->id)
            ->overlapping($day, $day)
            ->whereHas('tariff', fn(Builder $q) => $q->whereNotNull('min_age')->orWhereNotNull('max_age'))
            ->with(['tariff', 'member'])
            ->get();
        foreach ($assignments as $assignment) {
            $member = $assignment->member;
            $tariff = $assignment->tariff;
            if ($member === null || $tariff === null || $member->hasLeftOn($day)) {
                continue;
            }
            $fits = $tariff->fitsAge($member->ageOn($day));
            if (! $fits && $assignment->review_required_at === null) {
                $assignment->update(['review_required_at' => now(), 'review_note' => (string) __('club.fees.label.review_age', ['age' => $member->ageOn($day) ?? '?'])]);
                $assignment->audit('club.fee.reviewRequired', ['reason' => 'age']);
                $flagged++;
            } elseif ($fits && $assignment->review_required_at !== null) {
                $assignment->update(['review_required_at' => null, 'review_note' => null]);
            }
        }

        return $flagged;
    }

    /** Vorgeschlagener Tarif: aktiver Tarif gleicher Art, dessen Altersgrenzen am Stichtag passen — nur bei eindeutigem Treffer. */
    public function suggestTariff(ClubFeeAssignment $assignment, CarbonInterface $on): ?ClubFeeTariff {
        $member = $assignment->member;
        $current = $assignment->tariff;
        if ($member === null || $current === null) {
            return null;
        }
        $age = $member->ageOn($on);
        $candidates = ClubFeeTariff::query()
            ->where('organization_id', $assignment->organization_id)
            ->where('is_active', true)
            ->where('kind', $current->kind->value)
            ->whereKeyNot($current->id)
            ->get()
            ->filter(fn(ClubFeeTariff $tariff): bool => $tariff->hasAgeCriteria() && $tariff->fitsAge($age));

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    // ── Befreiungen ──────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function saveExemption(ClubMember $member, array $attributes, User $actor, ?ClubFeeExemption $exemption = null): ClubFeeExemption {
        $from = $this->date($attributes['starts_on'] ?? null);
        if ($from === null) {
            throw ValidationException::withMessages(['starts_on' => __('club.fees.error.valid_from_required')]);
        }
        $to = $this->date($attributes['ends_on'] ?? null);
        if ($to !== null && $to->lessThan($from)) {
            throw ValidationException::withMessages(['ends_on' => __('club.error.before_joined')]);
        }
        $kind = ClubFeeExemptionKind::tryFrom((string) ($attributes['kind'] ?? '')) ?? ClubFeeExemptionKind::Exemption;
        $percent = $kind === ClubFeeExemptionKind::Reduction ? $this->nullableString($attributes['percent'] ?? null) : null;
        if ($kind === ClubFeeExemptionKind::Reduction && $percent === null) {
            throw ValidationException::withMessages(['percent' => __('club.fees.error.percent_required')]);
        }
        $reason = $this->nullableString($attributes['reason'] ?? null);
        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => __('club.fees.error.reason_required')]);
        }
        $values = [
            'organization_id' => $member->organization_id,
            'club_member_id' => $member->id,
            'kind' => $kind->value,
            'percent' => $percent,
            'starts_on' => $from->toDateString(),
            'ends_on' => $to?->toDateString(),
            'reason' => $reason,
            'created_by_user_id' => $actor->id,
        ];
        if ($exemption !== null) {
            $exemption->update($values);
            $exemption->audit('club.fee.exemptionUpdated', ['kind' => $kind->value]);

            return $exemption->refresh();
        }
        $created = ClubFeeExemption::query()->create($values);
        $created->audit('club.fee.exemptionCreated', ['kind' => $kind->value, 'reason' => $reason]);

        return $created;
    }

    public function deleteExemption(ClubFeeExemption $exemption): void {
        $exemption->audit('club.fee.exemptionDeleted');
        $exemption->delete();
    }

    /** Aktuelle Zuordnung eines Mitglieds am Stichtag. */
    public function currentAssignment(ClubMember $member, ?CarbonInterface $on = null): ?ClubFeeAssignment {
        $day = CarbonImmutable::instance($on ?? CarbonImmutable::today())->startOfDay();
        /** @var ClubFeeAssignment|null $assignment */
        $assignment = ClubFeeAssignment::query()->where('club_member_id', $member->id)->overlapping($day, $day)->with(['account', 'tariff'])->orderByDesc('valid_from')->first();

        return $assignment;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function tariffAttributes(array $attributes): array {
        $minAge = $this->nullableInt($attributes['min_age'] ?? null);
        $maxAge = $this->nullableInt($attributes['max_age'] ?? null);
        if ($minAge !== null && $maxAge !== null && $maxAge < $minAge) {
            throw ValidationException::withMessages(['max_age' => __('club.error.age_range')]);
        }

        return [
            'name' => trim((string) $attributes['name']),
            'kind' => (ClubFeeTariffKind::tryFrom((string) ($attributes['kind'] ?? '')) ?? ClubFeeTariffKind::Individual)->value,
            'description' => $this->nullableString($attributes['description'] ?? null),
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
        ];
    }

    private function amount(mixed $value, string $field): string {
        $amount = $this->nullableAmount($value);
        if ($amount === null) {
            throw ValidationException::withMessages([$field => __('club.fees.error.amount_required')]);
        }

        return $amount;
    }

    /** Betrag als Decimal-String (deutsches oder US-Format), nie negativ. */
    private function nullableAmount(mixed $value): ?string {
        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }
        $normalized = NumberHelper::normalizeDecimalStringOrNull($raw);
        if ($normalized === null || Decimal::of($normalized, 2)->isNegative()) {
            throw ValidationException::withMessages(['amount' => __('club.fees.error.amount_invalid')]);
        }

        return Decimal::of($normalized, 2)->getValue();
    }

    private function date(mixed $value): ?CarbonImmutable {
        $string = $this->nullableString($value);

        return $string === null ? null : CarbonImmutable::parse($string)->startOfDay();
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
