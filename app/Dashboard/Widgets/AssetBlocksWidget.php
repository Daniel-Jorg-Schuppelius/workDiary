<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetBlocksWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\{AssetBlock, User};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Objekte, die aktuell gesperrt sind (D12) — etwa wegen überfälliger Prüfung.
 * Für die Disposition der wichtigere Blick als der Prüfkalender selbst.
 */
class AssetBlocksWidget extends Widget {
    public function key(): string {
        return 'asset-blocks';
    }

    public function label(): string {
        return (string) __('Gesperrte Objekte');
    }

    public function icon(): string {
        return 'block';
    }

    public function defaultOrder(): int {
        return 172;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Deadlines;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.asset_blocks.description');
    }

    public function requiredModule(): ?string {
        return 'module.asset_compliance';
    }

    public function availableFor(User $user): bool {
        return parent::availableFor($user)
            && Gate::forUser($user)->allows(Permission::AssetComplianceViewAny->value);
    }

    public function render(User $user): View|string {
        $query = AssetBlock::query()->active();

        return view('dashboard.widgets.asset-blocks', [
            'count' => (clone $query)->count(),
            'blocks' => $query->with(['asset:id,name'])->latest('blocked_from')->limit(5)->get(),
        ]);
    }
}
