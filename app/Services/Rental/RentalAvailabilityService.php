<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalAvailabilityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental;

use App\Exceptions\{AssetNotUsableException, RentalConflictException};
use App\Models\Asset;
use App\Models\Rental\{RentalProfile, RentalReservation};
use App\Services\Asset\AssetUsageGuard;
use Illuminate\Support\Carbon;

/**
 * Verfügbarkeits- und Konfliktprüfung (MVP-260): Reservierungen inklusive
 * Puffer-, Wartungs-, Reinigungs- und Transportfenstern blockieren den
 * Zeitraum. Gesperrte, blockierend defekte oder prüfüberfällige Geräte
 * (gemeinsames Sperrmodell, D12) werden sichtbar verhindert.
 */
class RentalAvailabilityService {
    public const USAGE_CONTEXT = 'rental';

    public function __construct(private readonly AssetUsageGuard $guard) {}

    /**
     * Erste kollidierende Belegung im Zeitraum (inkl. beidseitiger Puffer).
     */
    public function findConflict(Asset $asset, Carbon $from, Carbon $to, ?int $ignoreCaseId = null): ?RentalReservation {
        $candidates = RentalReservation::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->overlapping($from, $to)
            ->when($ignoreCaseId !== null, fn ($q) => $q->where(function ($inner) use ($ignoreCaseId): void {
                $inner->whereNull('rental_case_id')->orWhere('rental_case_id', '!=', $ignoreCaseId);
            }))
            ->orderBy('starts_at')
            ->get();

        return $candidates->first(
            fn (RentalReservation $reservation): bool => $reservation->kind->isBlocking()
                && $reservation->overlapsWindow($from, $to),
        );
    }

    /**
     * Weiche Vormerkungen im Zeitraum (Warnung statt Blockade).
     *
     * @return array<int, RentalReservation>
     */
    public function softConflicts(Asset $asset, Carbon $from, Carbon $to, ?int $ignoreCaseId = null): array {
        return RentalReservation::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->overlapping($from, $to)
            ->when($ignoreCaseId !== null, fn ($q) => $q->where(function ($inner) use ($ignoreCaseId): void {
                $inner->whereNull('rental_case_id')->orWhere('rental_case_id', '!=', $ignoreCaseId);
            }))
            ->get()
            ->filter(fn (RentalReservation $r): bool => ! $r->kind->isBlocking() && $r->overlapsWindow($from, $to))
            ->values()
            ->all();
    }

    /**
     * @throws RentalConflictException|AssetNotUsableException
     */
    public function assertAvailable(Asset $asset, Carbon $from, Carbon $to, ?int $ignoreCaseId = null): void {
        $profile = RentalProfile::query()->where('asset_id', $asset->id)->first();

        if ($profile === null || ! $profile->is_rentable) {
            throw new AssetNotUsableException((string) __(':asset ist nicht als leihfähig markiert.', ['asset' => $asset->name]));
        }

        // Gemeinsames Sperrmodell (D12): Sperren + blockierende Defekte
        $this->guard->ensureUsable($asset, self::USAGE_CONTEXT);

        // Prüfstatus: prüfpflichtige Leihobjekte mit überfälliger Prüfung
        // dürfen nicht still verliehen werden (MVP-259, Feature 075 folgt).
        if ($profile->requires_inspection
            && $asset->next_inspection_on !== null
            && $asset->next_inspection_on->isPast()) {
            throw new AssetNotUsableException((string) __(':asset hat eine überfällige Prüfung und kann nicht verliehen werden.', ['asset' => $asset->name]));
        }

        $conflict = $this->findConflict($asset, $from, $to, $ignoreCaseId);

        if ($conflict !== null) {
            throw new RentalConflictException(
                (string) __(':asset ist von :from bis :to bereits belegt (:kind).', [
                    'asset' => $asset->name,
                    'from' => $conflict->blockedFrom()->format('d.m.Y H:i'),
                    'to' => $conflict->blockedUntil()->format('d.m.Y H:i'),
                    'kind' => $conflict->kind->label(),
                ]),
                $conflict,
            );
        }
    }

    public function isAvailable(Asset $asset, Carbon $from, Carbon $to, ?int $ignoreCaseId = null): bool {
        try {
            $this->assertAvailable($asset, $from, $to, $ignoreCaseId);
        } catch (RentalConflictException|AssetNotUsableException) {
            return false;
        }

        return true;
    }
}
