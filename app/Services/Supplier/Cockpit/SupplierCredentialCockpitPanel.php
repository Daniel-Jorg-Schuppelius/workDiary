<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Supplier\Cockpit;

use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Supplier\SupplierCredentialService;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Pflichtnachweise des Lieferanten (Feature 117) mit Ampel je Nachweisart — dieselbe Prüfung wie die Nachweisliste. */
final class SupplierCredentialCockpitPanel implements CockpitPanel {
    public function __construct(private readonly SupplierCredentialService $credentials) {}

    public function order(): int {
        return 30;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Supplier;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Supplier || ! Gate::forUser($viewer)->allows('viewAny', Supplier::class)) {
            return null;
        }
        $items = $this->credentials->statusFor($subject);
        if ($items->isEmpty()) {
            return null;
        }
        $overall = $this->credentials->overallStatus($subject);

        return view('cockpit._list', [
            'id' => 'cockpit-credentials',
            'title' => __('cockpit.credentials.title'),
            'icon' => 'fact_check',
            'count' => $items->count(),
            'summary' => __('cockpit.credentials.summary', ['status' => $overall->label()]),
            'rows' => $items->map(static fn (array $item): array => [
                'title' => (string) $item['type']->name,
                'url' => route('suppliers.credentials.index'),
                'subtitle' => $item['credential']?->issuer,
                'badge' => $item['status']->label(),
                'tone' => $item['status']->tone(),
                'date' => $item['credential']?->valid_until !== null ? CarbonFmt::fdate($item['credential']->valid_until) : null,
            ])->values()->all(),
        ]);
    }
}
