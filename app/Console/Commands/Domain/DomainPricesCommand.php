<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainPricesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Domain;

use App\Enums\Domain\DomainConnectionStatus;
use App\Models\Domain\{DomainProviderConnection, DomainResellerAccount};
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Plugins\Support\Domain\DomainResponse;
use App\Services\Domain\{DomainProviderResolver, ProviderRelations};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vertragsprobe der Domainpreise (Feature 185, Befund 2026-10-30): die
 * Benutzerklassen des Portals sind Preisstaffeln — das Journal zeigt nur den
 * Einkauf des Inhabers, die Monatsrechnung an den Subuser läuft zum Staffelpreis.
 * Laut Handbuch (API Reference Manual 3.0, Abschnitt 1.1) stehen die Preise als
 * Relationen `PRICE_CLASS_DOMAIN_<ZONE>_ANNUAL|SETUP|TRANSFER|CURRENCY|…` am
 * Benutzer (`StatusUser`, mit `subuser` die wirksamen eines Subusers) und an der
 * Benutzerklasse (`StatusUserclass`; Klassen aus `QueryUserClassList`). Die
 * CentralNic-Befehle `QueryZoneList`/`QueryZonePriceList` kennt der Gateway nicht
 * („500 Invalid command name“, Prod 2026-10-30). Der Befehl liest roh, zeigt Code,
 * Felder und die Domainpreise je Zone und zeichnet die Eigenschaften für
 * `domain:contract` auf. Nur lesend. Live: `StatusUser` schreibt
 * `relationtype`/`relationvalue`, `StatusUserclass` `relation type`/`relation value`,
 * `QueryUserClassList` `user class`; Klassenpreise = eigener Einkauf × `FACTOR`
 * (STANDARD 1,80, RESELLER 1,40); `StatusUser` liefert auch `twofactorsecret` —
 * Namen werden aufgezeichnet, Werte nie gespeichert. Den Katalog füllt `domain:sync-prices`.
 */
class DomainPricesCommand extends Command {
    protected $signature = 'domain:prices
        {--connection= : Nur eine Verbindung (ID)}
        {--subuser=* : Wirksame Preise dieser Subuser (StatusUser); ohne Angabe alle bekannten Subuser der Verbindung}
        {--class=* : Benutzerklassen (StatusUserclass); ohne Angabe die Klassen aus QueryUserClassList}
        {--zone= : Nur Preise dieser Zone, z. B. de}
        {--raw : Alle Relationen zeigen, nicht nur Domainpreise}';

