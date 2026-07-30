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

use Throwable;

/**
 * Ergebnis eines Plugin-Health-Checks. Wird vom Plugin selbst zurückgegeben
 * (siehe {@see \App\Plugins\Contracts\Plugin::healthCheck()}) und persistiert
 * in {@see \App\Models\PluginState::$last_health_status} / `last_health_message`.
 *
 * Drei Stufen:
 *  - ok        : Plugin kann seine Hauptfunktion erfüllen (z. B. API erreichbar)
 *  - degraded  : Plugin funktioniert, aber mit Einschränkungen (langsam, partielle Antwort)
 *  - failing   : Plugin kann seine Hauptfunktion NICHT erfüllen (Auth, Netz, Config defekt)
 */
final class PluginHealth {
    public const STATUS_OK = 'ok';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_FAILING = 'failing';

    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
        /** Antwortzeit des Checks in Millisekunden (sofern gemessen). */
        public readonly ?int $latencyMs = null,
        /** Optionaler stabiler Maschinen-Code (z. B. 'http_401', 'rate_limited'). */
        public readonly ?string $code = null,
    ) {}

    public static function ok(string $message = '', ?string $code = null): self {
        return new self(self::STATUS_OK, $message, code: $code);
    }

    public static function degraded(string $message = '', ?string $code = null): self {
        return new self(self::STATUS_DEGRADED, $message, code: $code);
    }

    public static function failing(string $message = '', ?string $code = null): self {
        return new self(self::STATUS_FAILING, $message, code: $code);
    }

    /**
     * Gemeinsamer Rahmen der Ping-basierten Health-Checks (W3d):
     * Konfigurations-Guard, try/catch und Message-Mapping einmal — die
     * semantischen Unterschiede (CSV-Modus = ok vs. degraded, degraded vs.
     * failing) bleiben Parameter beim Plugin.
     *
     * @param  callable(): bool  $ping  Verbindungscheck; wirft bei Transport-/API-Fehlern
     * @param  string  $unreachableMessage  Meldung, wenn der Ping false liefert
     * @param  bool  $configured  false = Client unkonfiguriert → sofortiges Ergebnis mit $notConfiguredStatus (kein Ping)
     * @param  class-string<Throwable>|null  $apiExceptionClass  Provider-Exception, deren Message unverändert übernommen wird
     * @param  (callable(Throwable): string)|null  $throwableMessage  Message-Mapping übriger Ausnahmen (null = getMessage())
     */
    public static function pingHealth(
        callable $ping,
        string $unreachableMessage,
        bool $configured = true,
        string $notConfiguredMessage = '',
        string $notConfiguredStatus = self::STATUS_OK,
        string $errorStatus = self::STATUS_DEGRADED,
        string $okMessage = '',
        ?string $apiExceptionClass = null,
        ?callable $throwableMessage = null,
    ): self {
        if (! $configured) {
            return new self($notConfiguredStatus, $notConfiguredMessage);
        }

        try {
            return $ping()
                ? self::ok($okMessage)
                : new self($errorStatus, $unreachableMessage);
        } catch (Throwable $e) {
            // Provider-Exceptions tragen ihre Meldung selbst; alles andere läuft
            // durch das optionale Mapping (z. B. „<API> nicht erreichbar: …").
            $message = ($apiExceptionClass !== null && $e instanceof $apiExceptionClass) || $throwableMessage === null
                ? $e->getMessage()
                : $throwableMessage($e);

            return new self($errorStatus, $message);
        }
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
