<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainZonePricesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainConnectionStatus};
use App\Models\Domain\{DomainProviderConnection, DomainResellerAccount};
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Services\Domain\DomainProviderResolver;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vertragsprobe der Zonenpreise (Feature 185, Befund 2026-10-30): die
 * Benutzerklassen des Portals sind Preisstaffeln — das Journal zeigt nur den
 * Einkauf des Inhabers, die Monatsrechnung an den Subuser läuft zum Staffelpreis.
 * Die Wissensdatenbank beschreibt `QueryZoneList` (eigene Preise je Zone, mit
 * REGISTRAR die eines Subusers) und `QueryZonePriceList` (Preise je Zone,
 * Subreseller oder Staffel); der Live-Gateway schreibt Feldnamen aber anders als
 * die Doku. Der Befehl liest beide roh, zeigt Code, Felder und Zeilen und zeichnet
 * die gelieferten Eigenschaften für `domain:contract` auf. Nur lesend.
 */
class DomainZonePricesCommand extends Command {
    protected $signature = 'domain:zone-prices
        {--connection= : Nur eine Verbindung (ID)}
        {--zone= : Nur diese Zone, z. B. de}
        {--registrar= : Preise, die für diesen Subuser gelten}
        {--scale=* : Preisstaffel(n) für QueryZonePriceList; ohne Angabe die Klassen der bekannten Subuser}
        {--rows=15 : Zeilen je Antwort in der Ausgabe}';

    protected $description = 'Vertragsprobe: Zonenpreise des Domainanbieters (QueryZoneList, QueryZonePriceList) roh anzeigen und die gelieferten Eigenschaften aufzeichnen';

    public function handle(DomainProviderResolver $resolver): int {
        $query = DomainProviderConnection::query()->where('status', DomainConnectionStatus::Active->value)->orderBy('id');
        if (($id = $this->option('connection')) !== null) {
            $query->whereKey($id);
        }
        $connections = $query->get()->filter(static fn (DomainProviderConnection $connection): bool => $connection->isRunnable());
        if ($connections->isEmpty()) {
            $this->line('Keine laufende Domain-Verbindung.');

            return self::SUCCESS;
        }
        $rows = max(1, (int) $this->option('rows'));
        $zone = StringHelper::trimToNull((string) $this->option('zone'));
        $registrar = StringHelper::trimToNull((string) $this->option('registrar'));
        $failed = false;
        foreach ($connections as $connection) {
            $this->info(sprintf('Verbindung #%d %s (%s)', $connection->id, (string) $connection->name, $connection->environment->value));
            $adapter = $resolver->for($connection);

            $failed = ! $this->probe($adapter, 'QueryZoneList', ['ZONE' => $zone, 'REGISTRAR' => $registrar], $rows) || $failed;
            foreach ($this->scales($connection) as $scale) {
                $failed = ! $this->probe($adapter, 'QueryZonePriceList', ['PRICESCALE' => $scale, 'ZONE' => $zone, 'WIDE' => '1'], $rows) || $failed;
            }
            if ($registrar !== null) {
                $failed = ! $this->probe($adapter, 'QueryZonePriceList', ['REGISTRAR' => $registrar, 'ZONE' => $zone, 'WIDE' => '1'], $rows) || $failed;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ein Befehl roh: Parameter, Antwortcode, gelieferte Eigenschaften, die ersten Zeilen.
     * Preise gehören zum Bereich Accounting — „zone“ im Namen hieße sonst DNS.
     *
     * @param  array<string, string|null>  $params
     */
    private function probe(DomainProviderAdapter $adapter, string $command, array $params, int $rows): bool {
        $shown = array_filter($params, static fn (?string $value): bool => $value !== null && $value !== '');
        $this->line(sprintf('  %s %s', $command, $shown === [] ? '(ohne Parameter)' : http_build_query($shown, '', ' ')));
        try {
            $response = $adapter->execute($command, $params, DomainCapabilityArea::Accounting);
        } catch (Throwable $e) {
            $this->warn(sprintf('    Fehler: %s — %s', class_basename($e), $e->getMessage()));

            return false;
        }
        $this->line(sprintf('    Antwort %d %s%s', $response->code, $response->description, $response->isComplete() ? '' : ' (unvollständig, kein EOF)'));
        $names = $response->propertyNames();
        if ($names === []) {
            $this->line('    Keine Eigenschaften geliefert.');

            return $response->isSuccess();
        }
        $this->line('    Eigenschaften: ' . implode(', ', $names));
        $all = $response->rows();
        $this->line(sprintf('    %d Zeile(n)%s', count($all), count($all) > $rows ? sprintf(', die ersten %d:', $rows) : ':'));
        $this->table($names, array_map(
            static fn (array $row): array => array_map(static fn (string $name): string => (string) ($row[$name] ?? ''), $names),
            array_slice($all, 0, $rows),
        ));

        return $response->isSuccess();
    }

    /**
     * Zu prüfende Staffeln: die Option, sonst die Klassen aller Subuser der Verbindung (GOLD, STANDARD, RESELLER …).
     *
     * @return list<string>
     */
    private function scales(DomainProviderConnection $connection): array {
        $scales = [];
        foreach ((array) $this->option('scale') as $scale) {
            $scale = strtoupper(trim((string) $scale));
            if ($scale !== '') {
                $scales[$scale] = true;
            }
        }
        if ($scales !== []) {
            return array_keys($scales);
        }
        $classes = DomainResellerAccount::query()->withoutGlobalScopes()
            ->where('connection_id', $connection->id)
            ->whereNotNull('user_class')
            ->distinct()
            ->orderBy('user_class')
            ->pluck('user_class');

        return array_values(array_filter(array_map(static fn ($class): string => strtoupper(trim((string) $class)), $classes->all()), static fn (string $class): bool => $class !== ''));
    }
}
