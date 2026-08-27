<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHealthWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\{PluginState, User};
use Illuminate\Contracts\View\View;

/**
 * Plugins, deren letzter Gesundheitscheck nicht in Ordnung war — je
 * Organisation, wie der Healthcheck selbst (plugin_states).
 */
class PluginHealthWidget extends Widget {
    public function key(): string {
        return 'plugin-health';
    }

    public function label(): string {
        return (string) __('Plugin-Zustand');
    }

    public function icon(): string {
        return 'extension';
    }

    public function defaultOrder(): int {
        return 163;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Operations;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.plugin_health.description');
    }

    public function availableFor(User $user): bool {
        return $user->isAdmin();
    }

    public function render(User $user): View|string {
        $states = PluginState::query()
            ->where(function ($q) use ($user): void {
                $q->whereNull('organization_id')->orWhere('organization_id', $user->organization_id);
            })
            ->orderBy('plugin_id')
            ->get();

        return view('dashboard.widgets.plugin-health', [
            'failing' => $states->filter(fn (PluginState $s): bool => $s->last_health_status !== null && $s->last_health_status !== 'ok')->values(),
            'total' => $states->count(),
        ]);
    }
}
