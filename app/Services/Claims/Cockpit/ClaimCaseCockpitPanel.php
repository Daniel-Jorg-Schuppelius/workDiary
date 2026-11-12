<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimCaseCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Cockpit;

use App\Models\Asset\Asset;
use App\Models\Claims\ClaimCase;
use App\Models\Platform\User;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Offene Reklamationen (Feature 072) am Asset (MVP-1617) — Rechte wie die Reklamationsliste. */
final class ClaimCaseCockpitPanel implements CockpitPanel {
    private const int LIMIT = 10;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 42;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Asset;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $this->features->routeEnabled('claims.show') || ! $gate->allows('viewAny', ClaimCase::class)) {
            return null;
        }
        $claims = ClaimCase::query()
            ->where('asset_id', $subject->getKey())
            ->open()
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ClaimCase $c): bool => $gate->allows('view', $c))
            ->values();
        if ($claims->isEmpty()) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-claims',
            'title' => __('cockpit.claims.title'),
            'icon' => 'assignment_return',
            'count' => $claims->count(),
            'rows' => array_map(static fn (ClaimCase $c): array => [
                'title' => implode(' · ', array_filter([(string) $c->number, (string) $c->title])),
                'url' => route('claims.show', $c),
                'subtitle' => null,
                'badge' => $c->status->label(),
                'tone' => 'info',
                'date' => $c->due_at !== null ? CarbonFmt::fdate($c->due_at) : null,
            ], $claims->take(self::LIMIT)->all()),
        ]);
    }
}
