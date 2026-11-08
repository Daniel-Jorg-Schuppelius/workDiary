<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcileBoard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Mirror\MirrorLine;
use Carbon\CarbonImmutable;

/**
 * Abgleichstafel je Produkt (Phase 166, MVP-1447): alle Perioden der Abos
 * eines Rechnungsempfängers als Soll und alle seine Lizenzpositionen als Ist
 * auf einem Zeitstrahl. Eine Periode trägt ihre Bezüge (mit Passung), eine
 * offene fällige zusätzlich die Kandidaten aus dem Abgleich; Positionen, die
 * weder an einer Periode der Tafel hängen noch Kandidat sind, stehen als
 * eigene Zeile an ihrem Bezugsdatum. Die Bezüge müssen vorgeladen sein
 * ({@see \App\Services\Reselling\Mirror\InvoiceMirror::preload()}).
 *
 * @phpstan-import-type LineRow from RecipientReconciler
 * @phpstan-import-type Candidate from RecipientReconciler
 * @phpstan-import-type PeriodRow from RecipientReconciler
 * @phpstan-import-type ProductRow from RecipientReconciler
 *
 * @phpstan-type BoardLink array{link: ResalePeriodLink, line: MirrorLine|null, fit: LineFit|null}
 * @phpstan-type BoardCandidate array{candidate: Candidate, fit: LineFit}
 * @phpstan-type BoardRow array{kind: string, date: CarbonImmutable, period: ResalePeriod|null, subscription: ResaleSubscription|null, open: PeriodRow|null, links: list<BoardLink>, candidates: list<BoardCandidate>, line: LineRow|null, upcoming: bool}
 * @phpstan-type BoardBlock array{product: ProductRow, rows: list<BoardRow>, targets: list<PeriodRow>}
 */
final class ReconcileBoard {
    /** Kennzahlen des Abgleichs, nach denen Liste und Tafel filtern (Kacheln, Pilot 2026-11-01). */
    public const array FOCUS = ['open', 'partial', 'proposed', 'free', 'missing', 'surplus'];

    /**
     * Nur die Produkte, die zur Kennzahl beitragen; ihre Zeilen bleiben vollständig, damit der Zeitstrahl lesbar bleibt.
     *
     * @param  list<BoardBlock>  $blocks
     * @return list<BoardBlock>
     */
    public static function focus(array $blocks, string $focus): array {
        return array_values(array_filter($blocks, static fn(array $block): bool => match ($focus) {
            'free', 'missing', 'surplus' => $block['product'][$focus] > 0.001,
            default => array_filter($block['rows'], static fn(array $row): bool => self::counts($row, $focus)) !== [],
        }));
    }

    /**
     * Zählt die Zeile für die Kennzahl so wie {@see RecipientReconciler::forCustomer()}?
     *
     * @param  BoardRow  $row
     */
    private static function counts(array $row, string $focus): bool {
        $period = $row['period'];
        if ($period === null) {
            return false;
        }

        return match ($focus) {
            'open' => $row['open'] !== null && $period->status !== PeriodStatus::Partial,
            'partial' => $row['open'] !== null && $period->status === PeriodStatus::Partial,
            'proposed' => ! $row['upcoming'] && $period->status === PeriodStatus::Billed && $period->isProposedOnly(),
            default => false,
        };
    }

