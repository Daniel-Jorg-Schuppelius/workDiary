<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Models\Audit\AuditLog;
use App\Models\Sales\Lead;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;

/**
 * Lead-Auswertung je Quelle (MVP-1296): Zugang im Zeitraum, Stand, Konversion
 * und Dauer bis zur Konversion. Konvertiert zählt, wer heute konvertiert ist —
 * auch wenn die Konversion nach dem Zeitraum lag (Kohortensicht).
 */
final class LeadReport {
    /**
     * @return array{rows: array<string, array{source: LeadSource, total: int, open: int, converted: int, discarded: int, rate: float|null, avg_days: float|null}>, totals: array{total: int, converted: int, rate: float|null}}
     */
    public function build(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array {
        $leads = Lead::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'source', 'status', 'created_at']);

        $convertedAt = AuditLog::query()
            ->where('organization_id', $organizationId)
            ->where('auditable_type', MorphMap::stableKey(Lead::class))
            ->whereIn('auditable_id', $leads->pluck('id'))
            ->where('event', 'lead.converted')
            ->pluck('created_at', 'auditable_id');

        $rows = [];
        foreach (LeadSource::cases() as $source) {
            $group = $leads->filter(fn (Lead $lead): bool => $lead->source === $source);
            if ($group->isEmpty()) {
                continue;
            }
            $converted = $group->filter(fn (Lead $lead): bool => $lead->status === LeadStatus::Converted);
            $days = $converted
                ->map(fn (Lead $lead): ?float => isset($convertedAt[$lead->id]) && $lead->created_at !== null
                    ? $lead->created_at->diffInHours($convertedAt[$lead->id]) / 24
                    : null)
                ->filter(fn (?float $d): bool => $d !== null);
            $rows[$source->value] = [
                'source' => $source,
                'total' => $group->count(),
                'open' => $group->filter(fn (Lead $lead): bool => in_array($lead->status, [LeadStatus::New, LeadStatus::Contacted, LeadStatus::Qualified], true))->count(),
                'converted' => $converted->count(),
                'discarded' => $group->filter(fn (Lead $lead): bool => $lead->status === LeadStatus::Discarded)->count(),
                'rate' => round($converted->count() / $group->count() * 100, 1),
                'avg_days' => $days->isNotEmpty() ? round((float) $days->avg(), 1) : null,
            ];
        }

        $total = $leads->count();
        $convertedTotal = $leads->filter(fn (Lead $lead): bool => $lead->status === LeadStatus::Converted)->count();

        return [
            'rows' => $rows,
            'totals' => ['total' => $total, 'converted' => $convertedTotal, 'rate' => $total > 0 ? round($convertedTotal / $total * 100, 1) : null],
        ];
    }
}
