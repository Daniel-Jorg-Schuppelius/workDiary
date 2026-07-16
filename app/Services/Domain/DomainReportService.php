<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\{DomainRenewalMode, DomainSyncStatus};
use App\Models\Domain\{DomainAccountingEntry, DomainExternalInvoice, DomainProjection, DomainProviderConnection};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Support\{Carbon, Collection};

/**
 * Berichte des Domain-Moduls (Feature 083, MVP-395): Ablauf-/Renewal-Vorschau,
 * Renewal-Kostenprognose, fehlende Kundenzuordnung, Risiken (Autoexpire/
 * Autodelete/Failure), Sync-/Reconciliation-Zustand, Rechnungsabdeckung und
 * API-Health.
 */
class DomainReportService {
    /**
     * Ablauf-/Renewal-Vorschau je Korridor (30/60/90/180 Tage).
     *
     * @return array<int, int>
     */
    public function expiryCorridors(int $organizationId): array {
        $out = [];
        foreach ([30, 60, 90, 180] as $days) {
            $out[$days] = DomainProjection::query()
                ->where('organization_id', $organizationId)
                ->whereNotNull('expiration_at')
                ->whereBetween('expiration_at', [Carbon::now(), Carbon::now()->addDays($days)])
                ->count();
        }

        return $out;
    }

    /**
     * @return Collection<int, DomainProjection>
     */
    public function expiringWithin(int $organizationId, int $days): Collection {
        return DomainProjection::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('expiration_at')
            ->where('expiration_at', '<=', Carbon::now()->addDays($days))
            ->orderBy('expiration_at')
            ->get();
    }

    /**
     * Renewal-Kostenprognose je Kunde/Währung/Monat (nächste `days` Tage).
     *
     * @return array<string, array{count: int, amount: float, currency: string}>
     */
    public function renewalCostForecast(int $organizationId, int $days = 180): array {
        $rows = DomainProjection::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('renewal_price')
            ->whereNotNull('expiration_at')
            ->whereBetween('expiration_at', [Carbon::now(), Carbon::now()->addDays($days)])
            ->get();

        $forecast = [];
        foreach ($rows as $domain) {
            $currency = $domain->renewal_currency instanceof CurrencyCode ? $domain->renewal_currency->value : 'EUR';
            $month = $domain->expiration_at?->format('Y-m') ?? 'unbekannt';
            $key = $month . '|' . $currency;
            $forecast[$key] ??= ['count' => 0, 'amount' => 0.0, 'currency' => $currency];
            $forecast[$key]['count']++;
            $forecast[$key]['amount'] += (float) $domain->renewal_price;
        }

        return $forecast;
    }

    /**
     * Domains ohne Kundenzuordnung (auch nicht über einen zugeordneten Subuser).
     *
     * @return Collection<int, DomainProjection>
     */
    public function unmapped(int $organizationId): Collection {
        return DomainProjection::query()
            ->where('organization_id', $organizationId)
            ->whereNull('customer_id')
            ->where(function ($q): void {
                $q->whereNull('reseller_account_id')
                    ->orWhereDoesntHave('resellerAccount', fn ($r) => $r->whereNotNull('customer_id'));
            })
            ->orderBy('external_domain')
            ->get();
    }

    /**
     * Autoexpire-/Autodelete-Risiken.
     *
     * @return Collection<int, DomainProjection>
     */
    public function riskyRenewalModes(int $organizationId): Collection {
        return DomainProjection::query()
            ->where('organization_id', $organizationId)
            ->whereIn('renewal_mode', [DomainRenewalMode::Autoexpire->value, DomainRenewalMode::Autodelete->value])
            ->orderBy('expiration_at')
            ->get();
    }

    public function reconciliationCount(int $organizationId): int {
        return DomainProjection::query()
            ->where('organization_id', $organizationId)
            ->whereIn('sync_status', [DomainSyncStatus::Conflict->value, DomainSyncStatus::Unknown->value])
            ->count();
    }

    /**
     * Rechnungsabdeckung: belegte externe Rechnungen vs. reine Accounting-Zeilen.
     *
     * @return array{accounting: int, invoices: int}
     */
    public function invoiceCoverage(int $organizationId): array {
        return [
            'accounting' => DomainAccountingEntry::query()->where('organization_id', $organizationId)->count(),
            'invoices' => DomainExternalInvoice::query()->where('organization_id', $organizationId)->count(),
        ];
    }

    /**
     * @return Collection<int, DomainProviderConnection>
     */
    public function connectionHealth(int $organizationId): Collection {
        return DomainProviderConnection::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();
    }
}