    /**
     * @param  array{subscriptions: iterable<ResaleSubscription>, products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, product_by_subscription: array<int, string>}  $result  {@see RecipientReconciler::forCustomer()}
     * @return list<BoardBlock>
     */
    public function build(array $result, CarbonImmutable $reference): array {
        /** @var array<string, list<ResalePeriod>> $competitors offene Perioden je Produkt (auch bevorstehende) */
        $competitors = [];
        /** @var array<string, list<array{0: ResalePeriod, 1: ResaleSubscription}>> $periodsByProduct */
        $periodsByProduct = [];
        foreach ($result['subscriptions'] as $subscription) {
            $product = $result['product_by_subscription'][$subscription->id] ?? $subscription->productKey();
            foreach ($subscription->periods as $period) {
                $periodsByProduct[$product][] = [$period, $subscription];
                if (in_array($period->status, [PeriodStatus::Open, PeriodStatus::Partial], true)) {
                    $competitors[$product][] = $period;
                }
            }
        }

        /** @var array<int, PeriodRow> $openById */
        $openById = [];
        /** @var array<string, list<PeriodRow>> $targets */
        $targets = [];
        /** @var array<string, array{period: int, score: list<int>, outside: bool}> $best Position → offene Periode, zu der sie am besten passt */
        $best = [];
        foreach ($result['periods'] as $row) {
            $openById[$row['period']->id] = $row;
            $targets[$row['product']][] = $row;
            // Jede freie Rechnung steht nur einmal — bei der Periode, zu der sie zeitlich am besten passt
            // (Phase 168, MVP-1461); vorher stand dieselbe Rechnung in jeder offenen Periode ihres Fensters.
            foreach ($row['candidates'] as $candidate) {
                $fit = LineFit::of($candidate['row']['line'], $row['period'], $competitors[$row['product']] ?? []);
                $score = [$fit->rank(), $fit->days ?? PHP_INT_MAX, $row['period']->starts_on->getTimestamp()];
                foreach ($candidate['rows'] as $twin) {
                    $identity = $twin['line']->identity();
                    if (! isset($best[$identity]) || $score < $best[$identity]['score']) {
                        $best[$identity] = ['period' => (int) $row['period']->id, 'score' => $score, 'outside' => $fit->kind === LineFit::OUTSIDE];
                    }
                }
            }
        }
        /** @var array<string, true> $offered Positionen, die einer offenen Periode als Kandidat angeboten werden */
        $offered = [];
        foreach ($best as $identity => $entry) {
            if (! $entry['outside']) {
                $offered[$identity] = true;
            }
        }

        /** @var array<string, list<BoardRow>> $rowsByProduct */
        $rowsByProduct = [];
        /** @var array<string, true> $shown Positionen, die an einer Periode der Tafel hängen */
        $shown = [];
        foreach ($periodsByProduct as $product => $entries) {
            foreach ($entries as [$period, $subscription]) {
                $links = [];
                foreach ($period->links as $link) {
                    $line = $link->mirrorLine();
                    if ($line !== null) {
                        $shown[$line->identity()] = true;
                    }
                    $links[] = ['link' => $link, 'line' => $line, 'fit' => $line !== null && ! $line->isCreditNote ? LineFit::of($line, $period, $competitors[$product] ?? []) : null];
                }
                $open = $openById[$period->id] ?? null;
                $candidates = [];
                foreach ($open['candidates'] ?? [] as $candidate) {
                    $identity = $candidate['row']['line']->identity();
                    if (! isset($offered[$identity]) || ($best[$identity]['period'] ?? null) !== (int) $period->id) {
                        continue;
                    }
                    $candidates[] = ['candidate' => $candidate, 'fit' => LineFit::of($candidate['row']['line'], $period, $competitors[$product] ?? [])];
                }
                usort($candidates, static fn (array $a, array $b): int => ($a['fit']->rank() <=> $b['fit']->rank()) ?: ($a['candidate']['distance'] <=> $b['candidate']['distance']));
                $rowsByProduct[$product][] = [
                    'kind' => 'period', 'date' => $period->starts_on, 'period' => $period, 'subscription' => $subscription, 'open' => $open,
                    'links' => $links, 'candidates' => $candidates, 'line' => null, 'upcoming' => $period->starts_on->greaterThan($reference),
                ];
            }
        }

        // Positionen ohne Platz in der Tafel: frei und keinem Kandidaten zugeordnet, oder an Perioden außerhalb (anderer Empfänger).
        // Eine Lücke (keine Periode um das Bezugsdatum: Abo fehlt im Register) steht immer als eigene Zeile.
        foreach ($result['lines'] as $lineRow) {
            $identity = $lineRow['line']->identity();
            if (! $lineRow['gap'] && (isset($shown[$identity]) || isset($offered[$identity]))) {
                continue;
            }
            $rowsByProduct[$lineRow['product']][] = [
                'kind' => 'line', 'date' => LicenseMonths::referenceDate($lineRow['line']) ?? $reference, 'period' => null, 'subscription' => null, 'open' => null,
                'links' => [], 'candidates' => [], 'line' => $lineRow, 'upcoming' => false,
            ];
        }

        $blocks = [];
        foreach ($result['products'] as $product) {
            $rows = $rowsByProduct[$product['key']] ?? [];
            if ($rows === []) {
                continue;
            }
            usort($rows, static fn (array $a, array $b): int => ($a['date'] <=> $b['date']) ?: (($a['kind'] === 'period' ? 0 : 1) <=> ($b['kind'] === 'period' ? 0 : 1)) ?: (($a['subscription']->id ?? 0) <=> ($b['subscription']->id ?? 0)));
            $blocks[] = ['product' => $product, 'rows' => $rows, 'targets' => $targets[$product['key']] ?? []];
        }

        return $blocks;
    }
}
