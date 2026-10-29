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
 * Schlüssel, die die Dienste lesen. Zwei Befunde je Befehl: „Fehlt“ — ein gelesenes
 * Feld kommt nie an (stiller Mapping-Fehler wie beim Buchungsjournal, leer seit
 * August) — und „Nicht verwendet“ — der Anbieter liefert etwas, das nirgends landet
 * (Wunsch des Inhabers 2026-10-29: nicht nach fehlenden Daten fragen müssen).
 */
class DomainContractCommand extends Command {
    protected $signature = 'domain:contract {--connection= : Nur eine Verbindung (ID)}';

    protected $description = 'Zeigt je Befehl die vom Domainanbieter gelieferten Eigenschaftsnamen, fehlende gelesene Schlüssel und ungenutzte Felder';

    /**
     * Gelesene Pflichtfelder je Befehl (kompakte Schreibweise; ein Kandidat je Feld genügt) — fehlen sie, meldet der Befehl das.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const EXPECTED = [
        'querydomainlist' => ['Domain' => ['domain', 'object'], 'Subuser' => ['user', 'domainuser', 'subuser'], 'Status' => ['status', 'domainstatus'], 'Verlängerungsmodus' => ['renewalmode', 'domainrenewalmode'], 'Ablauf' => ['expirationdate', 'registrationexpirationdate', 'domainregistrationexpirationdate']],
        'statusdomain' => ['Status' => ['status'], 'Verlängerungsmodus' => ['renewalmode'], 'Ablauf' => ['expirationdate', 'registrationexpirationdate'], 'Failure-Datum' => ['failuredate'], 'Preis' => ['renewalprice', 'price'], 'Inhaberkontakt' => ['ownercontact', 'ownercontact0']],
        'queryuserlist' => ['Subuser' => ['user', 'username', 'subuser'], 'Übergeordnet' => ['parentuser', 'parent', 'userparent'], 'Klasse' => ['useruserclass', 'userclass', 'class'], 'Saldo' => ['useraccountcurrent', 'balance', 'accountbalance', 'userbalance'], 'Währung' => ['useraccountcurrency', 'currency', 'usercurrency', 'accountcurrency'], 'Kundennummer' => ['userid', 'customernumber', 'customerid']],
        'querycontactlist' => ['Kontakt' => ['contact', 'handle', 'id'], 'Organisation' => ['contactorganization', 'organization', 'org'], 'Land' => ['contactcountry', 'country']],
        'queryeventlist' => ['Ereignis' => ['event', 'eventid', 'id']],
        'statusevent' => ['Klasse' => ['eventclass', 'class'], 'Unterklasse' => ['eventsubclass', 'subclass', 'eventaction', 'action'], 'Objekt' => ['objectid', 'object', 'eventobject'], 'Datum' => ['eventdate', 'date']],
        'queryaccountinglist' => ['Datum' => ['accountingdate', 'date'], 'Typ' => ['accountingtype', 'type'], 'Beschreibung' => ['accountingdescription', 'description'], 'Menge' => ['accountingamount', 'amount', 'quantity'], 'Betrag' => ['accountingprice', 'price', 'netamount'], 'Rechnung' => ['accountinginvoiceid', 'invoiceid', 'invoice']],
        'checkdomains' => ['Verfügbarkeit' => ['domaincheck', 'status', 'availability']],
        'statusdnszone' => ['Einträge' => ['rr'], 'SOA' => ['soa', 'soaserial']],
    ];

    /**
     * Weitere Felder, die die Dienste verwenden (optional), kompakt.
     *
     * @var array<string, list<string>>
     */
    public const CONSUMED = [
        'querydomainlist' => ['registrar', 'domainregistrar', 'domainrepository', 'paiddate', 'accountingdate', 'domainaccountingdate', 'paiduntildate', 'domainpaiduntildate', 'failuredate', 'domainfailuredate', 'finalizationdate', 'domainfinalizationdate', 'registrationdate', 'createddate', 'domaincreateddate', 'nextaction', 'domainnextaction', 'nextactiondate', 'domainnextactiondate', 'updateddate', 'domainupdateddate', 'renewalperiod', 'domainrenewalperiod', 'domainexpirationdate', 'ownercontact', 'ownercontact0', 'domainownercontact', 'registrant', 'registrantcontact', 'ownerhandle', 'admincontact', 'domainadmincontact', 'techcontact', 'domaintechcontact', 'billingcontact', 'domainbillingcontact', 'nameserver', 'nameserver0', 'nameserver1', 'nameserver2', 'nameserver3', 'nameserver4', 'nameserver5', 'dnssec', 'idndomain', 'domainidn', 'wps', 'xwhoisprivacy', 'whoisprivacy', 'domainsubclass', 'subclass', 'transfermode', 'domaintransfermode', 'rgpstatus', 'createdby', 'domaincreatedby', 'updatedby', 'domainupdatedby', 'comment', 'domaincomment', 'iregcreated', 'transferlock'],
        'statusdomain' => ['registrar', 'nextaction', 'nextactiondate', 'transferlock', 'registrationdate', 'createddate', 'paiddate', 'accountingdate', 'paiduntildate', 'finalizationdate', 'currency', 'renewalcurrency', 'revision', 'roid', 'updateddate', 'renewalperiod', 'nameserver', 'admincontact', 'techcontact', 'billingcontact', 'dnssec', 'transfermode', 'rgpstatus', 'repository', 'createdby', 'updatedby', 'comment', 'domain', 'zone'],
        'queryuserlist' => ['useractive', 'active', 'status', 'userstatus', 'depth', 'userdepth', 'level', 'useraccountcredit', 'credit', 'useraccountvat', 'vatrate', 'useremail', 'email', 'usercomment', 'comment', 'usercreateddate', 'createddate', 'lastlogin', 'userlastlogin'],
        'querycontactlist' => ['user', 'contactuser', 'owner', 'parentuser', 'contacttitle', 'contactfirstname', 'contactmiddlename', 'contactlastname', 'contactname', 'contactstreet', 'contactzip', 'contactcity', 'contactstate', 'contactphone', 'contactfax', 'contactemail', 'contacttype', 'contactstatus', 'verified', 'emailverified', 'addressvalidated', 'verificationdate', 'contactrepository', 'contactid', 'contactcreateddate', 'contactupdateddate'],
        'statusevent' => ['info', 'eventinfo', 'data'],
        'queryaccountinglist' => ['accountingvat', 'vatrate', 'vat', 'accountingvatprice', 'vatprice', 'taxamount', 'accountingcurrency', 'currency', 'invoiceperiod', 'period', 'subreseller', 'subuser', 'user', 'accountingpricing', 'pricing', 'accountingreference', 'reference', 'accountingobject', 'object', 'domain'],
        'checkdomains' => ['domaincheck', 'domainchecktime', 'xfeeamount', 'price', 'aftermarketprice', 'xfeecurrency', 'currency', 'aftermarketcurrency', 'xfeeclass', 'class', 'reason', 'premium', 'aftermarket', 'aftermarketinfo', 'domain'],
        'statusdnszone' => ['rr', 'soa', 'revision', 'soaserial', 'dnszone'],
    ];

