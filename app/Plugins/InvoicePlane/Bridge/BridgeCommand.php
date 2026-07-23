<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BridgeCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Bridge;

use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};

/**
 * Signierter Befehl an die InvoicePlane-seitige WorkDiary-Bridge (Feature 086,
 * MVP-420) — die **WorkDiary-Hälfte** des schmalen, signierten Befehlskanals.
 *
 * Jeder Befehl trägt Organisation, WorkDiary-Quelltyp/-ID, Aktion, Payload-Hash,
 * Idempotency-Key, erwarteten Schema-/Bridge-Stand und Benutzerkontext. Die
 * kanonische, signierte Darstellung enthält bewusst **nur den Payload-Hash**
 * (keine Kundenfelder/Secrets) — die eigentliche Payload wird getrennt und
 * verschlüsselt übertragen. Ein Timeout gilt als „Ergebnis unklar" →
 * Reconciliation, nie als blinder Retry (Laufzeit, nicht hier).
 */
final readonly class BridgeCommand {
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $organizationId,
        public string $sourceType,
        public string $sourceId,
        public string $action,
        public array $payload,
        public string $idempotencyKey,
        public string $expectedSchemaFingerprint,
        public string $expectedBridgeVersion,
        public ?int $userId,
    ) {}

    public function payloadHash(): string {
        return CryptoHelper::hash(JsonHelper::encode($this->payload));
    }

    /**
     * Kanonische, signierbare Darstellung — deterministisch (feste
     * Schlüsselreihenfolge), ohne Payload-Klartext/Secrets.
     *
     * @return array<string, int|string|null>
     */
    public function canonical(): array {
        return [
            'organization_id' => $this->organizationId,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'action' => $this->action,
            'payload_hash' => $this->payloadHash(),
            'idempotency_key' => $this->idempotencyKey,
            'expected_schema_fingerprint' => $this->expectedSchemaFingerprint,
            'expected_bridge_version' => $this->expectedBridgeVersion,
            'user_id' => $this->userId,
        ];
    }

    /**
     * HMAC-SHA256 über die kanonische Darstellung mit dem je Organisation
     * verschlüsselt hinterlegten Bridge-Secret.
     */
    public function sign(string $secret): string {
        return hash_hmac('sha256', JsonHelper::encode($this->canonical()), $secret);
    }

    public function verify(string $signature, string $secret): bool {
        return hash_equals($this->sign($secret), $signature);
    }
}
