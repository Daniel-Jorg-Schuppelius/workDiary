<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Expense\PaymentMethod;
use App\Enums\Expense\PerDiemTripStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PerDiemDay;
use App\Models\PerDiemTrip;
use App\Models\TravelLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Persistenz und Lifecycle einer Per-Diem-Reise:
 *
 *  - create/update legen den Trip an und (re-)generieren die zugehörigen Tage.
 *  - Mahlzeitenflags werden tagesweise gepflegt (siehe updateDay()).
 *  - convertToExpense legt eine Expense mit Status Pending + Kategorie "Verpflegungsmehraufwand" an.
 */
class PerDiemTripService {
    public function __construct(
        private readonly PerDiemCalculator $calculator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PerDiemTrip {
        return DB::transaction(function () use ($attributes): PerDiemTrip {
            $attributes['status'] = PerDiemTripStatus::Draft->value;
            $trip = PerDiemTrip::create($attributes);
            $this->rebuildDays($trip);

            return $trip->refresh()->load('days');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PerDiemTrip $trip, array $attributes): PerDiemTrip {
        return DB::transaction(function () use ($trip, $attributes): PerDiemTrip {
            unset($attributes['status'], $attributes['expense_id']);
            $datesChanged = isset($attributes['started_at']) || isset($attributes['ended_at'])
                || isset($attributes['country']);
            $trip->fill($attributes);
            $trip->save();
            if ($datesChanged) {
                $this->rebuildDays($trip);
            }

            return $trip->refresh()->load('days');
        });
    }

    public function delete(PerDiemTrip $trip): void {
        DB::transaction(function () use ($trip): void {
            $trip->delete();
        });
    }

    /**
     * Aktualisiert die Mahlzeitenflags eines Tages und rechnet ihn neu.
     *
     * @param  array{meal_breakfast?: bool, meal_lunch?: bool, meal_dinner?: bool, notes?: string|null}  $attributes
     */
    public function updateDay(PerDiemDay $day, array $attributes): PerDiemDay {
        return DB::transaction(function () use ($day, $attributes): PerDiemDay {
            foreach (['meal_breakfast', 'meal_lunch', 'meal_dinner'] as $flag) {
                if (array_key_exists($flag, $attributes)) {
                    $day->{$flag} = (bool) $attributes[$flag];
                }
            }
            if (array_key_exists('notes', $attributes)) {
                $day->notes = $attributes['notes'];
            }
            $this->calculator->recalculateDay($day);
            $day->save();

            return $day->refresh();
        });
    }

    /** Verwirft und erzeugt alle Tage des Trips neu (z. B. nach Zeitraumänderung). */
    public function rebuildDays(PerDiemTrip $trip): void {
        DB::transaction(function () use ($trip): void {
            $trip->days()->delete();
            $days = $this->calculator->buildDays($trip);
            foreach ($days as $day) {
                $day->per_diem_trip_id = $trip->id;
                $day->save();
            }
        });
    }

    /**
     * Erzeugt eine Expense aus dem Trip (Status Pending) und verlinkt sie.
     * Idempotent: liefert bestehende Expense, falls bereits konvertiert.
     */
    public function convertToExpense(PerDiemTrip $trip): Expense {
        return DB::transaction(function () use ($trip): Expense {
            if ($trip->expense_id !== null && $trip->expense) {
                return $trip->expense;
            }
            $trip->loadMissing('days');
            if ($trip->days->isEmpty()) {
                throw new RuntimeException('Reise hat keine berechneten Tage.');
            }

            $category = ExpenseCategory::query()
                ->where('organization_id', $trip->organization_id)
                ->where('slug', ExpenseCategory::SLUG_MEALS)
                ->first();

            $start = CarbonImmutable::parse($trip->started_at)->toDateString();
            $total = (float) $trip->totalAmount();

            $expense = new Expense([
                'organization_id' => $trip->organization_id,
                'user_id' => $trip->user_id,
                'expense_category_id' => $category?->id,
                'project_id' => $trip->project_id,
                'customer_id' => $trip->customer_id,
                'date' => $start,
                'description' => sprintf('Verpflegungsmehraufwand %s (%s)', $trip->purpose, $trip->location),
                'payment_method' => PaymentMethod::PrivatePaid->value,
                'currency' => optional($trip->days->first())->currency ?? 'EUR',
                'amount_net' => number_format($total, 2, '.', ''),
                'tax_rate' => '0.00',
                'amount_gross' => number_format($total, 2, '.', ''),
                'billable' => false,
                'status' => ExpenseStatus::Pending->value,
            ]);
            $expense->save();

            $trip->expense_id = $expense->id;
            $trip->status = PerDiemTripStatus::Converted;
            $trip->save();

            return $expense->refresh();
        });
    }

    public function cancel(PerDiemTrip $trip): PerDiemTrip {
        return DB::transaction(function () use ($trip): PerDiemTrip {
            $trip->status = PerDiemTripStatus::Cancelled;
            $trip->save();

            return $trip->refresh();
        });
    }

    /**
     * Erzeugt einen Per-Diem-Trip aus einer Fahrt (Vorbefüllung).
     */
    public function createFromTravelLog(TravelLog $log, ?string $purposeOverride = null): PerDiemTrip {
        $date = $log->date ? CarbonImmutable::parse($log->date) : CarbonImmutable::now();
        $started = $log->started_at
            ? CarbonImmutable::parse($log->started_at)
            : $date->startOfDay();
        $ended = $log->ended_at ?? null;
        $ended = $ended ? CarbonImmutable::parse($ended) : $date->endOfDay();

        return $this->create([
            'organization_id' => $log->organization_id ?? null,
            'user_id' => $log->user_id,
            'project_id' => $log->project_id,
            'customer_id' => $log->customer_id,
            'travel_log_id' => $log->id,
            'country' => 'DE',
            'purpose' => $purposeOverride ?? ($log->purpose ?? __('Dienstreise')),
            'location' => $log->to_address ?? $log->from_address ?? '',
            'started_at' => $started,
            'ended_at' => $ended,
            'accommodation_provided' => false,
            'notes' => null,
            'created_by' => $log->user_id,
            'updated_by' => $log->user_id,
        ]);
    }
}
