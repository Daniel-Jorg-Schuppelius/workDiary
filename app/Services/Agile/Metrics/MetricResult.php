<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MetricResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile\Metrics;

use Illuminate\Support\Carbon;

/**
 * Ergebnis einer agilen Kennzahl (Feature 064, MVP-143). Trägt die
 * Definitionsversion, den Berechnungszeitpunkt und die Filter — Berichte
 * (P8–P10) zeigen damit nachvollziehbar, WIE gerechnet wurde.
 */
final readonly class MetricResult {
    /**
     * @param array<string, mixed> $filters
     * @param array<int|string, mixed> $data
     */
    public function __construct(
        public string $code,
        public string $unit,
        public int $metricVersion,
        public Carbon $computedAt,
        public array $filters,
        public array $data,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'code' => $this->code,
            'unit' => $this->unit,
            'metric_version' => $this->metricVersion,
            'computed_at' => $this->computedAt->toIso8601String(),
            'filters' => $this->filters,
            'data' => $this->data,
        ];
    }
}
