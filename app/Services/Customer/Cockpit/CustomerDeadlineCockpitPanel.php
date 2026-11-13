<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Cockpit;

use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Platform\{Organization, User};
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Customer\Deadlines\{CustomerDeadlineOverview, CustomerDeadlineProfileService, CustomerDeadlineRuleSets};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Kundenfristen am Kunden-Cockpit (Feature 197, MVP-1633): Fristmerkmale je
 * eingeschaltetem Regelsatz mit Dialog („wie Vorjahr“), Leistungsumfang und die
 * nächsten offenen Fristen mit Ampel. Ohne Leserecht oder ohne Regelsatz und
 * Fristen bleibt das Panel weg.
 */
final class CustomerDeadlineCockpitPanel implements CockpitPanel {
    private const int LIMIT = 8;

    public function __construct(
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly CustomerDeadlineProfileService $profiles,
        private readonly CustomerDeadlineOverview $overview,
    ) {}

    public function order(): int {
        return 25;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Customer || ! Gate::forUser($viewer)->allows('viewAny', CustomerDeadline::class)) {
            return null;
        }
        $organization = Organization::query()->find($subject->organization_id);
        if (! $organization instanceof Organization || (int) $viewer->organization_id !== (int) $organization->id) {
            return null;
        }
        $enabled = $this->ruleSets->enabled($organization);
        $query = CustomerDeadline::query()->where('organization_id', $organization->id)->where('customer_id', $subject->id)->open();
        $count = (clone $query)->count();
        if ($enabled === [] && $count === 0) {
            return null;
        }

        $profiles = [];
        foreach ($enabled as $key => $ruleSet) {
            $profiles[$key] = [
                'rule_set' => $ruleSet,
                'profile' => $this->profiles->current($subject, $key),
                'derived' => $ruleSet->derivedTraits($subject),
            ];
        }
        $today = CarbonImmutable::today();
        $warnDays = [];
        foreach (array_keys($this->ruleSets->available($organization)) as $key) {
            $warnDays[$key] = $this->ruleSets->warnDays($organization, $key);
        }

        return view('customer-deadlines._cockpit_panel', [
            'customer' => $subject,
            'profiles' => $profiles,
            'deadlines' => $query->with('responsible:id,name')->orderBy('due_on')->orderBy('id')->limit(self::LIMIT)->get(),
            'count' => $count,
            'overview' => $this->overview,
            'organization' => $organization,
            'warnDays' => $warnDays,
            'today' => $today,
            'canManage' => Gate::forUser($viewer)->allows('manage', CustomerDeadline::class),
        ]);
    }
}
