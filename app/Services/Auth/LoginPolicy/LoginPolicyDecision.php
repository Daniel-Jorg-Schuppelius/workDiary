<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyDecision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use App\Enums\Auth\LoginPolicyReason;
use Carbon\CarbonImmutable;

/**
 * Ergebnis einer Prüfung der Anmelderichtlinie (Feature 205): erlaubt oder
 * abgewiesen mit Grund, dazu die Gründe, aus denen eine Regel im Modus „nur
 * protokollieren“ abgewiesen hätte.
 */
final readonly class LoginPolicyDecision {
    /** @param list<LoginPolicyReason> $logged */
    private function __construct(
        public ?LoginPolicyReason $denied,
        public array $logged,
        public ?CarbonImmutable $nextOpening,
    ) {}

    /** @param list<LoginPolicyReason> $logged */
    public static function allow(array $logged = []): self {
        return new self(null, $logged, null);
    }

    /** @param list<LoginPolicyReason> $logged */
    public static function deny(LoginPolicyReason $reason, array $logged = [], ?CarbonImmutable $nextOpening = null): self {
        return new self($reason, $logged, $nextOpening);
    }

    public function allowed(): bool {
        return $this->denied === null;
    }

    /** Gilt der Grund — abgewiesen oder nur protokolliert? */
    public function concerns(LoginPolicyReason $reason): bool {
        return $this->denied === $reason || in_array($reason, $this->logged, true);
    }

    /** Verständliche Meldung für die abgewiesene Person (leer, wenn erlaubt). */
    public function message(): string {
        if ($this->denied === LoginPolicyReason::Hours && $this->nextOpening !== null) {
            return (string) __('login_policy.denied.hours_next', ['next' => $this->nextOpening->translatedFormat('l, d.m.Y H:i')]);
        }

        return $this->denied === null ? '' : (string) __('login_policy.denied.' . $this->denied->value);
    }
}
