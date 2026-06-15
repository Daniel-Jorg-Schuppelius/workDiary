<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetTimelineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Models\Asset;
use Illuminate\Support\Carbon;

class AssetTimelineService {
    /**
     * @return list<array<string, mixed>>
     */
    public function build(Asset $asset, int $limit = 120): array {
        $events = [];

        // Generische Trait-Events (created/updated/deleted) werden für Assets
        // ausgeblendet, weil der AssetService für jeden Vorgang ein
        // spezifischeres `asset.*`-Event schreibt und sonst Duplikate
        // in der Timeline erscheinen.
        $genericEvents = ['created', 'updated', 'deleted'];

        foreach ($asset->auditLogs()->with('user:id,name')->limit($limit)->get() as $log) {
            if (in_array($log->event, $genericEvents, true)) {
                continue;
            }

            $events[] = [
                'kind' => 'asset.audit',
                'occurred_at' => $this->toIso($log->created_at),
                'payload' => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                ],
            ];
        }

        foreach ($asset->diaryEntries()->latest('updated_at')->limit($limit)->get() as $entry) {
            $events[] = [
                'kind' => 'order.linked',
                'occurred_at' => $this->toIso($entry->updated_at ?? $entry->created_at),
                'payload' => [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'status' => $entry->status->value,
                ],
            ];
        }

        foreach ($asset->protocols()->limit($limit)->get() as $protocol) {
            $events[] = [
                'kind' => 'protocol.linked',
                'occurred_at' => $this->toIso($protocol->occurred_at),
                'payload' => [
                    'id' => $protocol->id,
                    'type' => $protocol->type->value,
                    'status' => $protocol->status->value,
                    'title' => $protocol->title,
                ],
            ];
        }

        foreach ($asset->materialUsages()->latest('updated_at')->limit($limit)->get() as $usage) {
            $events[] = [
                'kind' => 'material.linked',
                'occurred_at' => $this->toIso($usage->updated_at ?? $usage->created_at),
                'payload' => [
                    'id' => $usage->id,
                    'description' => $usage->description,
                    'quantity' => $usage->quantity,
                    'unit' => $usage->unit,
                    'line_total_net' => $usage->line_total_net,
                ],
            ];
        }

        foreach ($asset->attachments()->limit($limit)->get() as $attachment) {
            $events[] = [
                'kind' => 'attachment.linked',
                'occurred_at' => $this->toIso($attachment->created_at),
                'payload' => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                ],
            ];
        }

        // Ausgabe/Rückgabe (Feature 009): jeweils ein Checkout- und (falls
        // zurückgegeben) ein Return-Event aus derselben Zuweisung.
        foreach ($asset->assignments()->limit($limit)->get() as $assignment) {
            $events[] = [
                'kind' => 'assignment.checkedOut',
                'occurred_at' => $this->toIso($assignment->checked_out_at),
                'payload' => [
                    'id' => $assignment->id,
                    'user_name' => $assignment->assignedToUser?->name,
                    'team_name' => $assignment->assignedToTeam?->name,
                ],
            ];
            if ($assignment->returned_at !== null) {
                $events[] = [
                    'kind' => 'assignment.returned',
                    'occurred_at' => $this->toIso($assignment->returned_at),
                    'payload' => [
                        'id' => $assignment->id,
                        'user_name' => $assignment->assignedToUser?->name,
                        'team_name' => $assignment->assignedToTeam?->name,
                    ],
                ];
            }
        }

        // Defekt-/Sperrmeldungen (Feature 009).
        foreach ($asset->defects()->limit($limit)->get() as $defect) {
            $events[] = [
                'kind' => 'defect.reported',
                'occurred_at' => $this->toIso($defect->reported_at),
                'payload' => [
                    'id' => $defect->id,
                    'title' => $defect->title,
                    'severity' => $defect->severity->value,
                    'status' => $defect->status->value,
                    'blocks_usage' => $defect->blocks_usage,
                ],
            ];
            if ($defect->resolved_at !== null) {
                $events[] = [
                    'kind' => 'defect.resolved',
                    'occurred_at' => $this->toIso($defect->resolved_at),
                    'payload' => [
                        'id' => $defect->id,
                        'title' => $defect->title,
                    ],
                ];
            }
        }

        // Durchgeführte Wartungen (MaintenancePlan.last_run_at) sowie geplante
        // Fälligkeiten (next_due_on) als Lebenszyklus-Marker.
        foreach ($asset->maintenancePlans()->get() as $plan) {
            if ($plan->last_run_at !== null) {
                $events[] = [
                    'kind' => 'maintenance.completed',
                    'occurred_at' => $this->toIso($plan->last_run_at),
                    'payload' => [
                        'id' => $plan->id,
                        'label' => $plan->label,
                    ],
                ];
            }
        }

        usort(
            $events,
            static function (array $a, array $b): int {
                $left = (string) ($a['occurred_at'] ?? '');
                $right = (string) ($b['occurred_at'] ?? '');

                return $right <=> $left;
            }
        );

        return array_slice($events, 0, max(1, $limit));
    }

    private function toIso(Carbon|string|null $value): ?string {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return Carbon::parse($value)->toISOString();
        }

        return $value->toISOString();
    }
}