    /**
     * Bewusst ungenutzt: Summen- und Blätterfelder sowie Geheimnisse, die nie gespeichert werden.
     *
     * @var list<string>
     */
    public const IGNORED = [
        'count', 'first', 'last', 'limit', 'total', 'column', 'sum', 'sumprice', 'sumvatprice', 'openingbalance', 'openingbalanceprice', 'openingbalancevatprice', 'eventscount', 'eventstotal',
        'userpassword', 'domainauth', 'contactauth', 'auth', 'userindex', 'userupdateddate', 'contactupdatedby', 'contactcreatedby',
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
                $unused = self::unused($command, $names);
                $rows[] = [$command, implode(', ', $names), $missing === [] ? '—' : implode(', ', $missing), $unused === [] ? '—' : implode(', ', $unused)];
            }
            $this->table(['Befehl', 'Gelieferte Eigenschaften', 'Fehlt', 'Nicht verwendet'], $rows);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $candidates
     */
    public static function delivers(array $names, array $candidates): bool {
        $compact = array_map(self::compact(...), $names);
        foreach ($candidates as $candidate) {
            if (in_array(self::compact($candidate), $compact, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gelieferte Felder, die weder gelesen noch bewusst ignoriert werden.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function unused(string $command, array $names): array {
        $known = array_merge(self::CONSUMED[$command] ?? [], self::IGNORED);
        foreach (self::EXPECTED[$command] ?? [] as $candidates) {
            $known = array_merge($known, $candidates);
        }
        $known = array_map(self::compact(...), $known);
        $unused = [];
        foreach ($names as $name) {
            if (! in_array(self::compact($name), $known, true)) {
                $unused[] = $name;
            }
        }

        return $unused;
    }

    private static function compact(string $key): string {
        return strtolower(str_replace(['_', '-', ' '], '', $key));
    }
}
