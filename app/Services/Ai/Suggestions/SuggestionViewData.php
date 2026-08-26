<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuggestionViewData.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiTextSuggestion};
use App\Services\Ai\{AiCapabilityRegistry, AiRoutingResolver};
use App\Services\Licensing\ModuleStatusResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * View-Daten der KI-Vorschläge (Feature 084, MVP-402): kapselt für die
 * Beleg-Views, ob eine Capability nutzbar ist (Modul aktiv + Capability
 * freigeschaltet + Nutzer hat ai.use) und liefert die offenen
 * Vorschläge je Position — ohne die Beleg-Controller anzufassen.
 */
class SuggestionViewData {
    public function __construct(
        private readonly AiCapabilityRegistry $registry,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public function capabilityUsable(string $capability): bool {
        $user = Auth::user();
        if ($user === null || ! $user->can(Permission::AiUse->value)) {
            return false;
        }

        if (! app()->bound('currentOrganization')) {
            return false;
        }
        $organization = app('currentOrganization');

        if (! $this->registry->has($capability)
            || ! $this->modules->isActiveFor($organization, AiRoutingResolver::MODULE)) {
            return false;
        }

        return (bool) AiCapabilitySetting::query()
            ->where('capability', $capability)
            ->value('enabled');
    }

    /**
     * Offene Vorschläge je Positions-ID. Mit $capability nur die Vorschläge
     * dieser Einsatzstelle — Protokollpunkte (MVP-711) tragen Text- UND
     * Klassifikationsvorschlag nebeneinander, die sonst beim keyBy kollidieren.
     *
     * @param Collection<int, covariant Model> $items
     * @return Collection<int, AiTextSuggestion>
     */
    public function openSuggestionsFor(string $subjectType, Collection $items, ?string $capability = null): Collection {
        if ($items->isEmpty()) {
            return collect();
        }

        return AiTextSuggestion::query()
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $items->map(static fn (Model $m) => $m->getKey()))
            ->where('status', AiTextSuggestion::STATUS_PROPOSED)
            ->when($capability !== null, static fn ($q) => $q->where('capability', $capability))
            ->get()
            ->keyBy('subject_id');
    }
}
