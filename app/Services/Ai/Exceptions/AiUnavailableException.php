<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiUnavailableException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use App\Services\Ai\Dto\AiCapability;

/**
 * KI ist für diesen Aufruf nicht verfügbar (Feature 025, MVP-399).
 * Der Grund-Code steuert das Verhalten der Aufrufer: alles außer
 * ALL_FAILED ist terminal (kein Queue-Retry) — KI ist nie
 * Voraussetzung eines Kernworkflows, die Fachfunktion läuft ohne
 * Vorschlag weiter.
 */
class AiUnavailableException extends AiException {
    public const REASON_MODULE_INACTIVE = 'module_inactive';
    public const REASON_CAPABILITY_DISABLED = 'capability_disabled';
    public const REASON_NO_CONNECTION = 'no_connection';
    public const REASON_CONNECTION_NOT_ALLOWED = 'connection_not_allowed';
    public const REASON_ALL_FAILED = 'all_failed';

    private function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function moduleInactive(): self {
        return new self(self::REASON_MODULE_INACTIVE, (string) __('ai.error.module_inactive'));
    }

    public static function capabilityDisabled(string $capability): self {
        return new self(
            self::REASON_CAPABILITY_DISABLED,
            (string) __('ai.error.capability_disabled', ['capability' => AiCapability::labelFor($capability)])
        );
    }

    /**
     * Keine nutzbare Verbindung. Steht der Grund an der ausgefallenen
     * Verbindung (letzter Verbindungsfehler), wird ER genannt — die reine
     * Capability-Meldung schickte sonst auf die falsche Fährte: die
     * Zuordnung war in Ordnung, kaputt war der Provider-Zugang.
     */
    public static function noConnection(string $capability, ?string $connectionName = null, ?string $connectionError = null): self {
        $message = (string) __('ai.error.no_connection', ['capability' => AiCapability::labelFor($capability)]);

        if ($connectionName !== null && $connectionError !== null && trim($connectionError) !== '') {
            $message = (string) __('ai.error.connection_unusable', [
                'connection' => $connectionName,
                'error' => trim($connectionError),
                'capability' => AiCapability::labelFor($capability),
            ]);
        }

        return new self(self::REASON_NO_CONNECTION, $message);
    }

    public static function connectionNotAllowed(int $connectionId): self {
        return new self(
            self::REASON_CONNECTION_NOT_ALLOWED,
            (string) __('ai.error.connection_not_allowed', ['id' => $connectionId])
        );
    }

    public static function allProvidersFailed(string $capability): self {
        return new self(
            self::REASON_ALL_FAILED,
            (string) __('ai.error.all_failed', ['capability' => AiCapability::labelFor($capability)])
        );
    }

    /** Nur vorübergehende Zustände rechtfertigen einen Queue-Retry. */
    public function isRetryable(): bool {
        return $this->reason === self::REASON_ALL_FAILED;
    }
}
