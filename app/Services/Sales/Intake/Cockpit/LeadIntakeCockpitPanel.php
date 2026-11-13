<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Cockpit;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Sales\Intake\{IntakePackages, LeadIntakeService};
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Aufnahmeakte am Cockpit (Feature 200, MVP-1641): am Lead die neueste
 * Fassung mit Stufe, Paketen und Entscheidung — oder „Aufnahme öffnen“; am
 * Kunden die Akten seiner Leads (nach der Umwandlung zeigt das
 * Kunden-Cockpit die Akte über den Lead) samt Ende der Geschäftsbeziehung,
 * dem Anker der Aufbewahrung. Ohne Leserecht, ohne Akte und ohne
 * eingeschaltetes Paket bleibt das Panel weg.
 */
final class LeadIntakeCockpitPanel implements CockpitPanel {
    public function __construct(
        private readonly LeadIntakeService $intakes,
        private readonly IntakePackages $packages,
    ) {}

    public function order(): int {
        return 22;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Lead || $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! Gate::forUser($viewer)->allows('viewAny', LeadIntake::class) || (int) $viewer->organization_id !== (int) $subject->getAttribute('organization_id')) {
            return null;
        }
        $organization = Organization::query()->find($subject->getAttribute('organization_id'));
        if (! $organization instanceof Organization) {
            return null;
        }

        if ($subject instanceof Lead) {
            $intake = $this->intakes->current($subject);
            $enabled = $this->packages->enabled($organization);
            if ($intake === null && $enabled === []) {
                return null;
            }

            return view('leads.intakes._cockpit_panel', [
                'lead' => $subject,
                'customer' => null,
                'intakes' => $intake !== null ? collect([$intake->loadMissing('lead')]) : collect(),
                'enabled' => $enabled,
                'canOpen' => $intake === null && Gate::forUser($viewer)->allows('create', [LeadIntake::class, $subject]),
                'canEndRelationship' => false,
            ]);
        }

        /** @var Customer $subject */
        $intakes = LeadIntake::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(static fn ($q) => $q->where('customer_id', $subject->id)
                ->orWhereIn('lead_id', Lead::query()->withoutGlobalScopes()->where('organization_id', $subject->organization_id)->where('customer_id', $subject->id)->select('id')))
            ->whereNull('superseded_at')
            ->with('lead')
            ->orderByDesc('id')
            ->get();
        if ($intakes->isEmpty()) {
            return null;
        }

        return view('leads.intakes._cockpit_panel', [
            'lead' => null,
            'customer' => $subject,
            'intakes' => $intakes,
            'enabled' => [],
            'canOpen' => false,
            'canEndRelationship' => Gate::forUser($viewer)->allows('endRelationship', LeadIntake::class),
        ]);
    }
}