    protected $description = 'Vertragsprobe: Domainpreise des Anbieters aus den Benutzerrelationen (StatusUser je Konto und Subuser, StatusUserclass je Klasse) roh anzeigen und die gelieferten Eigenschaften aufzeichnen';

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
        $failed = false;
        foreach ($connections as $connection) {
            $this->info(sprintf('Verbindung #%d %s (%s)', $connection->id, (string) $connection->name, $connection->environment->value));
            $adapter = $resolver->for($connection);

            $failed = $this->probe($adapter, 'StatusUser', [], 'eigenes Konto') === null || $failed;
            foreach ($this->subusers($connection) as $subuser) {
                $failed = $this->probe($adapter, 'StatusUser', ['subuser' => $subuser], 'Subuser ' . $subuser) === null || $failed;
            }
            $classes = $this->listOption('class');
            if ($classes === []) {
                $list = $this->probe($adapter, 'QueryUserClassList', [], 'Benutzerklassen');
                $failed = $list === null || $failed;
                $classes = $list === null ? [] : array_values(array_unique(array_filter(ProviderRelations::values($list, 'userclass'))));
            }
            foreach ($classes as $class) {
                $failed = $this->probe($adapter, 'StatusUserclass', ['userclass' => $class], 'Klasse ' . $class) === null || $failed;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ein Befehl roh: Parameter, Antwortcode, gelieferte Eigenschaften, Domainpreise je Zone.
     * null bei Fehler oder Fehlercode.
     *
     * @param  array<string, string|null>  $params
     */
    private function probe(DomainProviderAdapter $adapter, string $command, array $params, string $label): ?DomainResponse {
        $shown = array_filter($params, static fn (?string $value): bool => $value !== null && $value !== '');
        $this->line(sprintf('  %s %s(%s)', $command, $shown === [] ? '' : http_build_query($shown, '', ' ') . ' ', $label));
        try {
            $response = $adapter->execute($command, $params);
        } catch (Throwable $e) {
            $this->warn(sprintf('    Fehler: %s — %s', class_basename($e), $e->getMessage()));

            return null;
        }
        $this->line(sprintf('    Antwort %d %s%s', $response->code, $response->description, $response->isComplete() ? '' : ' (unvollständig, kein EOF)'));
        $names = $response->propertyNames();
        if ($names === []) {
            $this->line('    Keine Eigenschaften geliefert.');

            return $response->isSuccess() ? $response : null;
        }
        $this->line('    Eigenschaften: ' . implode(', ', $names));
        $class = $command === 'StatusUser' ? (ProviderRelations::values($response, 'userclass')[0] ?? null) : null;
        if ($class !== null) {
            $this->line('    Klasse: ' . $class);
        }
        $relations = ProviderRelations::fromResponse($response);
        if ($relations !== []) {
            $this->showRelations($relations);
        } elseif ($command === 'QueryUserClassList') {
            $this->line('    Klassen: ' . implode(', ', ProviderRelations::values($response, 'userclass')));
        }

        return $response->isSuccess() ? $response : null;
    }

    /**
     * Domainpreise je Zone als Tabelle (mit --raw alle Relationen, mit --zone nur eine Zone).
     *
     * @param  list<array{type: string, value: string}>  $relations
     */
    private function showRelations(array $relations): void {
        $zoneFilter = StringHelper::trimToNull((string) $this->option('zone'));
        $zoneFilter = $zoneFilter !== null ? strtoupper(ltrim($zoneFilter, '.')) : null;
        $prices = ProviderRelations::domainPrices($relations);
        $priceCount = array_sum(array_map('count', $prices));
        if ($zoneFilter !== null) {
            $prices = array_intersect_key($prices, [$zoneFilter => true]);
        }
        $this->line(sprintf('    %d Relationen, davon %d ohne Domainpreis%s', count($relations), count($relations) - $priceCount, $zoneFilter !== null ? sprintf(', Zone %s', $zoneFilter) : ''));
        if ((bool) $this->option('raw')) {
            $this->table(['Relation', 'Wert'], array_map(static fn (array $r): array => [$r['type'], $r['value']], $relations));

            return;
        }
        if ($prices === []) {
            $this->line('    Keine Domainpreise in den Relationen.');

            return;
        }
        $rows = [];
        foreach ($prices as $zone => $kinds) {
            $rest = [];
            foreach ($kinds as $kind => $value) {
                if (! in_array($kind, ['SETUP', 'ANNUAL', 'TRANSFER', 'CURRENCY'], true)) {
                    $rest[] = $kind . '=' . $value;
                }
            }
            $rows[] = [strtolower($zone), $kinds['SETUP'] ?? '', $kinds['ANNUAL'] ?? '', $kinds['TRANSFER'] ?? '', $kinds['CURRENCY'] ?? '', implode(', ', $rest)];
        }
        $this->table(['Zone', 'Setup', 'Jahr', 'Transfer', 'Währung', 'Weitere'], $rows);
    }

    /**
     * Zu prüfende Subuser: die Option, sonst alle bekannten Subuser der Verbindung (nicht das eigene Konto).
     *
     * @return list<string>
     */
    private function subusers(DomainProviderConnection $connection): array {
        $option = $this->listOption('subuser');
        if ($option !== []) {
            return $option;
        }
        $users = DomainResellerAccount::query()->withoutGlobalScopes()
            ->where('connection_id', $connection->id)
            ->where('depth', '>', 0)
            ->orderBy('external_user')
            ->pluck('external_user');

        return array_values(array_filter(array_map(static fn ($user): string => trim((string) $user), $users->all()), static fn (string $user): bool => $user !== ''));
    }

    /** @return list<string> */
    private function listOption(string $name): array {
        $values = [];
        foreach ((array) $this->option($name) as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }
}
