<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainContractCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Domain;

use App\Models\Domain\DomainProviderConnection;
use Illuminate\Console\Command;

/**
 * Belegter API-Vertrag je Verbindung (Feature 083, Abgleich 2026-10-29): welche
 * Eigenschaftsnamen der Anbieter je Befehl tatsächlich geliefert hat, daneben die
 * Schlüssel, die die Dienste lesen. Fehlt ein gelesener Schlüssel in der Lieferung,
 * steht er als „fehlt“ — so fällt ein stiller Mapping-Fehler wie beim
 * Buchungsjournal (leer seit August) beim nächsten Sync auf.
 */
class DomainContractCommand extends Command {
    protected $signature = 'domain:contract {--connection= : Nur eine Verbindung (ID)}';

    protected $description = 'Zeigt je Befehl die vom Domainanbieter gelieferten Eigenschaftsnamen und die gelesenen Schlüssel';

    /**
     * Gelesene Schlüssel je Befehl (kompakte Schreibweise, ein Kandidat je Feld genügt).
     *
     * @var array<string, array<string, list<string>>>
     */
    public const EXPECTED = [
        'querydomainlist' => ['Domain' => ['domain', 'object'], 'Subuser' => ['user', 'domainuser', 'subuser'], 'Status' => ['status', 'domainstatus'], 'Verlängerungsmodus' => ['renewalmode', 'domainrenewalmode'], 'Ablauf' => ['expirationdate', 'registrationexpirationdate', 'domainregistrationexpirationdate']],
        'statusdomain' => ['Status' => ['status'], 'Verlängerungsmodus' => ['renewalmode'], 'Ablauf' => ['expirationdate', 'registrationexpirationdate'], 'Failure-Datum' => ['failuredate'], 'Preis' => ['renewalprice', 'price'], 'Inhaberkontakt' => ['ownercontact', 'ownercontact0']],
        'queryuserlist' => ['Subuser' => ['user', 'username', 'subuser'], 'Übergeordnet' => ['parentuser', 'parent', 'userparent'], 'Saldo' => ['balance', 'accountbalance', 'userbalance'], 'Währung' => ['currency', 'usercurrency', 'accountcurrency']],
        'querycontactlist' => ['Kontakt' => ['contact', 'handle', 'id'], 'Organisation' => ['contactorganization', 'organization', 'org'], 'Land' => ['contactcountry', 'country']],
        'queryeventlist' => ['Ereignis' => ['event', 'eventid', 'id'], 'Klasse' => ['eventclass', 'class'], 'Unterklasse' => ['eventsubclass', 'subclass', 'eventaction', 'action'], 'Objekt' => ['objectid', 'object', 'eventobject'], 'Datum' => ['eventdate', 'date']],
        'queryaccountinglist' => ['Datum' => ['accountingdate', 'date'], 'Typ' => ['accountingtype', 'type'], 'Beschreibung' => ['accountingdescription', 'description'], 'Betrag' => ['accountingprice', 'price', 'netamount'], 'Laufzeit' => ['invoiceperiod', 'period'], 'Subreseller' => ['subreseller', 'subuser', 'user']],
        'checkdomains' => ['Verfügbarkeit' => ['domaincheck', 'status', 'availability']],
        'statusdnszone' => ['Einträge' => ['rr'], 'SOA' => ['soa', 'soaserial']],
    ];

    public function handle(): int {
        $query = DomainProviderConnection::query()->orderBy('id');
        if (($id = $this->option('connection')) !== null) {
            $query->whereKey($id);
        }
        $connections = $query->get();
        if ($connections->isEmpty()) {
            $this->line('Keine Domain-Verbindung.');

            return self::SUCCESS;
        }
        foreach ($connections as $connection) {
            $this->info(sprintf('Verbindung #%d %s (%s)', $connection->id, (string) $connection->name, $connection->environment->value));
            $observed = $connection->observed_properties ?? [];
            if ($observed === []) {
                $this->line('  Noch kein Vertrag beobachtet — erst nach dem nächsten Sync.');

                continue;
            }
            $rows = [];
            foreach ($observed as $command => $names) {
                $missing = [];
                foreach (self::EXPECTED[$command] ?? [] as $label => $candidates) {
                    if (! self::delivers($names, $candidates)) {
                        $missing[] = $label;
                    }
                }
                $rows[] = [$command, implode(', ', $names), $missing === [] ? '—' : implode(', ', $missing)];
            }
            $this->table(['Befehl', 'Gelieferte Eigenschaften', 'Fehlt'], $rows);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $candidates
     */
    public static function delivers(array $names, array $candidates): bool {
        $compact = array_map(static fn(string $n): string => strtolower(str_replace(['_', '-', ' '], '', $n)), $names);
        foreach ($candidates as $candidate) {
            if (in_array(strtolower(str_replace(['_', '-', ' '], '', $candidate)), $compact, true)) {
                return true;
            }
        }

        return false;
    }
}
