<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentChainWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\DocumentChainService;
use App\Support\OrganizationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Kachel „Abzurechnen und nachzufassen“ (MVP-1057): Zähler je Schritt der Belegkette. */
class DocumentChainWidget extends Widget {
    public function key(): string {
        return 'document-chain';
    }

    public function label(): string {
        return (string) __('invoicing.chain.title');
    }

    public function icon(): string {
        return 'conversion_path';
    }

    public function defaultOrder(): int {
        return 130;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function description(): ?string {
        return (string) __('invoicing.chain.description');
    }

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows('viewAny', Invoice::class);
    }

    public function render(User $user): View|string {
        $organization = OrganizationContext::current();
        if (! $organization instanceof Organization) {
            return '';
        }

        return view('dashboard.widgets.document-chain', [
            'groups' => app(DocumentChainService::class)->groups($organization, $user, 0),
        ]);
    }
}
