<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkAudit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use Illuminate\Support\Collection;

/**
 * Prüffälle an Rechnungsbezügen (Feature 185, MVP-1403): eine Position, die
 * mehr Lizenzmonate deckt, als sie hat (überbucht), und ein Bezug, dessen
 * Leistungszeitraum die Periode nicht trifft (zeitlich unpassend) — mit dem
 * Vorschlag, auf welche Periode desselben Abos er gehört. Nichts wird
 * umgehängt; der Betreiber entscheidet.
 *
 * @phpstan-type Finding array{kind: string, line: MirrorLine, link: ResalePeriodLink|null, links: list<ResalePeriodLink>, period: ResalePeriod|null, suggested: ResalePeriod|null, excess: float}
 */
final class LinkAudit {
    public const OVERBOOKED = 'overbooked';

    public const MISMATCH = 'mismatch';

    private readonly InvoiceMirror $mirror;

    private readonly PeriodLinker $linker;

    public function __construct(?InvoiceMirror $mirror = null, ?PeriodLinker $linker = null) {
        $this->mirror = $mirror ?? app(InvoiceMirror::class);
        $this->linker = $linker ?? new PeriodLinker($this->mirror);
    }

    /**
     * Prüffälle über die Abos (mit `periods.links` geladen).
     *
     * @param  iterable<ResaleSubscription>  $subscriptions
     * @return list<Finding>
     */
    public function forSubscriptions(Organization $organization, iterable $subscriptions): array {
        /** @var list<ResalePeriodLink> $links */
        $links = [];
        /** @var array<int, ResalePeriod> $periodsById */
        $periodsById = [];
        /** @var array<int, list<ResalePeriod>> $periodsBySubscription */
        $periodsBySubscription = [];
        foreach ($subscriptions as $subscription) {
            foreach ($subscription->periods as $period) {
                $periodsById[$period->id] = $period;
                $periodsBySubscription[$subscription->id][] = $period;
                foreach ($period->links as $link) {
                    $links[] = $link;
                }
            }
        }
        if ($links === []) {
            return [];
        }
        $lines = $this->mirror->preload($organization, $links);
        $findings = [];

        // Überbucht: Verbrauch der Position über ALLE Perioden der Organisation gegen ihre Lizenzmonate.
        $consumed = $this->linker->consumed(array_values($lines));
        $seen = [];
        foreach ($links as $link) {
            $line = $lines[$link->mirrorIdentity()] ?? null;
            if ($line === null || $line->isCreditNote || isset($seen[$line->identity()])) {
                continue;
            }
            $seen[$line->identity()] = true;
            $available = LicenseMonths::ofLine($line);
            $used = $consumed[$line->identity()]['months'] ?? 0.0;
            if ($used > $available + 0.001) {
                $findings[] = [
                    'kind' => self::OVERBOOKED,
                    'line' => $line,
                    'link' => null,
                    'links' => array_values(array_filter($links, static fn(ResalePeriodLink $l): bool => $l->mirrorIdentity() === $line->identity())),
                    'period' => null,
                    'suggested' => null,
                    'excess' => round($used - $available, 2),
                ];
            }
        }

        // Zeitlich unpassend: Leistungszeitraum bzw. Rechnungsdatum trifft die Periode nicht.
        foreach ($links as $link) {
            $line = $lines[$link->mirrorIdentity()] ?? null;
            $period = $periodsById[$link->period_id] ?? null;
            if ($line === null || $period === null || $line->isCreditNote || self::fits($line, $period)) {
                continue;
            }
            $findings[] = [
                'kind' => self::MISMATCH,
                'line' => $line,
                'link' => $link,
                'links' => [$link],
                'period' => $period,
                'suggested' => $this->suggest($line, $link, $periodsBySubscription[$period->subscription_id] ?? []),
                'excess' => 0.0,
            ];
        }

        return $findings;
    }

    /**
     * Passt die Position zeitlich zur Periode? Mit Leistungszeitraum muss er
     * die Periode überschneiden; ohne zählt das Rechnungsdatum im Fenster
     * des Vorschlagslaufs (−90 Tage bis Nachberechnung über zwei Jahre).
     */
    public static function fits(MirrorLine $line, ResalePeriod $period): bool {
        if ($line->serviceFrom !== null && $line->serviceTo !== null) {
            return ! $line->serviceTo->lessThan($period->starts_on) && ! $line->serviceFrom->greaterThan($period->ends_on);
        }
        $date = LicenseMonths::referenceDate($line);
        if ($date === null) {
            return true;
        }

        return ! $date->lessThan($period->starts_on->subDays(LinkProposer::WINDOW_BEFORE)) && ! $date->greaterThan($period->ends_on->addDays(LinkProposer::WINDOW_AFTER));
    }

    /**
     * Periode desselben Abos, die zur Position passt und die Monate des Bezugs
     * noch aufnehmen kann — bevorzugt eine ohne Entscheidung.
     *
     * @param  list<ResalePeriod>  $candidates
     */
    private function suggest(MirrorLine $line, ResalePeriodLink $link, array $candidates): ?ResalePeriod {
        $best = null;
        foreach ($candidates as $candidate) {
            if ($candidate->id === $link->period_id || ! self::fits($line, $candidate) || in_array($candidate->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true)) {
                continue;
            }
            if ($candidate->openMonths() + 0.001 < (float) $link->months) {
                continue;
            }
            if ($best === null || ($best->decided_at !== null && $candidate->decided_at === null)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Prüffälle je Abo-ID (Zähler für Listen).
     *
     * @param  list<Finding>  $findings
     * @return array<int, int>
     */
    public static function countBySubscription(array $findings): array {
        $counts = [];
        foreach ($findings as $finding) {
            foreach ($finding['links'] as $link) {
                $counts[(int) $link->subscription_id] = ($counts[(int) $link->subscription_id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  iterable<int>  $ids
     * @return Collection<int, ResaleSubscription>
     */
    public static function loadedWithLinks(Organization $organization, iterable $ids): Collection {
        return ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', collect($ids)->all())->with(['periods.links'])->get();
    }
}
