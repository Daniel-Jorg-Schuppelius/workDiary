<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeExternalInvoiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Finance\{ExternalInvoiceStatus, ProviderSendState};
use App\Models\Finance\{BillingTransfer, ExternalInvoice};
use App\Services\Finance\Contracts\ExternalInvoiceProvider;
use App\Services\Finance\Dto\{ExternalInvoiceFile, ExternalInvoiceSnapshot};
use Carbon\CarbonImmutable;

/**
 * Fakturasystem für Tests des externen Wegs (Feature 196): steht hinter dem
 * Übergabeziel „Datei“, liefert einen einstellbaren Stand und ein Original.
 */
final class FakeExternalInvoiceProvider implements ExternalInvoiceProvider {
    public static string $status = 'open';

    public static ProviderSendState $sent = ProviderSendState::Unknown;

    /** @var list<array{name: string, quantity: string, unit_price: string|null, net: string}> */
    public static array $lines = [];

    public static ?string $original = null;

    public static ?string $invoiceId = 'ext-1';

    public static string $number = 'RE-2026-0042';

    public static function reset(): void {
        self::$status = 'open';
        self::$sent = ProviderSendState::Unknown;
        self::$lines = [
            ['name' => 'Wartung', 'quantity' => '1.000', 'unit_price' => '90.0000', 'net' => '90.00'],
            ['name' => 'Wartung', 'quantity' => '1.000', 'unit_price' => '90.0000', 'net' => '90.00'],
        ];
        self::$original = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
        self::$invoiceId = 'ext-1';
        self::$number = 'RE-2026-0042';
    }

    public function key(): string {
        return 'file';
    }

    public function label(): string {
        return 'Testfaktura';
    }

    public function isConfigured(?int $organizationId): bool {
        return true;
    }

    public function invoiceIdFor(BillingTransfer $transfer): ?string {
        return self::$invoiceId;
    }

    public function fetch(int $organizationId, string $externalId): ExternalInvoiceSnapshot {
        return new ExternalInvoiceSnapshot(
            externalId: $externalId,
            status: ExternalInvoiceStatus::from(self::$status),
            rawStatus: self::$status,
            number: self::$status === 'draft' ? null : self::$number,
            issuedOn: CarbonImmutable::parse('2026-10-31'),
            kind: ExternalInvoice::KIND_INVOICE,
            currency: 'EUR',
            netAmount: '180.00',
            grossAmount: '214.20',
            lines: self::$lines,
            sent: self::$sent,
        );
    }

    public function original(int $organizationId, ExternalInvoiceSnapshot $snapshot, bool $preferXml = false): ?ExternalInvoiceFile {
        return self::$original === null ? null : new ExternalInvoiceFile(self::$original, str_starts_with(self::$original, '%PDF') ? 'application/pdf' : 'application/xml', 'original');
    }

    public function candidates(int $organizationId, int $customerId): array {
        return [];
    }
}
