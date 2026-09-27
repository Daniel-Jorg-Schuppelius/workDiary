<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalPriceRuleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental;

use App\Enums\Rental\RentalRateRuleKind;
use App\Models\Asset\Asset;
use App\Models\Rental\{RentalProfile, RentalReservation};
use App\Services\Calendar\HolidayService;
use Illuminate\Support\Carbon;

/**
 * Mietpreisregeln (MVP-950): Tage im Mietzeitraum je Regel, Auslastung der
 * Gerätegruppe und Tageszählung für Wochenend- und Feiertagszuschläge.
 */
final class RentalPriceRuleService {
    public function __construct(private readonly HolidayService $holidays) {}

    /** Auslastung der Gerätegruppe im Zeitraum in Prozent (belegte Geräte / mietbare Geräte). */
    public function utilization(int $organizationId, ?string $groupCode, Carbon $from, Carbon $to): int {
        $assetIds = RentalProfile::query()->where('organization_id', $organizationId)->where('is_rentable', true)
            ->when($groupCode !== null, fn ($q) => $q->where('group_code', $groupCode))->pluck('asset_id');
        if ($assetIds->isEmpty()) {
            return 0;
        }
        $busy = RentalReservation::query()->whereIn('asset_id', $assetIds)->active()->overlapping($from, $to)->distinct()->count('asset_id');

        return (int) round($busy / $assetIds->count() * 100);
    }

    /** @return list<Carbon> Kalendertage des Zeitraums (Beginn einschließlich, Ende ausschließlich; mindestens ein Tag) */
    public function days(Carbon $from, Carbon $until): array {
        $days = [];
        $day = $from->copy()->startOfDay();
        $count = max(1, (int) ceil($from->diffInHours($until) / 24));
        for ($i = 0; $i < $count; $i++) {
            $days[] = $day->copy()->addDays($i);
        }

        return $days;
    }

    public function weekendDays(Carbon $from, Carbon $until): int {
        return count(array_filter($this->days($from, $until), static fn (Carbon $d): bool => $d->isWeekend()));
    }

    public function holidayDays(Carbon $from, Carbon $until): int {
        return count(array_filter($this->days($from, $until), fn (Carbon $d): bool => $this->holidays->isHoliday($d)));
    }

    /**
     * Auf-/Abschläge der Regeln auf einen Tagessatz: je Regel die zutreffenden Tage.
     *
     * @param list<array<string, mixed>> $rules
     * @return list<array{label: string, days: int, percent: float}>
     */
    public function adjustments(array $rules, Carbon $from, Carbon $until, ?int $utilization): array {
        $days = $this->days($from, $until);
        $out = [];
        foreach ($rules as $rule) {
            $percent = (float) ($rule['adjust_percent'] ?? 0);
            if ($percent == 0.0) {
                continue;
            }
            $matching = match (RentalRateRuleKind::tryFrom((string) ($rule['kind'] ?? ''))) {
                RentalRateRuleKind::Season => count(array_filter($days, static fn (Carbon $d): bool => ($rule['valid_from'] === null || $d->toDateString() >= $rule['valid_from'])
                    && ($rule['valid_until'] === null || $d->toDateString() <= $rule['valid_until']))),
                RentalRateRuleKind::Weekday => count(array_filter($days, static fn (Carbon $d): bool => in_array($d->dayOfWeekIso, (array) ($rule['weekdays'] ?? []), true))),
                RentalRateRuleKind::Utilization => $utilization !== null && $utilization >= (int) ($rule['utilization_min_percent'] ?? 101) ? count($days) : 0,
                default => 0,
            };
            if ($matching > 0) {
                $out[] = ['label' => (string) ($rule['label'] ?? ''), 'days' => $matching, 'percent' => $percent];
            }
        }

        return $out;
    }

    public function groupCodeFor(Asset $asset): ?string {
        return $asset->rentalProfile?->group_code;
    }
}
