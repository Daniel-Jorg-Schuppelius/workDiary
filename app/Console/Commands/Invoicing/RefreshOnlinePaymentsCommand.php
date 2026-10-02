<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RefreshOnlinePaymentsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Invoicing\OnlinePayment;
use App\Services\Invoicing\OnlinePayment\OnlinePaymentService;
use Illuminate\Console\Command;

/**
 * Täglicher Abgleich der Online-Zahlungen (MVP-1067): offene Bezahlseiten des
 * Vortags und bezahlte der letzten 120 Tage beim Anbieter nachfragen — fängt
 * verlorene Webhooks und Erstattungen, die nicht jeder Anbieter meldet.
 */
class RefreshOnlinePaymentsCommand extends Command {
    private const REFUND_WINDOW_DAYS = 120;

    protected $signature = 'invoicing:online-payments-refresh';

    protected $description = 'Gleicht offene und kürzlich bezahlte Online-Zahlungen mit dem Zahlungsanbieter ab.';

    public function handle(OnlinePaymentService $payments): int {
        $count = 0;
        // TENANT-BYPASS: Lauf über alle Organisationen; refresh() bindet den Kontext je Zahlung.
        OnlinePayment::query()->withoutGlobalScopes()
            ->whereNotNull('provider_reference')
            ->where(static function ($query): void {
                $query->where(static fn ($open) => $open->where('status', OnlinePaymentStatus::Open->value)
                    ->where('created_at', '>=', now()->subDay()))
                    ->orWhere(static fn ($paid) => $paid->where('status', OnlinePaymentStatus::Paid->value)
                        ->where('paid_at', '>=', now()->subDays(self::REFUND_WINDOW_DAYS)));
            })
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($payments, &$count): void {
                foreach ($chunk as $payment) {
                    $payments->refresh($payment);
                    $count++;
                }
            });

        $this->info("Online-Zahlungen abgeglichen: {$count}.");

        return self::SUCCESS;
    }
}
