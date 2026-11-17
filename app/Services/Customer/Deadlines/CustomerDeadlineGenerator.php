<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines;

use App\Enums\Customer\CustomerDeadlineStatus;
use App\Models\Customer\{Customer, CustomerDeadline, CustomerDeadlineProfile};
use App\Models\Platform\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Customer\Deadlines\Dto\DeadlineSpec;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Jahreslauf der Kundenfristen (Feature 197, MVP-1632): erzeugt aus den
 * Fassungen der Fristmerkmale und dem Leistungsumfang die Fristen der Jahre
 * und hält sie aktuell — idempotent.
 *
 * - Neu: fehlende Fristen entstehen offen (Journal `created`).
 * - Offen und nicht von Hand gesetzt: Termin wird neu gerechnet (`recalculated`).
 * - Erledigt, verlängert, entfallen oder vorangefordert: bleibt, wie es ist.
 * - Nicht mehr erwartet (Merkmal geändert, Leistung beendet): eine unberührte
 *   offene Frist (ohne Notiz, Auftrag, Handtermin) wird entfernt, alles andere bleibt.
 *
 * Welche Fassung eine Frist trägt: die am letzten Tag der Periode gültige. Endet
 * die Periode vor der ersten Fassung, ist der Termin aber erst danach fällig,
 * gehört sie zur ersten Fassung — was bei Übernahme eines Kunden noch offen
 * ist, gehört dazu; was davor fällig war, nicht.
 */
final class CustomerDeadlineGenerator {
    public function __construct(private readonly CustomerDeadlineRuleSets $ruleSets) {}

    /**
     * Jahre des Jahreslaufs: Vorjahr (Jahreserklärungen werden erst im
     * übernächsten Jahr fällig), laufendes und Folgejahr.
     *
     * @return list<int>
     */
    public static function yearsAround(CarbonImmutable $today): array {
        return [$today->year - 1, $today->year, $today->year + 1];
    }

    /**
     * Alle Kunden mit Fristmerkmalen eines eingeschalteten Regelsatzes.
     *
     * @return array{created: int, updated: int, removed: int}
     */
    public function syncOrganization(Organization $organization, ?CarbonImmutable $today = null): array {
        $totals = ['created' => 0, 'updated' => 0, 'removed' => 0];
        $years = self::yearsAround($today ?? CarbonImmutable::today());

        OrganizationContext::run($organization, function () use ($organization, $years, &$totals): void {
            foreach ($this->ruleSets->enabled($organization) as $ruleSet) {
                $customerIds = CustomerDeadlineProfile::query()->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organization->id)
                    ->where('rule_set', $ruleSet->key())
                    ->distinct()
                    ->pluck('customer_id');
                foreach (Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->whereIn('id', $customerIds)->orderBy('id')->get() as $customer) {
                    foreach ($this->syncCustomer($customer, $ruleSet, $years) as $key => $count) {
                        $totals[$key] += $count;
                    }
                }
            }
        });

