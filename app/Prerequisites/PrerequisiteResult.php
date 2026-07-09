<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrerequisiteResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Prerequisites;

use Illuminate\Support\Facades\{Gate, Route};

/**
 * Ergebnis einer Voraussetzungs-Prüfung (Feature 067, MVP-181):
 * Zustand + verständliche Meldung + optionaler Setup-CTA. Der CTA
 * erscheint nur, wenn der Betrachter die Ziel-Aktion überhaupt darf
 * (sonst Hinweis auf die zuständige Rolle) — Konfigurationsdetails
 * bleiben für nicht Berechtigte verborgen.
 */
final readonly class PrerequisiteResult {
    /** @param array<string, mixed> $messageParams */
    public function __construct(
        public PrerequisiteState $state,
        public string $messageKey,
        public array $messageParams = [],
        public ?string $ctaRoute = null,
        public ?string $ctaLabelKey = null,
        public ?string $ctaPermission = null,
        public ?string $responsibleRoleKey = null,
    ) {}

    public static function ready(): self {
        return new self(PrerequisiteState::Ready, '');
    }

    public function message(): string {
        return (string) __($this->messageKey, $this->messageParams);
    }

    public function ctaVisible(): bool {
        if ($this->ctaRoute === null || !Route::has($this->ctaRoute)) {
            return false;
        }

        return $this->ctaPermission === null || Gate::allows($this->ctaPermission);
    }

    public function ctaUrl(): ?string {
        if ($this->ctaRoute === null || !$this->ctaVisible()) {
            return null;
        }

        return route($this->ctaRoute);
    }
}
