<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingStepState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Platform;

use App\Enums\Contracts\HasLabel;

/**
 * Stand eines Einrichtungsschritts. Ohne Übergangstabelle: die Checkliste
 * leitet „erledigt“ und „offen“ bei jedem Aufruf aus dem Datenbestand ab,
 * übersprungen wird von Hand aus jedem Stand.
 */
enum OnboardingStepState: string implements HasLabel {
    case Open = 'open';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string {
        return (string) __('onboarding.page.badge_' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Done => 'success',
            self::Skipped => 'ghost',
            self::Open => 'warning',
        };
    }
}