        return $totals;
    }

    /**
     * Fristen eines Kunden für einen Regelsatz und die genannten Jahre.
     *
     * @param  list<int>|null  $years  null = Jahre um heute
     * @return array{created: int, updated: int, removed: int}
     */
    public function syncCustomer(Customer $customer, CustomerDeadlineRuleSet $ruleSet, ?array $years = null): array {
        $years ??= self::yearsAround(CarbonImmutable::today());
        $versions = CustomerDeadlineProfile::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('rule_set', $ruleSet->key())
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get();

        $expected = $this->expected($customer, $ruleSet, $versions, $years);

        return DB::transaction(function () use ($customer, $ruleSet, $years, $expected): array {
            $result = ['created' => 0, 'updated' => 0, 'removed' => 0];
            $existing = CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id)
                ->where('rule_set', $ruleSet->key())
                ->get()
                ->filter(static fn (CustomerDeadline $deadline): bool => in_array($deadline->period_key->year, $years, true))
                ->keyBy(static fn (CustomerDeadline $deadline): string => $deadline->kind . '|' . $deadline->period_key->key());

            foreach ($expected as $key => [$spec, $version]) {
                $deadline = $existing->get($key);
                if ($deadline === null) {
                    if ($this->create($customer, $ruleSet, $spec, $version)) {
                        $result['created']++;
                    }

                    continue;
                }
                if ($this->refresh($deadline, $spec, $version)) {
                    $result['updated']++;
                }
            }

            foreach ($existing as $key => $deadline) {
                if (! isset($expected[$key]) && $this->isUntouched($deadline)) {
                    $deadline->delete();
                    $result['removed']++;
                }
            }

            return $result;
        });
    }

    /**
     * @param  Collection<int, CustomerDeadlineProfile>  $versions
     * @param  list<int>  $years
     * @return array<string, array{0: DeadlineSpec, 1: CustomerDeadlineProfile}>
     */
    private function expected(Customer $customer, CustomerDeadlineRuleSet $ruleSet, Collection $versions, array $years): array {
        $expected = [];
        if ($versions->isEmpty()) {
            return $expected;
        }
        foreach ($years as $year) {
            foreach ($versions as $version) {
                foreach ($ruleSet->deadlines($customer, $version->traitValues(), $year) as $spec) {
                    $owner = $this->ownerOf($versions, $spec);
                    if ($owner !== null && (int) $owner->id === (int) $version->id) {
                        $expected[$spec->key()] = [$spec, $version];
                    }
                }
            }
        }

        return $expected;
    }

    /** @param  Collection<int, CustomerDeadlineProfile>  $versions */
    private function ownerOf(Collection $versions, DeadlineSpec $spec): ?CustomerDeadlineProfile {
        $end = $spec->period->end();
        foreach ($versions as $version) {
            if ($version->isValidOn($end)) {
                return $version;
            }
        }
        $first = $versions->first();
        if ($first instanceof CustomerDeadlineProfile
            && $end->toDateString() < $first->valid_from->toDateString()
            && $spec->dueOn->toDateString() >= $first->valid_from->toDateString()) {
            return $first;
        }

        return null;
    }

    private function create(Customer $customer, CustomerDeadlineRuleSet $ruleSet, DeadlineSpec $spec, CustomerDeadlineProfile $version): bool {
        try {
            $deadline = DB::transaction(static fn (): CustomerDeadline => CustomerDeadline::query()->create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'customer_deadline_profile_id' => $version->id,
                'rule_set' => $ruleSet->key(),
                'kind' => $spec->kind,
                'period_key' => $spec->period,
                'due_on' => $spec->dueOn->toDateString(),
                'original_due_on' => $spec->dueOn->toDateString(),
                'status' => CustomerDeadlineStatus::Open,
                'responsible_user_id' => $version->responsible_user_id,
                'is_region_assumed' => $spec->regionAssumed,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Paralleler Lauf hat dieselbe Frist angelegt.
            return false;
        }
        $deadline->record('deadline_created', ['due_on' => $spec->dueOn->toDateString()]);

        return true;
    }

    /** Offene Frist an die neue Rechnung angleichen; erledigte und entfallene bleiben. */
    private function refresh(CustomerDeadline $deadline, DeadlineSpec $spec, CustomerDeadlineProfile $version): bool {
        if (! $deadline->status->isOpen()) {
            return false;
        }
        $changes = [];
        if ((int) $deadline->customer_deadline_profile_id !== (int) $version->id) {
            $changes['customer_deadline_profile_id'] = $version->id;
        }
        if ($deadline->responsible_user_id !== $version->responsible_user_id) {
            $changes['responsible_user_id'] = $version->responsible_user_id;
        }
        if ($deadline->is_region_assumed !== $spec->regionAssumed) {
            $changes['is_region_assumed'] = $spec->regionAssumed;
        }
        $calculated = $spec->dueOn->toDateString();
        $recalculated = false;
        if ($deadline->original_due_on->toDateString() !== $calculated) {
            $changes['original_due_on'] = $calculated;
            // Verlängert oder von Hand gesetzt: der geltende Termin bleibt.
            if ($deadline->status === CustomerDeadlineStatus::Open && ! $deadline->is_due_fixed) {
                $changes['due_on'] = $calculated;
                $changes['warned_at'] = null;
                $recalculated = true;
            }
        }
        if ($changes === []) {
            return false;
        }
        $from = $deadline->due_on->toDateString();
        $deadline->update($changes);
        if ($recalculated) {
            $deadline->record('deadline_recalculated', ['from' => $from, 'to' => $calculated]);
        }

        return true;
    }

    /** Vom Jahreslauf angelegt und nie angefasst — darf beim Wegfall verschwinden. */
    private function isUntouched(CustomerDeadline $deadline): bool {
        return $deadline->status === CustomerDeadlineStatus::Open
            && ! $deadline->is_due_fixed
            && $deadline->note === null
            && $deadline->diary_entry_id === null;
    }
}
