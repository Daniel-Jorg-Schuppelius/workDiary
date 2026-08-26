<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeExport;

use App\Models\{CostCenter, CostCenterRule, Expense};
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Löst die Kostenstelle eines Users für den Zeitexport auf (Rang 35 —
 * rescoped): Benutzer-Regel > Team-Regel (höchste Priorität, dann kleinste
 * id) > Org-Default-Regel (User und Team leer) > null. Zustandsbehaftet je
 * Export-Lauf (cached Regeln + Team-Zugehörigkeiten) — pro Aggregation neu
 * instanziieren.
 *
 * Seit Feature 142 (MVP-709) ist er die **eine** KOST-Regel für Zeitexport,
 * DATEV-Stapel und Journal: {@see self::ruleForSource()} entscheidet je
 * Quellbeleg (Auslage → verursachende Person, sonst Org-Default), die
 * Aufrufer nehmen davon wahlweise den Code ({@see self::codeForSource()},
 * DATEV KOST1) oder den Stammsatz ({@see self::idForSource()}, Buchungszeile).
 */
class CostCenterResolver {
    /** @var Collection<int, CostCenterRule>|null */
    private ?Collection $rules = null;

    /** @var array<int, array<int, int>> */
    private array $teamIdsByUser = [];

    public function __construct(private readonly int $organizationId) {}

    public function forUser(int $userId): ?string {
        return $this->ruleForUser($userId)?->effectiveCode();
    }

    /**
     * Org-Default-Regel (Benutzer und Team leer) — auch für Quellen ohne
     * Personenbezug (DATEV-KOST1 an Rechnungen, Feature 135).
     */
    public function default(): ?string {
        return $this->defaultRule()?->effectiveCode();
    }

    /**
     * Gewinnende Regel für einen Quellbeleg: Auslagen tragen die Kostenstelle
     * der verursachenden Person (Benutzer-/Team-Regel wie im Zeitexport),
     * Rechnungen und alles andere die Org-Default-Regel.
     */
    public function ruleForSource(?Model $source): ?CostCenterRule {
        if ($source instanceof Expense && (int) $source->user_id > 0) {
            return $this->ruleForUser((int) $source->user_id);
        }

        return $this->defaultRule();
    }

    /** KOST-Code für den DATEV-Stapel (Feature 135). */
    public function codeForSource(?Model $source): ?string {
        return $this->ruleForSource($source)?->effectiveCode();
    }

    /**
     * Stammsatz-ID für die Buchungszeile (Feature 142). Regeln, die nur einen
     * Code-Snapshot tragen, werden über den Code der Organisation aufgelöst;
     * ohne Stammsatz bleibt die Zeile ohne Kostenstelle — geraten wird nicht.
     */
    public function idForSource(?Model $source): ?int {
        $rule = $this->ruleForSource($source);
        if ($rule === null) {
            return null;
        }

        $id = $rule->getAttribute('cost_center_id');
        if ($id !== null) {
            return (int) $id;
        }

        $byCode = CostCenter::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->where('code', $rule->cost_center)
            ->value('id');

        return $byCode !== null ? (int) $byCode : null;
    }

    private function ruleForUser(int $userId): ?CostCenterRule {
        $rules = $this->rules();
        if ($rules->isEmpty()) {
            return null;
        }

        $userRule = $rules->first(fn (CostCenterRule $r): bool => (int) $r->user_id === $userId);
        if ($userRule !== null) {
            return $userRule;
        }

        $teamIds = $this->teamIdsFor($userId);
        if ($teamIds !== []) {
            $teamRule = $rules->first(fn (CostCenterRule $r): bool => $r->team_id !== null && in_array((int) $r->team_id, $teamIds, true));
            if ($teamRule !== null) {
                return $teamRule;
            }
        }

        return $this->defaultRule();
    }

    private function defaultRule(): ?CostCenterRule {
        return $this->rules()->first(fn (CostCenterRule $r): bool => $r->user_id === null && $r->team_id === null);
    }

    /**
     * Alle Regeln der Organisation, sortiert nach Priorität (absteigend) und
     * id (aufsteigend) — first() liefert damit den Gewinner.
     *
     * @return Collection<int, CostCenterRule>
     */
    private function rules(): Collection {
        return $this->rules ??= CostCenterRule::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $this->organizationId)
            // Export läuft auch ohne gebundenen Org-Kontext (Queue/CLI) —
            // Stammdaten deshalb explizit ohne Global Scopes mitladen.
            ->with(['costCenter' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, int> */
    private function teamIdsFor(int $userId): array {
        return $this->teamIdsByUser[$userId] ??= DB::table('team_user')
            ->where('user_id', $userId)
            ->pluck('team_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
