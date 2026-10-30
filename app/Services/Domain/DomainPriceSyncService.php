<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainPriceSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain;

use App\Enums\Reselling\{BillingFrequency, SubscriptionProvider};
use App\Models\Domain\DomainProviderConnection;
use App\Models\Reselling\ResalePriceEntry;
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Plugins\Support\Domain\DomainResponse;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Preisstaffeln des Domainanbieters in den Preiskatalog (Feature 185, Befund
 * 2026-10-30): die Benutzerklassen des Portals sind Preisstaffeln — das
 * Buchungsjournal zeigt nur den Einkauf des Inhabers, die Monatsrechnung an den
 * Subuser läuft zum Klassenpreis (Einkauf × FACTOR). Je Klasse (`QueryUserClassList`,
 * `StatusUserclass`) und Zone entsteht eine Katalogzeile mit Einkauf (`StatusUser`
 * des eigenen Kontos) und Staffelpreis; Preisänderungen schließen die alte Zeile
 * am Vortag und eröffnen eine neue, damit Perioden ihren historischen Preis
 * behalten. Zonen, die das Portal nicht mehr nennt, bleiben unberührt.
 */
final class DomainPriceSyncService {
    public const TERM_MONTHS = 12;

    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /**
     * @return array{classes: list<string>, zones: int, created: int, updated: int, unchanged: int}
     */
    public function sync(DomainProviderConnection $connection, ?CarbonImmutable $today = null): array {
        $today ??= CarbonImmutable::today();
        $adapter = $this->resolver->for($connection);
        $own = ProviderRelations::domainPrices(ProviderRelations::fromResponse(self::call($adapter, 'StatusUser', [])));
        $classes = [];
        foreach (ProviderRelations::values(self::call($adapter, 'QueryUserClassList', []), 'userclass') as $class) {
            $class = strtoupper(trim($class));
            if ($class !== '') {
                $classes[$class] = true;
            }
        }
        $classes = array_keys($classes);

        $rows = [];
        foreach ($classes as $class) {
            $prices = ProviderRelations::domainPrices(ProviderRelations::fromResponse(self::call($adapter, 'StatusUserclass', ['userclass' => $class])));
            foreach ($own as $zone => $kinds) {
                $purchase = self::decimal($kinds['ANNUAL'] ?? null);
                $list = self::decimal($prices[$zone]['ANNUAL'] ?? null);
                if ($purchase === null || $list === null) {
                    continue; // ohne Jahrespreis auf beiden Seiten keine Katalogzeile (Zonen mit nur Währung oder Faktor)
                }
                $currency = CurrencyCode::tryFrom(strtoupper(trim((string) ($prices[$zone]['CURRENCY'] ?? $kinds['CURRENCY'] ?? '')))) ?? CurrencyCode::Euro;
                $rows[] = ['class' => $class, 'product' => ResalePriceEntry::domainProduct($zone), 'purchase' => $purchase, 'list' => $list, 'currency' => $currency->value];
            }
        }

        return $this->upsert((int) $connection->organization_id, $rows, $today) + ['classes' => $classes, 'zones' => count($own)];
    }

    /**
     * @param  list<array{class: string, product: string, purchase: string, list: string, currency: string}>  $rows
     * @return array{created: int, updated: int, unchanged: int}
     */
    private function upsert(int $organizationId, array $rows, CarbonImmutable $today): array {
        $counters = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        /** @var array<string, ResalePriceEntry> $current */
        $current = [];
        foreach (ResalePriceEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->where('term_months', self::TERM_MONTHS)
            ->where('interval', BillingFrequency::Yearly->value)
            ->where('price_class', '!=', '')
            ->whereNull('valid_to')
            ->get() as $entry) {
            $current[$entry->price_class . '|' . $entry->product] = $entry;
        }
        $now = now();
        $inserts = [];
        DB::transaction(function () use ($organizationId, $rows, $today, $current, $now, &$inserts, &$counters): void {
            foreach ($rows as $row) {
                $currency = CurrencyCode::from($row['currency']);
                $existing = $current[$row['class'] . '|' . $row['product']] ?? null;
                $values = ['purchase_unit_price' => $row['purchase'], 'list_unit_price' => $row['list'], 'currency' => $row['currency']];
                if ($existing !== null) {
                    $same = $existing->currency === $currency
                        && $existing->purchase_unit_price->withScale(4)->equals(Money::of($row['purchase'], $currency, 4))
                        && $existing->list_unit_price !== null
                        && $existing->list_unit_price->withScale(4)->equals(Money::of($row['list'], $currency, 4));
                    if ($same) {
                        $counters['unchanged']++;

                        continue;
                    }
                    $counters['updated']++;
                    if ($existing->valid_from->isSameDay($today)) {
                        $existing->forceFill($values)->save(); // zweite Änderung am selben Tag: in der Zeile

                        continue;
                    }
                    $existing->forceFill(['valid_to' => $today->subDay()->toDateString()])->save();
                } else {
                    $counters['created']++;
                }
                $inserts[] = $values + [
                    'organization_id' => $organizationId,
                    'provider' => SubscriptionProvider::DomainReselling->value,
                    'product' => $row['product'],
                    'price_class' => $row['class'],
                    'term_months' => self::TERM_MONTHS,
                    'interval' => BillingFrequency::Yearly->value,
                    'valid_from' => $today->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($inserts, 500) as $chunk) {
                ResalePriceEntry::query()->insert($chunk);
            }
        });

        return $counters;
    }

    /**
     * Ein Lesebefehl; ein Fehlercode des Gateways bricht den Lauf ab, statt leere Preise zu spiegeln.
     *
     * @param  array<string, string|null>  $params
     */
    private static function call(DomainProviderAdapter $adapter, string $command, array $params): DomainResponse {
        $response = $adapter->execute($command, $params);
        if (! $response->isSuccess()) {
            throw new RuntimeException(sprintf('%s: %d %s', $command, $response->code, $response->description));
        }

        return $response;
    }

    /** Dezimalwert des Portals („4.50“), sonst null. */
    private static function decimal(?string $value): ?string {
        $value = trim((string) $value);

        return $value !== '' && is_numeric($value) ? $value : null;
    }
}
