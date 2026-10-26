<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalTenancyService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental;

use App\Enums\Rental\RentalTenancyStatus;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Rental\{RentalProfile, RentalTenancy};
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\{Arr, Carbon};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Mietverhältnisse der Dauervermietung (Branchenprofil Vermieter). Unter der
 * Sperre der Wohnung: ein bestätigtes Mietverhältnis darf keine Ferienbuchung,
 * keine Verleihakte und kein anderes Mietverhältnis überschneiden.
 */
class RentalTenancyService {
    use AssertsStatusTransition;

    /** Felder, die Leitung bzw. Formular setzen; Status und Zuordnung führt der Dienst. */
    private const FIELDS = ['starts_on', 'ends_on', 'rent_amount', 'ancillary_amount', 'deposit_amount', 'note'];

    public function __construct(private readonly RentalAvailabilityService $availability) {}

    /**
     * Bestätigtes Mietverhältnis bzw. mit `$requestOnly` eine Anfrage aus dem
     * Portal — ohne Beträge und ohne Belegung.
     *
     * @param array<string, mixed> $data
     */
    public function create(RentalProfile $profile, Customer $customer, User $actor, array $data, bool $requestOnly = false): RentalTenancy {
        abort_unless((int) $profile->organization_id === (int) $customer->organization_id
            && (int) $actor->organization_id === (int) $profile->organization_id, 404);
        if ($profile->rental_mode?->allowsLongTerm() !== true || ! $profile->is_rentable) {
            throw ValidationException::withMessages(['profile_id' => __('housing.unavailable')]);
        }

        return DB::transaction(function () use ($profile, $customer, $actor, $data, $requestOnly): RentalTenancy {
            Asset::query()->whereKey($profile->asset_id)->lockForUpdate()->firstOrFail();
            if (! $requestOnly) {
                $this->assertAvailable($profile, $data);
            }
            $tenancy = RentalTenancy::query()->create([
                'organization_id' => $profile->organization_id,
                'asset_id' => $profile->asset_id,
                'customer_id' => $customer->id,
                'status' => $requestOnly ? RentalTenancyStatus::Requested : RentalTenancyStatus::Active,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'rent_amount' => $requestOnly ? null : ($data['rent_amount'] ?? $profile->rent_amount),
                'ancillary_amount' => $requestOnly ? null : ($data['ancillary_amount'] ?? $profile->ancillary_amount),
                'deposit_amount' => $requestOnly ? null : ($data['deposit_amount'] ?? null),
                'currency' => $profile->currency,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);
            $tenancy->audit($requestOnly ? 'housing.requested' : 'housing.activated');

            return $tenancy;
        });
    }

    /**
     * Bestätigen, Ablehnen, Stornieren oder Ändern (Auszug: letzten Miettag
     * setzen). Abgeschlossene Mietverhältnisse bleiben unverändert.
     *
     * @param array<string, mixed> $data
     * @throws RuntimeException bei unzulässigem Statuswechsel
     */
    public function update(RentalTenancy $tenancy, RentalTenancyStatus $status, array $data): RentalTenancy {
        return DB::transaction(function () use ($tenancy, $status, $data): RentalTenancy {
            Asset::query()->whereKey($tenancy->asset_id)->lockForUpdate()->firstOrFail();
            $tenancy = RentalTenancy::query()->whereKey($tenancy->id)->lockForUpdate()->firstOrFail();
            if ($status !== $tenancy->status) {
                $this->assertStatusTransition($tenancy->status, $status);
            } elseif (! $tenancy->status->isOpen()) {
                throw new RuntimeException((string) __('housing.closed'));
            }
            $tenancy->fill(Arr::only($data, self::FIELDS));
            if ($status === RentalTenancyStatus::Active) {
                $profile = RentalProfile::query()->where('asset_id', $tenancy->asset_id)->firstOrFail();
                $this->assertAvailable($profile, ['starts_on' => $tenancy->starts_on->toDateString(), 'ends_on' => $tenancy->ends_on?->toDateString()], $tenancy->id);
            }
            $from = $tenancy->status;
            $tenancy->status = $status;
            $tenancy->save();
            $tenancy->audit('housing.updated', ['status' => $status->value, 'from' => $from->value]);

            return $tenancy;
        });
    }

    /**
     * Rücknahme einer eigenen, noch offenen Anfrage im Kundenportal.
     *
     * @throws RuntimeException wenn die Anfrage schon entschieden ist
     */
    public function withdraw(RentalTenancy $tenancy): RentalTenancy {
        return DB::transaction(function () use ($tenancy): RentalTenancy {
            $tenancy = RentalTenancy::query()->whereKey($tenancy->id)->lockForUpdate()->firstOrFail();
            if ($tenancy->status !== RentalTenancyStatus::Requested) {
                throw new RuntimeException((string) __('housing.not_withdrawable'));
            }
            $tenancy->status = RentalTenancyStatus::Cancelled;
            $tenancy->save();
            $tenancy->audit('housing.withdrawn');

            return $tenancy;
        });
    }

    /** @param array<string, mixed> $data */
    private function assertAvailable(RentalProfile $profile, array $data, ?int $ignoreTenancyId = null): void {
        if ($profile->rental_mode?->allowsLongTerm() !== true) {
            throw ValidationException::withMessages(['profile_id' => __('housing.unavailable')]);
        }
        $zone = Tz::ofOrganization($profile->organization ?? abort(404));
        $from = CarbonImmutable::parse((string) $data['starts_on'], $zone)->utc();
        // Ein offenes Mietverhältnis kollidiert mit JEDER späteren Buchung.
        $to = ! empty($data['ends_on'])
            ? CarbonImmutable::parse((string) $data['ends_on'], $zone)->addDay()->utc()
            : null;
        $asset = $profile->asset ?? abort(404);
        $this->availability->assertAvailable($asset, Carbon::instance($from), $to !== null ? Carbon::instance($to) : null, null, $ignoreTenancyId);
    }
}
