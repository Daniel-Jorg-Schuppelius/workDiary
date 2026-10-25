<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHealth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Enums\Plugin\PluginHealthStatus;

/**
 * Ergebnis eines Plugin-Health-Checks. Wird vom Plugin selbst zurückgegeben
 * (siehe {@see \App\Plugins\Contracts\Plugin::healthCheck()}) und persistiert
 * in {@see \App\Models\Platform\PluginState::$last_health_status} / `last_health_message`.
 *
 * Drei Stufen:
 *  - ok        : Plugin kann seine Hauptfunktion erfüllen (z. B. API erreichbar)
 *  - degraded  : Plugin funktioniert, aber mit Einschränkungen (langsam, partielle Antwort)
 *  - failing   : Plugin kann seine Hauptfunktion NICHT erfüllen (Auth, Netz, Config defekt)
 */
final class PluginHealth {
    public const STATUS_OK = PluginHealthStatus::Ok->value;

    public const STATUS_DEGRADED = PluginHealthStatus::Degraded->value;

    public const STATUS_FAILING = PluginHealthStatus::Failing->value;

    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
        /** Antwortzeit des Checks in Millisekunden (sofern gemessen). */
        public readonly ?int $latencyMs = null,
        /** Optionaler stabiler Maschinen-Code (z. B. 'http_401', 'rate_limited'). */
        public readonly ?string $code = null,
    ) {
        // Eine Stufe außerhalb des Katalogs ist ein Plugin-Fehler: der Health-Dienst
        // fängt ihn ab und meldet „failing“, statt sie in plugin_states zu schreiben.
        PluginHealthStatus::from($status);
    }

    public static function ok(string $message = '', ?string $code = null): self {
        return new self(self::STATUS_OK, $message, code: $code);
    }

    public static function degraded(string $message = '', ?string $code = null): self {
        return new self(self::STATUS_DEGRADED, $message, code: $code);
    }

    public static function failing(string $message = '', ?string $code = null): self {
        return new self(self::STATUS_FAILING, $message, code: $code);
    }

    /** Kopie mit gesetzter Latenz (der Aufrufer misst die Zeit um healthCheck()). */
    public function withLatency(int $latencyMs): self {
        return new self($this->status, $this->message, $latencyMs, $this->code);
    }

    public function isOk(): bool {
        return $this->status === self::STATUS_OK;
    }

    public function isFailing(): bool {
        return $this->status === self::STATUS_FAILING;
    }

    /** @return array{status: string, message: string, latency_ms: ?int, code: ?string} */
    public function toArray(): array {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'latency_ms' => $this->latencyMs,
            'code' => $this->code,
        ];
    }
}
