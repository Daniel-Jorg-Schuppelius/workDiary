<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationInboxWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\{IntegrationInboxItem, User};
use Illuminate\Contracts\View\View;

/**
 * Importe, die noch niemand zugeordnet hat — je Quelle gezählt. Gleiches
 * Gate wie die Zuordnungs-Inbox selbst (Abrechnungs-Verwaltung).
 */
class IntegrationInboxWidget extends Widget {
    public function key(): string {
        return 'integration-inbox';
    }

    public function label(): string {
        return (string) __('Zuordnungs-Inbox');
    }

    public function icon(): string {
        return 'inbox';
    }

    public function defaultOrder(): int {
        return 161;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Operations;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.integration_inbox.description');
    }

    public function availableFor(User $user): bool {
        return $user->canManageBilling();
    }

    public function render(User $user): View|string {
        $perPlugin = IntegrationInboxItem::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->selectRaw('plugin_id, COUNT(*) as cnt')
            ->groupBy('plugin_id')
            ->orderByDesc('cnt')
            ->limit(6)
            ->get();

        return view('dashboard.widgets.integration-inbox', [
            'perPlugin' => $perPlugin,
            'total' => (int) $perPlugin->sum('cnt'),
        ]);
    }
}
