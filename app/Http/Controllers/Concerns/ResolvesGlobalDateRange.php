<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesGlobalDateRange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Services\UI\DateRangeContext;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Resolves date hints for report controllers: query parameters take
 * precedence (so bookmarked URLs still work), otherwise the globally
 * selected DateRangeContext (from the header widget) is used.
 */
trait ResolvesGlobalDateRange {
    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, preset: string, effectivePreset: string, label: string, unit: string, isoWeekLabel: ?string}
     */
    protected function globalDateRange(): array {
        return app(DateRangeContext::class)->current();
    }

    /**
     * Normalisierte [von, bis]-Grenzen des global gewählten Zeitraums:
     * Von am Tagesanfang, Bis am Tagesende — ohne from/to-Query-Override.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function globalDateRangeBounds(): array {
        $range = $this->globalDateRange();

        return [$range['from']->startOfDay(), $range['to']->endOfDay()];
    }

    /**
     * Effektiver [von, bis]-Zeitraum eines Listen-Requests: explizite
     * from/to-Query-Parameter haben Vorrang (Bookmarks), sonst der global
     * gewählte Zeitraum aus dem Header-Widget. Von startet am Tagesanfang,
     * Bis endet am Tagesende.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function resolveRange(Request $request): array {
        if ($request->filled('from') && $request->filled('to')) {
            try {
                $from = CarbonImmutable::parse((string) $request->query('from'))->startOfDay();
                $to = CarbonImmutable::parse((string) $request->query('to'))->endOfDay();
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                // Müll-Input (hand-editierte Bookmarks) → globaler Zeitraum statt 500.
                return $this->globalDateRangeBounds();
            }

            if ($to->lessThan($from)) {
                [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
            }

            return [$from, $to];
        }

        return $this->globalDateRangeBounds();
    }

    /**
     * Splits a date range into a list of months (Y-m keys) for tab navigation.
     *
     * @return list<array{key:string,year:int,month:int,label:string,shortLabel:string}>
     */
    protected function buildMonthsInRange(CarbonImmutable $from, CarbonImmutable $to): array {
        $months = [];
        $cursor = $from->startOfMonth();
        $end = $to->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'year' => $cursor->year,
                'month' => $cursor->month,
                'label' => $cursor->translatedFormat('F Y'),
                'shortLabel' => $cursor->translatedFormat('M Y'),
            ];
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Einheit des global gewählten Zeitraums (Header-Widget) für adaptive
     * Diagramm-Granularität. Fällt bei fehlender Angabe auf 'custom' zurück.
     */
    protected function globalUnit(): string {
        $unit = $this->globalDateRange()['unit'];

        return $unit !== '' ? $unit : 'custom';
    }

    /**
     * Granularität für die Zeitachse eines Diagramms, abgeleitet aus dem global
     * gewählten Zeitraum ({@see ChartBucket::granularity()}). Die 'hour'-Stufe
     * wird auf 'day' reduziert: Report-Datensätze dieser Gruppe sind
     * datumsgenau (kein Tageszeit-Bezug), ein Ein-Tages-Zeitraum ergibt genau
     * einen Tages-Bucket.
     *
     * @return 'day'|'week'|'month'|'quarter'
     */
    protected function bucketGranularity(CarbonImmutable $from, CarbonImmutable $to, ?string $unit = null): string {
        $granularity = ChartBucket::granularity($unit ?? $this->globalUnit(), $from, $to);

        return $granularity === 'hour' ? 'day' : $granularity;
    }

    /**
     * Adaptive Zeit-Buckets für [von,bis] in der Granularität des global
     * gewählten Zeitraums (Tag→Tag, Woche→Tag, Monat/Quartal→Woche, Jahr→Monat,
     * mehrjährig→Quartal). Ersetzt {@see buildMonthsInRange()} für Diagramme,
     * die auf die Header-Zeitangabe reagieren. Reihenfolge ist sortierstabil,
     * Schlüssel entstehen über {@see ChartBucket::keyLabel()} — Aufrufer mappen
     * Einzeldaten mit demselben Aufruf auf den passenden Bucket-Schlüssel.
     *
     * @return list<array{key: string, label: string, shortLabel: string}>
     */
    protected function buildBucketsInRange(CarbonImmutable $from, CarbonImmutable $to, ?string $unit = null): array {
        $granularity = $this->bucketGranularity($from, $to, $unit);

        /** @var array<string, array{key: string, label: string, shortLabel: string}> $buckets */
        $buckets = [];
        for ($cursor = $from->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            [$key, $label] = ChartBucket::keyLabel($granularity, $cursor);
            $buckets[$key] ??= ['key' => $key, 'label' => $label, 'shortLabel' => $label];
        }

        return array_values($buckets);
    }

    /**
     * Lokalisierte „je <Periode>"-Phrase für einen adaptiven Diagrammtitel
     * (Platzhalter `:per`), passend zur Granularität aus {@see bucketGranularity()}.
     */
    protected function periodPhrase(string $granularity): string {
        return match ($granularity) {
            'week' => __('je Woche'),
            'month' => __('je Monat'),
            'quarter' => __('je Quartal'),
            default => __('je Tag'),
        };
    }

    /**
     * Lokalisiertes Achswort (X-Achse) passend zur Granularität aus
     * {@see bucketGranularity()}.
     */
    protected function periodAxisLabel(string $granularity): string {
        return match ($granularity) {
            'week' => __('Woche'),
            'month' => __('Monat'),
            'quarter' => __('Quartal'),
            default => __('Tag'),
        };
    }
}
