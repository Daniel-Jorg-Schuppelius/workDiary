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
use App\Plugins\DomainReselling\DomainResellingConfig;
use App\Plugins\Support\Domain\DomainResponse;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Carbon;

/**
 * Projiziert Subuser/Reseller, Domains und Registry-Kontakte des
 * DomainReselling-Kontos in WorkDiary (Feature 083, MVP-386/387).
 * DomainReselling bleibt führend; hier entstehen nur Projektionen mit
 * Revision + `raw_hash` und Aktualitätsstempel. Auth-Codes werden NICHT in
 * die Projektion übernommen.
 */
class DomainSyncService {
    public function __construct(private readonly DomainProviderResolver $resolver) {
    }

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

            DomainResellerAccount::query()->updateOrCreate(
                [
                    'organization_id' => $connection->organization_id,
                    'connection_id' => $connection->id,
                    'external_user' => $user,
                ],
                [
                    'parent_user' => $this->field($row, ['parentuser', 'parent']),
                    'depth' => (int) ($this->field($row, ['depth', 'level']) ?? 0),
                    'user_class' => $this->field($row, ['userclass', 'class']),
                    'active' => $this->boolField($row, ['active', 'status'], true),
                    'currency' => $this->currency($this->field($row, ['currency'])),
                    'balance_snapshot' => $this->decimal($this->field($row, ['balance', 'accountbalance'])),
                    'balance_at' => Carbon::now(),
                    'raw_hash' => CryptoHelper::hash(JsonHelper::encode($row)),
                    'synced_at' => Carbon::now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Paginierte Domainliste je `userdepth` (SELF|SUBUSER|ALLSUBUSER|ALL).
     * Legt/aktualisiert die Projektion mit den Listenfeldern und markiert sie
     * als aktuell; Detailfelder folgen über {@see refreshDomain()}.
     */
    public function syncDomains(DomainProviderConnection $connection, string $userDepth = 'ALL', ?DomainProviderAdapter $adapter = null): int {
        $adapter ??= $this->resolver->for($connection);
        $pageSize = DomainResellingConfig::resolve((int) $connection->organization_id)['list_page_size'];
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
        $response = $adapter->execute('QueryContactList', [], DomainCapabilityArea::Contacts);

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
                    'external_user' => $this->field($row, ['user', 'owner']),
                    // Minimierter Snapshot ohne rohe PII-Häufung.
                    'snapshot' => [
                        'organization' => $this->field($row, ['organization', 'org']),
                        'country' => $this->field($row, ['country']),
                        'type' => $this->field($row, ['type']),
                    ],
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
        $threshold = Carbon::now()->subHours(DomainResellingConfig::resolve((int) $connection->organization_id)['stale_after_hours']);

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
            'registrar' => $this->field($row, ['registrar', 'domainregistrar']),
            'status' => $this->field($row, ['status', 'domainstatus']),
            'sync_status' => DomainSyncStatus::Current->value,
            'renewal_mode' => DomainRenewalMode::fromProvider($this->field($row, ['renewalmode', 'domainrenewalmode']))?->value,
            'expiration_at' => $this->date($this->field($row, ['expirationdate', 'expiration', 'registrationexpirationdate', 'domainregistrationexpirationdate'])),
            'raw_hash' => CryptoHelper::hash(JsonHelper::encode($row)),
            'synced_at' => Carbon::now(),
        ];
        // Diese Daten liefert NUR StatusDomain — die Liste würde per Detail-
        // abgleich gefüllte Werte sonst bei jedem Sync wieder auf null setzen.
        foreach (['accounting_at' => ['paiddate', 'accountingdate'], 'failure_at' => ['failuredate'], 'finalization_at' => ['finalizationdate']] as $column => $keys) {
            $value = $this->date($this->field($row, $keys));
            if ($value !== null) {
                $values[$column] = $value;
            }
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
            'renewal_price' => $this->decimal($this->field($row, ['renewalprice', 'price'])),
            'renewal_currency' => $this->currency($this->field($row, ['currency', 'renewalcurrency']))?->value,
            'revision' => $this->field($row, ['revision', 'roid']),
            'owner_handle' => $this->ownerHandle($row) ?? $projection->owner_handle,
            'raw_hash' => $response->rawHash(),
            'synced_at' => Carbon::now(),
        ]);

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
    private function field(array $row, array $keys): ?string {
        foreach ($keys as $key) {
            $value = $row[strtolower($key)] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function boolField(array $row, array $keys, bool $default): bool {
        $value = $this->field($row, $keys);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'active'], true);
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

    private function decimal(?string $value): ?float {
        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    private function currency(?string $value): ?CurrencyCode {
        return $value !== null && $value !== '' ? CurrencyCode::tryFrom(strtoupper($value)) : null;
    }
}
