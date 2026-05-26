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
    ) {}

    public static function ok(string $message = ''): self {
        return new self(self::STATUS_OK, $message);
    }

    public static function degraded(string $message = ''): self {
        return new self(self::STATUS_DEGRADED, $message);
    }

    public static function failing(string $message = ''): self {
        return new self(self::STATUS_FAILING, $message);
    }

    public function isOk(): bool {
        return $this->status === self::STATUS_OK;
    }

    public function isFailing(): bool {
        return $this->status === self::STATUS_FAILING;
    }

    /** @return array{status: string, message: string} */
    public function toArray(): array {
        return ['status' => $this->status, 'message' => $this->message];
    }
}
