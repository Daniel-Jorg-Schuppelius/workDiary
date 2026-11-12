<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainExpiryScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain\DeadlineScans;

use App\Enums\Domain\DomainRenewalMode;
use App\Enums\Notification\NotificationEvent;
use App\Models\Domain\{DomainProjection, DomainResellerAccount};
use App\Models\Platform\Organization;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;

/**
 * Vollaudit 2026-07 (H12): ablaufende Domains und gescheiterte
 * Verlängerungen an die Admins — Feature 083 hatte keinerlei
 * Benachrichtigungen.
 *
 * `failure_at` ist das FAILUREDATE des Anbieters: der Tag, an dem die
 * Domain ohne Verlängerung in den Fehlzustand geht — ein Lebenszyklusdatum,
 * das jede gesunde Domain trägt und das in der Zukunft liegt. Gescheitert
 * ist eine Verlängerung erst, wenn dieser Tag erreicht ist und der
 * Detailabgleich kein neues Datum gebracht hat (Befund 2026-10-27: nach
 * `domain:refresh-details` meldete der Scan je Charge „Verlängerung
 * fehlgeschlagen am 07.03.2027“, obwohl beim Anbieter alles in Ordnung war).
 */
class DomainExpiryScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'domains';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $expiringDays = $options->expiringDays;

        $sent = $this->renewalCosts($dispatcher, $expiringDays) + $this->balanceRisks($dispatcher, $expiringDays);

        return $sent + $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => DomainProjection::query()
                    ->withoutGlobalScopes()
                    ->where(fn($q) => $q
                        ->where('failure_at', '<=', now())
                        ->orWhere(fn($qq) => $qq
                            ->whereNotNull('expiration_at')
                            ->whereBetween('expiration_at', [now(), now()->addDays($expiringDays)]))),
                'event' => NotificationEvent::DomainExpiring,
                'payload' => function (DomainProjection $domain): array {
                    $failed = $domain->failure_at !== null && ! $domain->failure_at->isFuture();
                    $titleKey = $failed ? 'notification.message.domain_renewal_failed_title' : 'notification.message.domain_expiring_title';

                    return [
                        'title' => (string) __($titleKey, ['domain' => (string) $domain->external_domain]),
                        'title_key' => $titleKey,
                        'title_params' => ['domain' => (string) $domain->external_domain],
                        'message' => $failed
                            ? (string) __('Verlängerung fehlgeschlagen am :date.', ['date' => $domain->failure_at->format('d.m.Y')])
                            : (string) __('Läuft ab am :date.', ['date' => $domain->expiration_at?->format('d.m.Y') ?? '—']),
                        'url' => route('domains.show', $domain),
                        'due_at' => $domain->expiration_at?->toIso8601String(),
                    ];
                },
            ],
        ]);
    }

    /**
     * Teure Verlängerung kündigt sich an (Feature 083 §Berichte, MVP-1614):
     * Preis der nächsten Abbuchung (RENEWALPRICE zum ACCOUNTINGDATE) ab dem
     * Schwellwert der Organisation; Domains, die zum FAILUREDATE gelöscht
     * werden (AUTODELETE, AUTOEXPIRE), kosten nichts mehr.
     */
    private function renewalCosts(NotificationDispatcher $dispatcher, int $days): int {
        $sent = 0;
        foreach ($this->thresholds('domain.renewal_cost_threshold') as $organizationId => $threshold) {
            $domains = DomainProjection::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->whereNotNull('renewal_price')
                ->whereBetween('accounting_at', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
                ->where(fn ($q) => $q->whereNull('renewal_mode')->orWhereNotIn('renewal_mode', [DomainRenewalMode::Autodelete->value, DomainRenewalMode::Autoexpire->value]));
            foreach ($domains->lazyById(200) as $domain) {
                $currency = $domain->renewal_currency ?? CurrencyCode::Euro;
                $price = Money::of((string) $domain->renewal_price, $currency);
                if ($price->lessThan(Money::of($threshold, $currency))) {
                    continue;
                }
                $params = ['domain' => (string) $domain->external_domain, 'amount' => $price->format()];
                $sent += $dispatcher->notify(NotificationEvent::DomainRenewalCostHigh, $domain, null, [
                    'title' => (string) __('notification.message.domain_renewal_cost_title', $params),
                    'title_key' => 'notification.message.domain_renewal_cost_title',
                    'title_params' => $params,
                    'message' => (string) __('notification.message.domain_renewal_cost_body', ['date' => $domain->accounting_at?->format('d.m.Y') ?? '—', 'amount' => $price->format()]),
                    'url' => route('domains.show', $domain),
                    'due_at' => $domain->accounting_at?->toIso8601String(),
                    // Dedup-Stufe je Abbuchungstermin (Spalte `stage` fasst 16 Zeichen).
                ], 'renew:' . $domain->accounting_at?->format('Ymd'), dedup: true);
            }
        }

        return $sent;
    }

    /**
     * Fehlende Deckung (MVP-1614): Guthaben (ACCOUNTCURRENT) plus Kreditrahmen
     * (ACCOUNTCREDIT) eines Anbieterkontos reichen nicht für die anstehenden
     * Verlängerungen seiner Domains — der Anbieter löscht dann zum
     * FAILUREDATE (Handbuch 2.3.2.5). Mit Schwellwert warnt zusätzlich ein
     * Guthaben darunter. Wöchentlich höchstens eine Meldung je Konto.
     */
    private function balanceRisks(NotificationDispatcher $dispatcher, int $days): int {
        $thresholds = $this->thresholds('domain.balance_threshold');
        $sent = 0;
        $accounts = DomainResellerAccount::query()->withoutGlobalScopes()->whereNotNull('balance_snapshot')->where('active', true);
        foreach ($accounts->lazyById(200) as $account) {
            if (! $this->moduleActiveFor($account->organization_id)) {
                continue;
            }
            $currency = $account->currency ?? CurrencyCode::Euro;
            $balance = Money::of((string) $account->balance_snapshot, $currency);
            $available = $balance->plus(Money::of((string) ($account->credit_snapshot ?? '0'), $currency));
            $due = Money::sum(DomainProjection::query()->withoutGlobalScopes()
                ->where('reseller_account_id', $account->id)
                ->whereNotNull('renewal_price')
                ->where(fn ($q) => $q->whereNull('renewal_currency')->orWhere('renewal_currency', $currency->value))
                ->whereBetween('accounting_at', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
                ->where(fn ($q) => $q->whereNull('renewal_mode')->orWhereNotIn('renewal_mode', [DomainRenewalMode::Autodelete->value, DomainRenewalMode::Autoexpire->value]))
                ->pluck('renewal_price')
                ->map(fn ($price): Money => Money::of((string) $price, $currency)), $currency);

            $threshold = $thresholds[(int) $account->organization_id] ?? null;
            $uncovered = $due->isPositive() && $available->lessThan($due);
            $low = $threshold !== null && $balance->lessThan(Money::of($threshold, $currency));
            if (! $uncovered && ! $low) {
                continue;
            }
            $params = ['account' => (string) $account->external_user];
            $sent += $dispatcher->notify(NotificationEvent::DomainBalanceRisk, $account, null, [
                'title' => (string) __('notification.message.domain_balance_risk_title', $params),
                'title_key' => 'notification.message.domain_balance_risk_title',
                'title_params' => $params,
                'message' => (string) __($uncovered ? 'notification.message.domain_balance_uncovered' : 'notification.message.domain_balance_low', [
                    'balance' => $balance->format(),
                    'available' => $available->format(),
                    'due' => $due->format(),
                    'days' => $days,
                ]),
                'url' => route('domain-reseller.show', $account),
            ], 'bal:' . now()->format('o\\WW'), dedup: true);
        }

        return $sent;
    }

    /**
     * Schwellwerte je Organisation mit aktivem Modul; leer oder 0 = aus.
     *
     * @return array<int, string>
     */
    private function thresholds(string $key): array {
        $thresholds = [];
        foreach (Organization::query()->get(['id', 'settings']) as $organization) {
            $value = NumberHelper::normalizeDecimalStringOrNull((string) data_get((array) $organization->settings, $key, ''));
            // Über die ID: die Modulprüfung braucht den vollständigen Datensatz (Tarif), nicht die Teilauswahl.
            if ($value !== null && (float) $value > 0 && $this->moduleActiveFor((int) $organization->id)) {
                $thresholds[(int) $organization->id] = $value;
            }
        }

        return $thresholds;
    }
}
