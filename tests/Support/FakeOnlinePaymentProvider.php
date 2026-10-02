<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeOnlinePaymentProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Platform\Organization;
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{OnlinePaymentProvider, PluginCapability};
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\Request;
use RuntimeException;

/** Zahlungsanbieter für Tests (MVP-1067): merkt sich Aufträge, der Stand wird im Test gesetzt. */
final class FakeOnlinePaymentProvider extends AbstractPlugin implements OnlinePaymentProvider {
    public const ID = 'fakepay';

    /** @var list<OnlinePaymentRequest> */
    public array $requests = [];

    /** @var array<string, OnlinePaymentSnapshot> */
    public array $snapshots = [];

    public bool $failCheckout = false;

    public int $fetches = 0;

    public function name(): string {
        return 'FakePay';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return 'Test';
    }

    public function capabilities(): array {
        return [PluginCapability::OnlinePayment];
    }

    public function settingsSchema(): array {
        return [];
    }

    public function onlinePaymentProviderId(): string {
        return self::ID;
    }

    public function createCheckout(Organization $organization, OnlinePaymentRequest $request): OnlinePaymentCheckout {
        if ($this->failCheckout) {
            throw new RuntimeException('Anbieter gestört');
        }
        $this->requests[] = $request;
        $reference = 'pay_' . count($this->requests);
        $this->snapshots[$reference] = new OnlinePaymentSnapshot(OnlinePaymentStatus::Open, $request->amount);

        return new OnlinePaymentCheckout($reference, 'https://pay.example.com/' . $reference, CarbonImmutable::now()->addHour());
    }

    public function fetchPayment(Organization $organization, string $providerReference): OnlinePaymentSnapshot {
        $this->fetches++;

        return $this->snapshots[$providerReference] ?? throw new RuntimeException('unbekannt');
    }

    public function webhookReference(Request $request): ?string {
        $id = $request->input('id');

        return is_string($id) ? $id : null;
    }

    public function settle(string $reference, ?Money $fee = null, ?Money $amount = null): void {
        $open = $this->snapshots[$reference];
        $this->snapshots[$reference] = new OnlinePaymentSnapshot(OnlinePaymentStatus::Paid, $amount ?? $open->amount, CarbonImmutable::now(), $fee, null, 'card');
    }

    public function refund(string $reference, Money $refunded): void {
        $paid = $this->snapshots[$reference];
        $this->snapshots[$reference] = new OnlinePaymentSnapshot(OnlinePaymentStatus::Paid, $paid->amount, $paid->paidAt, $paid->fee, $refunded, 'card');
    }
}
