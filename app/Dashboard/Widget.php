<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Widget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard;

use App\Models\User;
use Illuminate\Contracts\View\View;

abstract class Widget {
    /**
     * Stable identifier used in URLs, DB rows, registry lookups.
     */
    abstract public function key(): string;

    /**
     * Human-readable label (already translated).
     */
    abstract public function label(): string;

    /**
     * Material Symbols icon name (optional).
     */
    public function icon(): string {
        return 'widgets';
    }

    /**
     * Permission string to check via Gate, or null = visible to all
     * authenticated users.
     */
    public function requiredAbility(): ?string {
        return null;
    }

    /**
     * Modul-Flag (module.*), an dem das Widget hängt, oder null = Core
     * (Feature 081, MVP-372: generisches Modul-Gating statt Einzel-Checks
     * in availableFor()-Overrides).
     */
    public function requiredModule(): ?string {
        return null;
    }

    /**
     * Returns true if the widget is allowed for the given user.
     */
    public function availableFor(User $user): bool {
        $module = $this->requiredModule();
        if ($module !== null && ! app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled($module)) {
            return false;
        }

        $ability = $this->requiredAbility();
        if ($ability === null) {
            return true;
        }

        return $user->hasEffectivePermission($ability) || $user->isAdmin();
    }

    /**
     * Render the widget as a Blade view.
     */
    abstract public function render(User $user): View|string;
}
