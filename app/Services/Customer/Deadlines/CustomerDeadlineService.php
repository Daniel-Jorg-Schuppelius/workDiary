<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines;

use App\Enums\Customer\CustomerDeadlineStatus;
use App\Models\Customer\CustomerDeadline;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Übergänge einer Kundenfrist (Feature 197, MVP-1632) — einzige Schreibstelle
 * für Status, Verlängerung, Vorabanforderung und Erledigung. Jeder Schritt
 * steht im Journal der Frist.
 *
 * Erledigt wird von Hand oder über einen abgeschlossenen Auftrag mit Periode
 * ({@see completeForOrder()}, Event `OrderCompleted`): Kunde und Periode
 * gleich, und der Regelsatz ordnet Eintragsart und Produktgruppe der Fristart
 * zu. Dieselbe Zuordnung liefert der Folgeperiode ihre Fälligkeit
 * ({@see dueDateFor()}, Feature 198, MVP-1637).
 */
final class CustomerDeadlineService {
    use AssertsValidatedTransition;

    public function __construct(private readonly CustomerDeadlineRuleSets $ruleSets) {}

    public function complete(CustomerDeadline $deadline, User $actor, ?string $note = null): CustomerDeadline {
        return $this->transition($deadline, CustomerDeadlineStatus::Completed, $actor, 'deadline_completed', [
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ], $note);
    }

    /** Verlängerung (Antrag bewilligt): neuer Termin mit Grund; erneut verlängern ist erlaubt. */
    public function extend(CustomerDeadline $deadline, CarbonImmutable $dueOn, string $reason, User $actor): CustomerDeadline {
        return DB::transaction(function () use ($deadline, $dueOn, $reason, $actor): CustomerDeadline {
            $deadline = $this->lockAndRefresh($deadline);
            if (! $deadline->status->isOpen()) {
                $this->assertValidatedTransition($deadline->status, CustomerDeadlineStatus::Extended, 'customer_deadline.error.transition');
            }
            $from = $deadline->due_on->toDateString();
            $deadline->update([
                'status' => CustomerDeadlineStatus::Extended,
                'due_on' => $dueOn->toDateString(),
                'extension_reason' => $reason,
                'warned_at' => null,
                'overdue_warned_at' => null,
            ]);
            $deadline->record('deadline_extended', ['from' => $from, 'to' => $dueOn->toDateString(), 'reason' => $reason], $actor);

            return $deadline->refresh();
        });
    }

    /**
     * Vorabanforderung (§ 149 Abs. 4 AO): das Finanzamt verlangt die Erklärung
     * früher. Der Termin ist von Hand gesetzt und bleibt beim Jahreslauf stehen.
     */
    public function requestAdvance(CustomerDeadline $deadline, CarbonImmutable $requestedOn, CarbonImmutable $dueOn, User $actor): CustomerDeadline {
        return DB::transaction(function () use ($deadline, $requestedOn, $dueOn, $actor): CustomerDeadline {
            $deadline = $this->lockAndRefresh($deadline);
            if (! $deadline->status->isOpen()) {
                throw ValidationException::withMessages(['status' => (string) __('customer_deadline.error.not_open')]);
            }
            $from = $deadline->due_on->toDateString();
            $deadline->update([
                'due_on' => $dueOn->toDateString(),
                'is_due_fixed' => true,
                'advance_requested_on' => $requestedOn->toDateString(),
                'warned_at' => null,
                'overdue_warned_at' => null,
            ]);
            $deadline->record('deadline_advance_requested', ['from' => $from, 'to' => $dueOn->toDateString(), 'requested_on' => $requestedOn->toDateString()], $actor);

            return $deadline->refresh();
        });
    }

    public function waive(CustomerDeadline $deadline, string $reason, User $actor): CustomerDeadline {
        return $this->transition($deadline, CustomerDeadlineStatus::Waived, $actor, 'deadline_waived', [], $reason);
    }

    /** Erledigtes oder Entfallenes wieder öffnen (Grund Pflicht); der geltende Termin bleibt. */
    public function reopen(CustomerDeadline $deadline, string $reason, User $actor): CustomerDeadline {
        return $this->transition($deadline, CustomerDeadlineStatus::Open, $actor, 'deadline_reopened', [
            'completed_at' => null,
            'completed_by' => null,
            'diary_entry_id' => null,
        ], $reason);
    }

