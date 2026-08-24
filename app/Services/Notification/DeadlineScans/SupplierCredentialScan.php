<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Supplier\SupplierCredential;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Pflichtnachweise von Subunternehmern (Feature 117, MVP-606).
 *
 * `due` = läuft im Vorlauf ab, `overdue` = bereits abgelaufen. Der zweite
 * Fall ist der gefährliche: Das Dokument ist da, sieht vollständig aus und
 * trägt trotzdem nicht mehr.
 */
class SupplierCredentialScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'supplier_credentials';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $expiringDays = $options->expiringDays;
        $withDate = static fn () => SupplierCredential::query()->whereNotNull('valid_until');

        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn () => $withDate()
                    ->whereDate('valid_until', '>=', $today->toDateString())
                    ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays)->toDateString()),
                'event' => NotificationEvent::SupplierCredentialExpiring,
                'payload' => fn (SupplierCredential $credential): array => $this->credentialPayload($credential, 'supplier_credential_expiring'),
            ],
            'overdue' => [
                'query' => fn () => $withDate()->whereDate('valid_until', '<', $today->toDateString()),
                'event' => NotificationEvent::SupplierCredentialExpiring,
                'payload' => fn (SupplierCredential $credential): array => $this->credentialPayload($credential, 'supplier_credential_expired'),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function credentialPayload(SupplierCredential $credential, string $messageKey): array {
        $params = [
            'supplier' => (string) ($credential->supplier?->displayLabel() ?? '–'),
            'type' => (string) ($credential->type->name ?? '–'),
            'date' => $credential->valid_until?->format('d.m.Y') ?? '–',
        ];

        return [
            'title' => (string) __('notification.message.supplier_credential_title', ['supplier' => $params['supplier']]),
            'title_key' => 'notification.message.supplier_credential_title',
            'title_params' => ['supplier' => $params['supplier']],
            'message' => (string) __('notification.message.' . $messageKey, $params),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => $params + ['date' => $credential->valid_until?->toDateString() ?? '–'],
            'url' => route('suppliers.credentials.index'),
            'due_at' => $credential->valid_until,
        ];
    }
}
