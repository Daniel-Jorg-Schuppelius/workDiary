<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringPlans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Reselling\ResalePlanLink;
use Illuminate\Support\Collection;

/**
 * Registry der Plan-Quellen (Feature 185, MVP-1409). Singleton
 * (AppServiceProvider); Plugins registrieren ihre Quelle beim Boot. Ohne
 * Quelle gibt es keinen Plan — dann bleibt der Register-Serienlauf bzw. der
 * Entwurf von Hand der Weg.
 */
final class RecurringPlans {
    /** @var array<string, RecurringPlanSource> */
    private array $sources = [];

    public function register(RecurringPlanSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    /** @return list<RecurringPlanSource> */
    public function sources(): array {
        return array_values($this->sources);
    }

    public function hasSources(): bool {
        return $this->sources !== [];
    }

    /** Gibt es für die Organisation eine aktive Plan-Quelle? Ohne sie gibt es keinen Plan und keine Plan-Befunde. */
    public function availableFor(Organization $organization): bool {
        foreach ($this->sources as $source) {
            if ($source->availableFor($organization)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, PlanTemplate> */
    public function templatesFor(Organization $organization): Collection {
        $templates = collect();
        foreach ($this->sources as $source) {
            if (! $source->availableFor($organization)) {
                continue;
            }
            $templates = $templates->merge($source->templatesFor($organization));
        }

        return $templates->values();
    }

    public function templateByKey(Organization $organization, string $key): ?PlanTemplate {
        $prefix = strstr($key, ':', true);
        $source = $prefix !== false ? ($this->sources[$prefix] ?? null) : null;
        if ($source !== null) {
            return $source->templateByKey($organization, $key);
        }
        foreach ($this->sources as $candidate) {
            $template = $candidate->templateByKey($organization, $key);
            if ($template !== null) {
                return $template;
            }
        }

        return null;
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        foreach ($this->sources as $source) {
            if ($source->coversRecipient($organization, $recipient)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Abos, deren Abrechnung eine laufende Serienvorlage übernimmt — der
     * Rechnungsvorschlag lässt ihre Perioden aus (MVP-1411).
     *
     * @return list<int>
     */
    public function plannedSubscriptionIds(Organization $organization): array {
        if (! $this->availableFor($organization)) {
            return [];
        }
        $active = [];
        foreach ($this->templatesFor($organization) as $template) {
            if ($template->isActive()) {
                $active[$template->key] = true;
            }
        }
        if ($active === []) {
            return [];
        }

        $ids = ResalePlanLink::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('plan_key', array_keys($active))
            ->distinct()
            ->pluck('subscription_id')
            ->all();

        return array_values(array_map('intval', $ids));
    }
}
