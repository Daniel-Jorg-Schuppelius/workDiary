<?php
/*
 * Created on   : Wed Nov 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanAnchors.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Enums\Reselling\LinkOrigin;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResalePlanLink, ResaleSubscription};
use App\Services\Reselling\Mirror\MirrorLine;
use App\Services\Reselling\Register\{LicenseMonths, LinkProposer, ResaleRequestMemo};
use Carbon\CarbonImmutable;

/**
 * Vorlagenanker (Phase 183, MVP-1741): Eine Rechnung kennt die Serienvorlage,
 * aus der sie entstand, und die Vorlagenposition ist einem Abo zugeordnet —
 * damit steht fest, welchem Abo die Rechnungsposition gehört. Zur Kette zählen
 * die Vorgänger des Abos (Anbieter- oder Bestellungswechsel).
 *
 * Der Anker gilt nur, wo er belegt ist: entschiedene Zuordnung, gleicher
 * Artikel wie die Vorlagenposition, Bezugsdatum nicht vor dem Beginn der
 * Kette. Ältere Rechnungen derselben Vorlagenposition gehörten einem anderen
 * Abo (Produktivdaten 2026-11-18: 58 von 166 Abweichungen).
 *
 * @phpstan-type Anchor array{label: string, article: string|null, subscriptions: array<int, true>, from: CarbonImmutable|null}
 */
final class PlanAnchors {
    /** Schlüssel im Request-Memo; das Präfix `plan-templates` teilt die Invalidierung der Vorlagen. */
    public const string MEMO_PREFIX = 'plan-templates-anchors:';

    public function __construct(private readonly RecurringPlans $plans) {}

    /**
     * Urteil des Ankers über Position und Periode (Anzeige, Passung, Belegdialog — MVP-1745):
     * true = die Position gehört dieser Periode (Abo in der Kette, Bezugsdatum in der Laufzeit
     * oder höchstens {@see LinkProposer::WINDOW_BEFORE} Tage davor), false = sie gehört laut
     * Vorlage einem anderen Abo, null = kein Anker oder ein anderes Lizenzjahr derselben Kette.
     */
    public function verdict(ResalePeriod $period, MirrorLine $line): ?bool {
        if ($line->planKey === null) {
            return null;
        }
        $organizationId = (int) $period->organization_id;
        $map = app(ResaleRequestMemo::class)->remember(self::MEMO_PREFIX . $organizationId, function () use ($organizationId): array {
            $organization = Organization::query()->find($organizationId);

            return $organization === null ? [] : $this->map($organization);
        });
        $anchor = self::of($map, $line);
        if ($anchor === null) {
            return null;
        }
        if (! isset($anchor['subscriptions'][(int) $period->subscription_id])) {
            return false;
        }
        $date = LicenseMonths::referenceDate($line);

        return $date !== null && ! $date->lessThan($period->starts_on->subDays(LinkProposer::WINDOW_BEFORE)) && ! $date->greaterThan($period->ends_on) ? true : null;
    }

    /**
     * @return array<string, Anchor> Schlüssel der Vorlagenposition → Anker
     */
    public function map(Organization $organization): array {
        if (! $this->plans->availableFor($organization)) {
            return [];
        }
        $links = ResalePlanLink::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('origin', '<>', LinkOrigin::Proposed->value)
            ->get(['subscription_id', 'plan_key', 'line_position']);
        if ($links->isEmpty()) {
            return [];
        }
        $templates = $this->plans->templatesFor($organization)->keyBy(static fn(PlanTemplate $t): string => $t->key);
        [$predecessors, $starts] = $this->lineage($organization);
        $map = [];
        foreach ($links as $link) {
            $template = $templates->get($link->plan_key);
            $line = $template?->line((int) $link->line_position);
            if ($template === null || $line === null) {
                continue;
            }
            $key = $link->lineKey();
            $map[$key] ??= ['label' => $template->label(), 'article' => $line->articleKey, 'subscriptions' => [], 'from' => null];
            foreach (self::chain((int) $link->subscription_id, $predecessors) as $id) {
                $map[$key]['subscriptions'][$id] = true;
                $start = $starts[$id] ?? null;
                if ($start !== null && ($map[$key]['from'] === null || $start->lessThan($map[$key]['from']))) {
                    $map[$key]['from'] = $start;
                }
            }
        }

        return $map;
    }

    /**
     * Kette je Abo: das Abo und alle seine Vorgänger.
     *
     * @param  list<int>  $subscriptionIds
     * @return array<int, list<int>>
     */
    public function chains(Organization $organization, array $subscriptionIds): array {
        if ($subscriptionIds === []) {
            return [];
        }
        [$predecessors] = $this->lineage($organization);
        $chains = [];
        foreach ($subscriptionIds as $id) {
            $chains[$id] = self::chain($id, $predecessors);
        }

        return $chains;
    }

    /**
     * @return array{0: array<int, list<int>>, 1: array<int, CarbonImmutable>} direkte Vorgänger je Abo, Beginn je Abo
     */
    private function lineage(Organization $organization): array {
        $predecessors = [];
        $starts = [];
        foreach (ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get(['id', 'successor_id', 'starts_on']) as $row) {
            $starts[(int) $row->id] = $row->starts_on;
            if ($row->successor_id !== null) {
                $predecessors[(int) $row->successor_id][] = (int) $row->id;
            }
        }

        return [$predecessors, $starts];
    }

    /**
     * Anker der Rechnungsposition oder null: keine Vorlage, Gutschrift, Artikel
     * weicht von der Vorlagenposition ab oder Bezugsdatum vor Beginn der Kette.
     *
     * @param  array<string, Anchor>  $map
     * @return Anchor|null
     */
    public static function of(array $map, MirrorLine $line): ?array {
        if ($line->planKey === null || $line->isCreditNote) {
            return null;
        }
        $anchor = $map[ResalePlanLink::lineKeyOf($line->planKey, $line->position)] ?? null;
        if ($anchor === null || $anchor['subscriptions'] === []) {
            return null;
        }
        if ($anchor['article'] !== $line->articleKey) {
            return null;
        }
        $date = LicenseMonths::referenceDate($line);
        if ($date === null || ($anchor['from'] !== null && $date->lessThan($anchor['from']->subDays(LinkProposer::WINDOW_BEFORE)))) {
            return null;
        }

        return $anchor;
    }

    /**
     * Das Abo und alle seine Vorgänger.
     *
     * @param  array<int, list<int>>  $predecessors
     * @return list<int>
     */
    private static function chain(int $subscriptionId, array $predecessors): array {
        $chain = [];
        $queue = [$subscriptionId];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($chain[$id])) {
                continue;
            }
            $chain[$id] = true;
            foreach ($predecessors[$id] ?? [] as $previous) {
                $queue[] = $previous;
            }
        }

        return array_keys($chain);
    }
}
