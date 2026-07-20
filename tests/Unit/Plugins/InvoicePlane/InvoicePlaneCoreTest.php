<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\InvoicePlane;

use App\Plugins\InvoicePlane\Bridge\BridgeCommand;
use App\Plugins\InvoicePlane\{InvoicePlaneConnectionException, InvoicePlaneConnectionGuard, InvoicePlanePreflight};
use App\Plugins\InvoicePlane\Schema\SchemaReader;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * MVP-418/419/420 — ohne Pilotinstanz baubarer/testbarer Kern des
 * InvoicePlane-Plugins: Verbindungs-Guard, Schema-Preflight, Bridge-Envelope.
 */
class InvoicePlaneCoreTest extends TestCase {
    // ── MVP-419: Verbindungs-Guard ───────────────────────────────────────────

    private function guard(): InvoicePlaneConnectionGuard {
        return new InvoicePlaneConnectionGuard(['8.8.8.8'], requireTls: true);
    }

    public function test_private_host_is_allowed_without_allowlist_or_tls(): void {
        $this->guard()->assertAcceptable('10.20.30.40', false);
        $this->guard()->assertAcceptable('127.0.0.1', false);
        $this->assertTrue(true); // keine Exception
    }

    public function test_public_host_requires_allowlist(): void {
        $this->expectException(InvoicePlaneConnectionException::class);
        $this->guard()->assertAcceptable('1.1.1.1', true); // public, nicht gelistet
    }

    public function test_allowlisted_public_host_requires_tls(): void {
        $this->assertTrue($this->guard()->isAcceptable('8.8.8.8', true));
        $this->assertFalse($this->guard()->isAcceptable('8.8.8.8', false)); // TLS fehlt
    }

    // ── MVP-420: signierter Bridge-Befehl ────────────────────────────────────

    private function command(): BridgeCommand {
        return new BridgeCommand(
            organizationId: 7,
            sourceType: 'quote',
            sourceId: 'Q-42',
            action: 'create_quote',
            payload: ['client' => 'ACME', 'total' => '119.00'],
            idempotencyKey: 'idem-1',
            expectedSchemaFingerprint: 'fp-abc',
            expectedBridgeVersion: '1.0.0',
            userId: 3,
        );
    }

    public function test_command_sign_and_verify_roundtrip(): void {
        $cmd = $this->command();
        $sig = $cmd->sign('secret-xyz');

        $this->assertTrue($cmd->verify($sig, 'secret-xyz'));
        $this->assertFalse($cmd->verify($sig, 'other-secret'));
        $this->assertFalse($cmd->verify('tampered', 'secret-xyz'));
    }

    public function test_payload_change_invalidates_signature(): void {
        $sig = $this->command()->sign('secret-xyz');

        $tampered = new BridgeCommand(7, 'quote', 'Q-42', 'create_quote', ['client' => 'ACME', 'total' => '999.00'], 'idem-1', 'fp-abc', '1.0.0', 3);
        $this->assertFalse($tampered->verify($sig, 'secret-xyz'));
    }

    // ── MVP-419: Schema-Preflight ────────────────────────────────────────────

    /**
     * @param  array<string, list<string>>  $columns
     */
    private function reader(?string $version, array $columns, ?DateTimeImmutable $serverTime = null): SchemaReader {
        return new class($version, $columns, $serverTime) implements SchemaReader {
            /** @param array<string, list<string>> $columns */
            public function __construct(private ?string $version, private array $columns, private ?DateTimeImmutable $serverTime) {}

            public function version(): ?string {
                return $this->version;
            }

            public function tablePrefix(): string {
                return 'ip_';
            }

            public function charset(): ?string {
                return 'utf8mb4';
            }

            public function serverTime(): ?DateTimeImmutable {
                return $this->serverTime;
            }

            public function columnsOf(string $tableWithoutPrefix): array {
                return $this->columns[$tableWithoutPrefix] ?? [];
            }
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private function fullSchema(): array {
        /** @var array<string, list<string>> $required */
        $required = (array) (config('invoiceplane.versions')['v1']['required_columns'] ?? []);

        return $required;
    }

    public function test_supported_version_with_full_schema_passes(): void {
        $result = InvoicePlanePreflight::fromConfig()->run($this->reader('1.0.4', $this->fullSchema()));

        $this->assertTrue($result->ok);
        $this->assertSame('v1', $result->versionKey);
        $this->assertNotSame('', $result->fingerprint);
    }

    public function test_v2_is_blocked(): void {
        $result = InvoicePlanePreflight::fromConfig()->run($this->reader('2.0.0', $this->fullSchema()));

        $this->assertTrue($result->isBlocked());
        $this->assertSame('v2', $result->versionKey);
    }

    public function test_unknown_version_is_blocked(): void {
        $result = InvoicePlanePreflight::fromConfig()->run($this->reader('0.9.9', []));

        $this->assertTrue($result->isBlocked());
        $this->assertNull($result->versionKey);
    }

    public function test_missing_required_column_blocks(): void {
        $schema = $this->fullSchema();
        $schema['invoices'] = ['invoice_id']; // Pflichtspalten fehlen

        $result = InvoicePlanePreflight::fromConfig()->run($this->reader('1.0.4', $schema));

        $this->assertTrue($result->isBlocked());
        $this->assertNotEmpty($result->reasons);
    }

    public function test_fingerprint_is_stable_for_same_schema(): void {
        $a = InvoicePlanePreflight::fromConfig()->run($this->reader('1.0.4', $this->fullSchema()));
        $b = InvoicePlanePreflight::fromConfig()->run($this->reader('1.0.4', $this->fullSchema()));

        $this->assertSame($a->fingerprint, $b->fingerprint);
    }

    public function test_clock_drift_blocks(): void {
        $now = 1_700_000_000;
        $drifted = new DateTimeImmutable('@' . ($now + 10_000));

        $result = InvoicePlanePreflight::fromConfig()->run($this->reader('1.0.4', $this->fullSchema(), $drifted), $now);

        $this->assertTrue($result->isBlocked());
    }
}
