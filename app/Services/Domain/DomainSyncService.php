<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainRenewalMode, DomainSyncStatus};
use App\Models\Domain\{DomainContactProjection, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Plugins\Support\Domain\DomainResponse;
use App\Services\Domain\Concerns\ReadsProviderFields;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;

/**
 * Projiziert Subuser/Reseller, Domains und Registry-Kontakte des
 * DomainReselling-Kontos in WorkDiary (Feature 083, MVP-386/387).
 * DomainReselling bleibt führend; hier entstehen nur Projektionen mit
 * Revision + `raw_hash` und Aktualitätsstempel. Auth-Codes werden NICHT in
 * die Projektion übernommen.
 */
class DomainSyncService {
    use ReadsProviderFields;

    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /** Voller Abgleich: Reseller, Domains (SELF + Subuser) und Kontakte. */
    public function syncAll(DomainProviderConnection $connection): void {
        $adapter = $this->resolver->for($connection);

        $this->syncResellerAccounts($connection, $adapter);
        $this->syncDomains($connection, 'ALL', $adapter);
        $this->syncContacts($connection, $adapter);

        $connection->forceFill(['last_sync_at' => Carbon::now()])->save();
    }

    /** Subuser-/Subreseller-Hierarchie (Herkunft/Tiefe/Parent bleiben erhalten). */
    public function syncResellerAccounts(DomainProviderConnection $connection, ?DomainProviderAdapter $adapter = null): int {
        $adapter ??= $this->resolver->for($connection);
        // wide=1: erst der Verbose-Modus liefert UserClass/Aktivstatus/Saldo.
        $response = $adapter->execute('QueryUserList', ['wide' => 1], DomainCapabilityArea::Subuser);

        $count = 0;
        foreach ($response->rows() as $row) {
            $user = $this->field($row, ['user', 'username', 'subuser']);
            if ($user === null || $user === '') {
                continue;
            }

            $account = DomainResellerAccount::query()->updateOrCreate(
                [
                    'organization_id' => $connection->organization_id,
                    'connection_id' => $connection->id,
                    'external_user' => $user,
                ],
                [
                    // Live-Vertrag (domain:contract 2026-10-29): „parent user“, useruserclass, useractive,
                    // useraccountcurrency, useraccountcurrent (Saldo); eine Tiefe liefert die Liste nicht.
                    'parent_user' => $this->field($row, ['parentuser', 'parent', 'userparent']),
                    'depth' => (int) ($this->field($row, ['depth', 'userdepth', 'level']) ?? 0),
                    'user_class' => $this->field($row, ['useruserclass', 'userclass', 'class']),
                    'active' => $this->boolField($row, ['useractive', 'active', 'status', 'userstatus'], true),
                    'currency' => $this->currency($this->field($row, ['useraccountcurrency', 'currency', 'usercurrency', 'accountcurrency'])),
                    'balance_snapshot' => NumberHelper::normalizeDecimalStringOrNull($this->field($row, ['useraccountcurrent', 'balance', 'accountbalance', 'userbalance', 'amount']) ?? ''),
                    'credit_snapshot' => NumberHelper::normalizeDecimalStringOrNull($this->field($row, ['useraccountcredit', 'credit']) ?? ''),
                    'vat_rate' => NumberHelper::normalizeDecimalStringOrNull($this->field($row, ['useraccountvat', 'vatrate']) ?? ''),
                    'email' => $this->field($row, ['useremail', 'email']),
                    'note' => $this->field($row, ['usercomment', 'comment']),
                    'provider_created_at' => $this->date($this->field($row, ['usercreateddate', 'createddate'])),
                    'last_login_at' => $this->date($this->field($row, ['lastlogin', 'userlastlogin'])),
                    'balance_at' => Carbon::now(),
                    'raw_hash' => CryptoHelper::hash(JsonHelper::encode($row)),
                    'synced_at' => Carbon::now(),
                ],
            );
            // Kundennummer des Subusers beim Anbieter = USERID (Portal-Kundenliste 2026-10-29: 90021 nordlicht-systems,
            // 90055 nebenhof); die Monatsrechnungen heißen „<Kundennummer>-MM-JJJJ“. Ein Wert des Inhabers bleibt.
            $customerNumber = $this->field($row, ['userid', 'customernumber', 'customerid']);
            if ($account->provider_customer_number === null && $customerNumber !== null && preg_match('/^[A-Za-z0-9-]{1,32}$/', $customerNumber) === 1) {
                $account->forceFill(['provider_customer_number' => $customerNumber])->saveQuietly();
            }
            $count++;
        }
        $this->assignDepths($connection);

        return $count;
    }

    /** Tiefe aus der Parent-Kette: Konten ohne bekannten Parent sind die Wurzel (0), darunter je Stufe +1. */
    private function assignDepths(DomainProviderConnection $connection): void {
        $accounts = DomainResellerAccount::query()->where('connection_id', $connection->id)->get()->keyBy('external_user');
        foreach ($accounts as $account) {
            $depth = 0;
            $parent = $account->parent_user;
            $seen = [];
            while ($parent !== null && isset($accounts[$parent]) && ! isset($seen[$parent])) {
                $seen[$parent] = true;
                $depth++;
                $parent = $accounts[$parent]->parent_user;
            }
            if ((int) $account->depth !== $depth) {
                $account->forceFill(['depth' => $depth])->saveQuietly();
            }
        }
    }

    /**
     * Paginierte Domainliste je `userdepth` (SELF|SUBUSER|ALLSUBUSER|ALL).
     * Legt/aktualisiert die Projektion mit den Listenfeldern und markiert sie
     * als aktuell; Detailfelder folgen über {@see refreshDomain()}.
     */
    public function syncDomains(DomainProviderConnection $connection, string $userDepth = 'ALL', ?DomainProviderAdapter $adapter = null): int {
        $adapter ??= $this->resolver->for($connection);
        $pageSize = $this->resolver->settings((int) $connection->organization_id)->listPageSize;
        $first = 0;
        $total = 0;

        do {
            // wide=1 ist PFLICHT: ohne Verbose-Modus liefert die echte API nur
            // die Domainnamen — USER (→ Reseller-Verknüpfung), Registrar,
            // Status und Ablauf fehlen dann komplett (Live-Befund 2026-08-05).
            $response = $adapter->execute('QueryDomainList', [
                'userdepth' => $userDepth,
                'first' => $first,
                'limit' => $pageSize,
                'wide' => 1,
            ], DomainCapabilityArea::Domains);

            $rows = $response->rows();
            foreach ($rows as $row) {
                $domain = $this->field($row, ['domain', 'object']);
                if ($domain === null || $domain === '') {
                    continue;
                }
                $this->upsertFromRow($connection, $domain, $row);
                $total++;
            }

            $first += $pageSize;
        } while (count($rows) >= $pageSize && $rows !== []);

        return $total;
    }

    /** Detailabgleich einer einzelnen Domain über StatusDomain. */
    public function refreshDomain(DomainProjection $projection): DomainProjection {
        $connection = $projection->providerConnection();
        $adapter = $this->resolver->for($connection);
        $response = $adapter->execute('StatusDomain', ['domain' => $projection->external_domain], DomainCapabilityArea::Domains);

        $row = $this->flatten($response);
        $this->applyDetail($projection, $row, $response);
        $projection->save();

        return $projection;
    }

    /** Registry-Kontakte als providergeführte, minimierte Projektionen. */
    public function syncContacts(DomainProviderConnection $connection, ?DomainProviderAdapter $adapter = null): int {
        $adapter ??= $this->resolver->for($connection);
        // wide=1: ohne Verbose-Modus liefert der Gateway nur die Handles (domain:contract 2026-10-29).
        $response = $adapter->execute('QueryContactList', ['wide' => 1], DomainCapabilityArea::Contacts);

        $count = 0;
        foreach ($response->rows() as $row) {
            $handle = $this->field($row, ['contact', 'handle', 'id']);
            if ($handle === null || $handle === '') {
                continue;
            }

            DomainContactProjection::query()->updateOrCreate(
                [
                    'organization_id' => $connection->organization_id,
                    'connection_id' => $connection->id,
                    'external_handle' => $handle,
                ],
                [
                    'external_user' => $this->field($row, ['user', 'contactuser', 'owner', 'parentuser']),
                    // Inhaberdaten für die Domainverwaltung (Live-Vertrag 2026-10-29) — nie CONTACTAUTH.
                    'snapshot' => $this->contactSnapshot($row),
                    'raw_hash' => CryptoHelper::hash(JsonHelper::encode($row)),
                    'synced_at' => Carbon::now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    /** Markiert Projektionen älter als das Datenalter-Budget als veraltet. */
    public function markStale(DomainProviderConnection $connection): int {
        $threshold = Carbon::now()->subHours($this->resolver->settings((int) $connection->organization_id)->staleAfterHours);

        return DomainProjection::query()
            ->where('connection_id', $connection->id)
            ->where('sync_status', DomainSyncStatus::Current->value)
            ->where('synced_at', '<', $threshold)
            ->update(['sync_status' => DomainSyncStatus::Stale->value]);
    }

    /** @param  array<string, string>  $row */
    private function upsertFromRow(DomainProviderConnection $connection, string $domain, array $row): void {
        $reseller = null;
        $user = $this->field($row, ['user', 'owner']);
        if ($user !== null && $user !== '') {
            $reseller = DomainResellerAccount::query()
                ->where('connection_id', $connection->id)
                ->where('external_user', $user)
                ->first();
        }

        // Die echte Listenantwort präfixt die Felder mit DOMAIN…
        // (DOMAINREGISTRAR, DOMAINSTATUS, …) — die unpräfixierten Namen bleiben
        // als Fallback für StatusDomain-artige Antworten erhalten.
        $values = [
            'connection_id' => $connection->id,
            'external_domain' => $domain,
            'external_user' => $user ?? '',
            'reseller_account_id' => $reseller?->id,
            // Live-Vertrag (domain:contract 2026-10-29): die Liste nennt keinen Registrar, aber die Registry (DOMAINREPOSITORY).
            'registrar' => $this->field($row, ['registrar', 'domainregistrar', 'domainrepository']),
            'status' => $this->field($row, ['status', 'domainstatus']),
            'sync_status' => DomainSyncStatus::Current->value,
            'renewal_mode' => DomainRenewalMode::fromProvider($this->field($row, ['renewalmode', 'domainrenewalmode']))?->value,
            'expiration_at' => $this->date($this->field($row, ['expirationdate', 'expiration', 'registrationexpirationdate', 'domainregistrationexpirationdate'])),
            'raw_hash' => CryptoHelper::hash(JsonHelper::encode($row)),
            'synced_at' => Carbon::now(),
        ];
        // Nur setzen, wenn geliefert — ein Listen-Sync darf per StatusDomain gefüllte Werte nicht
        // auf null zurücksetzen. Der Live-Gateway liefert diese Daten auch in der Liste
        // (DOMAINACCOUNTINGDATE, DOMAINFAILUREDATE, DOMAINFINALIZATIONDATE, DOMAINCREATEDDATE; domain:contract 2026-10-29).
        foreach (self::optionalColumns($row) as $column => $value) {
            $values[$column] = $value;
        }
        $details = $this->providerDetails($row);
        if ($details !== []) {
            $values['details'] = $details;
        }

        // Registrant-/Owner-Contact-Handle nur setzen, wenn der Provider ihn
        // liefert (sonst würde ein Listen-Sync einen per StatusDomain gefüllten
        // Handle wieder löschen).
        $ownerHandle = $this->ownerHandle($row);
        if ($ownerHandle !== null) {
            $values['owner_handle'] = $ownerHandle;
        }

        // Org-weit eindeutig je Domainname: dieselbe Domain aktualisiert genau
        // EINE Zeile, auch wenn sie über eine andere Verbindung gemeldet wird
        // (connection_id „wandert" mit, keine Doppelzeile → keine Doppelbuchung).
        DomainProjection::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'domain_hash' => DomainProjection::hashFor($domain),
            ],
            $values,
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function applyDetail(DomainProjection $projection, array $row, DomainResponse $response): void {
        $freshMode = DomainRenewalMode::fromProvider($this->field($row, ['renewalmode']));
        $currentMode = $projection->renewal_mode instanceof DomainRenewalMode ? $projection->renewal_mode->value : null;

        $projection->fill([
            'registrar' => $this->field($row, ['registrar']) ?? $projection->registrar,
            'status' => $this->field($row, ['status']) ?? $projection->status,
            'sync_status' => DomainSyncStatus::Current->value,
            'renewal_mode' => $freshMode instanceof DomainRenewalMode ? $freshMode->value : $currentMode,
            'next_action' => $this->field($row, ['nextaction']),
            'transferlock' => $this->boolField($row, ['transferlock'], (bool) $projection->transferlock),
            'registration_at' => $this->date($this->field($row, ['registrationdate', 'createddate'])) ?? $projection->registration_at,
            'expiration_at' => $this->date($this->field($row, ['expirationdate', 'expiration', 'registrationexpirationdate'])) ?? $projection->expiration_at,
            'accounting_at' => $this->date($this->field($row, ['paiddate', 'accountingdate'])) ?? $projection->accounting_at,
            'failure_at' => $this->date($this->field($row, ['failuredate'])) ?? $projection->failure_at,
            'finalization_at' => $this->date($this->field($row, ['finalizationdate'])) ?? $projection->finalization_at,
            'renewal_price' => NumberHelper::normalizeDecimalStringOrNull($this->field($row, ['renewalprice', 'price']) ?? ''),
            'renewal_currency' => $this->currency($this->field($row, ['currency', 'renewalcurrency']))?->value,
            'revision' => $this->field($row, ['revision', 'roid']),
            'owner_handle' => $this->ownerHandle($row) ?? $projection->owner_handle,
            'raw_hash' => $response->rawHash(),
            'synced_at' => Carbon::now(),
        ]);
        $projection->fill(self::optionalColumns($row));
        $details = array_merge($projection->details ?? [], $this->providerDetails($row, $response));
        if ($details !== []) {
            $projection->fill(['details' => $details]);
        }

        // StatusDomain liefert USER — Einzelabgleich heilt damit auch eine
        // fehlende/veraltete Reseller-Verknüpfung (Selbstheilung ohne Vollsync).
        $user = $this->field($row, ['user', 'owner']);
        if ($user !== null && $user !== '') {
            $projection->external_user = $user;
            $projection->reseller_account_id = DomainResellerAccount::query()
                ->where('connection_id', $projection->connection_id)
                ->where('external_user', $user)
                ->first()?->id;
        }
    }

    /**
     * Reduziert eine Detailantwort auf ein flaches Feld-Array (erster Wert).
     *
     * @return array<string, string>
     */
    private function flatten(DomainResponse $response): array {
        $rows = $response->rows();

        return $rows[0] ?? [];
    }

    /**
     * Registrant-Daten als Snapshot — nur bekannte Schlüssel, der Auth-Code (CONTACTAUTH) bleibt draußen.
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function contactSnapshot(array $row): array {
        $snapshot = [];
        foreach ([
            'title' => ['contacttitle', 'title'], 'first_name' => ['contactfirstname', 'firstname'], 'middle_name' => ['contactmiddlename', 'middlename'],
            'last_name' => ['contactlastname', 'lastname'], 'name' => ['contactname', 'name'], 'organization' => ['contactorganization', 'organization', 'org'],
            'street' => ['contactstreet', 'street'], 'zip' => ['contactzip', 'zip'], 'city' => ['contactcity', 'city'], 'state' => ['contactstate', 'state'],
            'country' => ['contactcountry', 'country'], 'phone' => ['contactphone', 'phone'], 'fax' => ['contactfax', 'fax'], 'email' => ['contactemail', 'email'],
            'type' => ['contacttype', 'type'], 'status' => ['contactstatus', 'status'], 'verified' => ['verified'], 'email_verified' => ['emailverified'],
            'address_validated' => ['addressvalidated'], 'verification_date' => ['verificationdate'], 'repository' => ['contactrepository', 'repository'],
            'contact_id' => ['contactid', 'roid'], 'created_at' => ['contactcreateddate', 'createddate'], 'updated_at' => ['contactupdateddate', 'updateddate'],
        ] as $key => $candidates) {
            $value = $this->field($row, $candidates);
            if ($value !== null) {
                $snapshot[$key] = $value;
            }
        }

        return $snapshot;
    }

    /**
     * Spalten, die nur gesetzt werden, wenn der Anbieter sie liefert — die Liste darf per
     * StatusDomain gefüllte Werte nicht auf null setzen. Live-Vertrag 2026-10-29: Liste und
     * Detail nennen dieselben Daten, die Liste mit DOMAIN-Präfix.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private static function optionalColumns(array $row): array {
        $values = [];
        foreach ([
            'accounting_at' => ['paiddate', 'accountingdate', 'domainaccountingdate'],
            'paid_until_at' => ['paiduntildate', 'domainpaiduntildate'],
            'failure_at' => ['failuredate', 'domainfailuredate'],
            'finalization_at' => ['finalizationdate', 'domainfinalizationdate'],
            'registration_at' => ['registrationdate', 'createddate', 'domaincreateddate'],
            'next_action_at' => ['nextactiondate', 'domainnextactiondate'],
            'provider_updated_at' => ['updateddate', 'domainupdateddate'],
        ] as $column => $keys) {
            $raw = self::providerField($row, $keys);
            if ($raw === null) {
                continue;
            }
            try {
                $values[$column] = Carbon::parse($raw, 'UTC');
            } catch (\Throwable) {
                // unlesbares Datum → Spalte unverändert
            }
        }
        $nextAction = self::providerField($row, ['nextaction', 'domainnextaction']);
        if ($nextAction !== null) {
            $values['next_action'] = $nextAction;
        }
        $period = self::providerField($row, ['renewalperiod', 'domainrenewalperiod']);
        if ($period !== null && is_numeric($period)) {
            $values['renewal_period'] = (int) $period;
        }

        return $values;
    }

    /**
     * Technische Angaben des Anbieters als JSON — nur bekannte Schlüssel, nie Auth-Codes
     * (DOMAINAUTH) oder andere Geheimnisse. Mehrwertige NAMESERVER kommen aus der Antwort.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function providerDetails(array $row, ?DomainResponse $response = null): array {
        $details = [];
        $nameservers = $response !== null ? array_values(array_filter(array_map('trim', $response->property('nameserver')))) : [];
        foreach (['nameserver0', 'nameserver1', 'nameserver2', 'nameserver3', 'nameserver4', 'nameserver5'] as $key) {
            $value = $this->field($row, [$key]);
            if ($value !== null) {
                $nameservers[] = $value;
            }
        }
        if ($nameservers === [] && ($single = $this->field($row, ['nameserver'])) !== null) {
            $nameservers[] = $single;
        }
        if ($nameservers !== []) {
            $details['nameservers'] = array_values(array_unique($nameservers));
        }
        foreach ([
            'admin_contact' => ['admincontact', 'domainadmincontact', 'admincontact0'],
            'tech_contact' => ['techcontact', 'domaintechcontact', 'techcontact0'],
            'billing_contact' => ['billingcontact', 'domainbillingcontact', 'billingcontact0'],
            'dnssec' => ['dnssec'],
            'idn' => ['idndomain', 'domainidn'],
            'whois_privacy' => ['wps', 'xwhoisprivacy', 'whoisprivacy'],
            'subclass' => ['domainsubclass', 'subclass'],
            'transfer_mode' => ['transfermode', 'domaintransfermode'],
            'rgp_status' => ['rgpstatus'],
            'repository' => ['domainrepository', 'repository'],
            'created_by' => ['createdby', 'domaincreatedby'],
            'updated_by' => ['updatedby', 'domainupdatedby'],
            'comment' => ['comment', 'domaincomment', 'xdomaincomment'],
            'ireg_created' => ['iregcreated'],
        ] as $key => $candidates) {
            $value = $this->field($row, $candidates);
            if ($value !== null) {
                $details[$key] = $value;
            }
        }

        return $details;
    }

    /**
     * Registrant-/Owner-Contact-Handle aus einer Domain(detail)-Zeile. Der
     * Provider benennt das Feld je nach Command unterschiedlich; keiner der
     * Namen ist garantiert vorhanden (dann null).
     *
     * @param  array<string, string>  $row
     */
    private function ownerHandle(array $row): ?string {
        return $this->field($row, ['ownercontact', 'ownercontact0', 'registrant', 'registrantcontact', 'ownerhandle']);
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $keys
     */
    private function field(array $row, array $keys): ?string {
        return self::providerField($row, $keys);
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $keys
     */
    private function boolField(array $row, array $keys, bool $default): bool {
        return self::providerBool($row, $keys, $default);
    }

    private function date(?string $value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    private function currency(?string $value): ?CurrencyCode {
        return $value !== null && $value !== '' ? CurrencyCode::tryFrom(strtoupper($value)) : null;
    }
}
