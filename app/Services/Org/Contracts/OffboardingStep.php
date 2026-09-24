<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OffboardingStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Org\Contracts;

use App\Models\Platform\User;

/**
 * Erweiterungspunkt des Austritts (Welle 4.3): ein Fachmodul meldet, was den
 * Austritt oder das Entfernen blockiert, und räumt beim Vollzug seinen Teil
 * auf. Registrierung über `Manifest::extensions()`.
 */
interface OffboardingStep {
    /** @return list<string> lesbare Sperrgründe; leer = kein Einwand */
    public function blockers(User $member): array;

    /** Beim Vollzug des Austritts (idempotent, `left_at` ist gesetzt). */
    public function onExit(User $member): void;
}
