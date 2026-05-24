<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeocodeCustomersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Routing\{GeocodingException, NominatimGeocoder};
use Illuminate\Console\Command;

/**
 * Idempotent backfill: geocode every customer with an address but
 * missing coordinates. Rate-limiting is enforced by the geocoder
 * itself, so this command can be re-run safely after failures.
 */
class GeocodeCustomersCommand extends Command {
    protected $signature = 'geocode:customers
        {--force : also re-geocode customers that already have coordinates}
        {--limit=0 : process at most N customers (0 = unlimited)}';

    protected $description = 'Backfill latitude/longitude for customer addresses via Nominatim.';

    public function handle(NominatimGeocoder $geocoder): int {
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $query = Customer::query()->whereNotNull('address_street');
        if (! $force) {
            $query->where(function ($q): void {
                $q->whereNull('address_lat')->orWhereNull('address_lng');
            });
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $processed = 0;
        $resolved = 0;
        $missing = 0;
        $errors = 0;

        $query->orderBy('id')->each(function (Customer $customer) use ($geocoder, &$processed, &$resolved, &$missing, &$errors): void {
            $processed++;
            $address = $this->buildAddress($customer);
            if ($address === '') {
                $missing++;

                return;
            }

            try {
                $result = $geocoder->geocode($address);
            } catch (GeocodingException $e) {
                $errors++;
                $this->warn(sprintf('[#%d] %s — %s', $customer->id, $customer->name, $e->getMessage()));

                return;
            }

            if ($result === null) {
                $missing++;
                $this->line(sprintf('[#%d] %s — no match', $customer->id, $customer->name));

                return;
            }

            $customer->forceFill([
                'address_lat' => $result->lat,
                'address_lng' => $result->lng,
            ])->saveQuietly();
            $resolved++;
            $this->line(sprintf('[#%d] %s → %.6f, %.6f', $customer->id, $customer->name, $result->lat, $result->lng));
        });

        $this->info(sprintf(
            'Processed: %d • Resolved: %d • Missing: %d • Errors: %d',
            $processed,
            $resolved,
            $missing,
            $errors,
        ));

        return self::SUCCESS;
    }

    private function buildAddress(Customer $customer): string {
        $parts = array_filter([
            $customer->address_street,
            trim(($customer->address_zip ?? '') . ' ' . ($customer->address_city ?? '')),
            $customer->country,
        ], static fn(?string $value): bool => $value !== null && trim($value) !== '');

        return trim(implode(', ', $parts));
    }
}
