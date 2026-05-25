<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WidgetRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard;

use App\Models\User;
use Illuminate\Support\Collection;

class WidgetRegistry {
    /** @var array<string, Widget> */
    private array $widgets = [];

    public function register(Widget $widget): void {
        $this->widgets[$widget->key()] = $widget;
    }

    /** @return Collection<string, Widget> */
    public function all(): Collection {
        return collect($this->widgets);
    }

    public function find(string $key): ?Widget {
        return $this->widgets[$key] ?? null;
    }

    /** @return Collection<string, Widget> */
    public function availableFor(User $user): Collection {
        return $this->all()->filter(fn (Widget $w) => $w->availableFor($user));
    }
}