    /**
     * Erledigung über einen abgeschlossenen Auftrag mit Periode: alle offenen
     * Fristen des Kunden in derselben Periode, deren Art der Regelsatz der
     * Eintragsart und Produktgruppe des Auftrags zuordnet.
     *
     * @return int Anzahl erledigter Fristen
     */
    public function completeForOrder(DiaryEntry $entry): int {
        $done = 0;
        foreach ($this->matching($entry, $entry->period_key, open: true) as $deadline) {
            $done += (int) DB::transaction(function () use ($deadline, $entry): bool {
                $deadline = $this->lockAndRefresh($deadline);
                if (! $deadline->status->isOpen()) {
                    return false;
                }
                $deadline->update([
                    'status' => CustomerDeadlineStatus::Completed,
                    'completed_at' => now(),
                    'completed_by' => $entry->assigned_user_id,
                    'diary_entry_id' => $entry->id,
                ]);
                $deadline->record('deadline_completed_by_order', ['diary_entry_id' => $entry->id, 'period_key' => $deadline->period_key->key()]);

                return true;
            });
        }

        return $done;
    }

    /**
     * Fälligkeit eines Auftrags der Periode aus den Kundenfristen gleicher Art
     * (Feature 198, MVP-1637): der früheste Termin der zugeordneten,
     * nicht entfallenen Fristen des Kunden; null ohne passende Frist.
     */
    public function dueDateFor(DiaryEntry $source, PeriodKey $period): ?CarbonImmutable {
        $earliest = null;
        foreach ($this->matching($source, $period, open: false) as $deadline) {
            $due = CarbonImmutable::instance($deadline->due_on)->startOfDay();
            if ($earliest === null || $due->lessThan($earliest)) {
                $earliest = $due;
            }
        }

        return $earliest;
    }

    /**
     * Fristen des Kunden in der Periode, deren Art der Regelsatz Eintragsart und
     * Produktgruppe des Auftrags zuordnet.
     *
     * @return list<CustomerDeadline>
     */
    private function matching(DiaryEntry $entry, ?PeriodKey $period, bool $open): array {
        if ($period === null || $entry->customer_id === null || $entry->entry_type_id === null) {
            return [];
        }
        $slug = $entry->entryType()->withoutGlobalScopes()->value('slug');
        if (! is_string($slug) || $slug === '') {
            return [];
        }
        $productGroup = $entry->productGroupCode();

        $deadlines = CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $entry->organization_id)
            ->where('customer_id', $entry->customer_id)
            ->where('period_key', $period->key())
            ->when($open, static fn ($q) => $q->open(), static fn ($q) => $q->where('status', '!=', CustomerDeadlineStatus::Waived->value))
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($deadlines as $deadline) {
            $ruleSet = $this->ruleSets->get((int) $entry->organization_id, $deadline->rule_set);
            if ($ruleSet !== null && $ruleSet->completes($deadline->kind, $slug, $productGroup)) {
                $out[] = $deadline;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $attributes */
    private function transition(CustomerDeadline $deadline, CustomerDeadlineStatus $target, User $actor, string $event, array $attributes, ?string $text): CustomerDeadline {
        return DB::transaction(function () use ($deadline, $target, $actor, $event, $attributes, $text): CustomerDeadline {
            $deadline = $this->lockAndRefresh($deadline);
            $this->assertValidatedTransition($deadline->status, $target, 'customer_deadline.error.transition');
            $from = $deadline->status;
            $deadline->update(['status' => $target, ...$attributes]);
            $deadline->record($event, array_filter(['from' => $from->value, 'to' => $target->value, 'text' => $text], static fn (mixed $value): bool => $value !== null), $actor);

            return $deadline->refresh();
        });
    }

    /** Frist unter Zeilensperre neu lesen — Prüfung und Schreiben sehen denselben Stand. */
    private function lockAndRefresh(CustomerDeadline $deadline): CustomerDeadline {
        return CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)->whereKey($deadline->getKey())->lockForUpdate()->firstOrFail();
    }
}
