<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDeadlineScans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{AssetAssignment, MaintenancePlan, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Asset-Fristen (Feature 009): überfällige Ausgabe-Rückgaben und fällige
 * Wartungs-/Prüfpläne — ein Fachmodul, eine Scan-Klasse (B11).
 *
 * @phpstan-import-type TNotifyPayload from AbstractDeadlineScan
 */
class AssetDeadlineScans extends AbstractDeadlineScan {
    public function key(): string {
        return 'assets';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->scanReturns($dispatcher) + $this->scanMaintenance($dispatcher, $options->expiringDays);
    }

    /**
     * Ausgabe-/Rückgabe-Workflow (Feature 009): offene Asset-Zuweisungen mit
     * überschrittener erwarteter Rückgabe melden (Empfänger Ausleiher) + Eskalation.
     */
    private function scanReturns(NotificationDispatcher $dispatcher): int {
        $now = Carbon::now();

        return $this->runScan($dispatcher, [
            'affected' => fn(AssetAssignment $assignment): ?User => $assignment->assignedToUser,
            'overdue' => [
                'query' => fn() => AssetAssignment::query()
                    ->whereNull('returned_at')
                    ->whereNotNull('expected_return_at')
                    ->where('expected_return_at', '<=', $now)
                    ->with(['asset:id,name,asset_no', 'assignedToUser']),
                'event' => NotificationEvent::AssetReturnOverdue,
                'payload' => fn(AssetAssignment $assignment): array => $this->assetReturnPayload($assignment),
            ],
        ]);
    }

    /**
     * Wartungs-/Prüfpläne (Feature 009, MVP-336): fällig innerhalb des
     * Vorlaufs → dueSoon; überschrittene, unerledigte Fälligkeit → overdue
     * mit Eskalationskette (escalateIfDue — Stufe 1 an die Eskalationsrolle,
     * optionale Stufen 2/3 gemäß Regel, MVP-331). Empfänger ist der Asset-
     * Verantwortliche (aktueller Ausgabe-Inhaber, notify_affected),
     * Default-Fallback/Mitwisser die Rolle teamleitung (NotificationEvent).
     * Dedup über das notification_dispatch_log pro Plan und Stufe.
     */
    private function scanMaintenance(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $today = Carbon::now()->toDateString();
        $soon = Carbon::now()->addDays($expiringDays)->toDateString();

        return $this->runScan($dispatcher, [
            'affected' => fn(MaintenancePlan $plan): ?User => $this->maintenanceAffected($plan),
            'due' => [
                'query' => fn() => MaintenancePlan::query()
                    ->where('is_active', true)
                    ->whereNotNull('next_due_on')
                    ->whereBetween('next_due_on', [$today, $soon])
                    ->with('asset.currentAssignment.assignedToUser'),
                'event' => NotificationEvent::MaintenanceDueSoon,
                'payload' => fn(MaintenancePlan $plan): array => $this->maintenancePayload($plan, 'maintenance_due_soon'),
            ],
            'overdue' => [
                'query' => fn() => MaintenancePlan::query()
                    ->where('is_active', true)
                    ->whereNotNull('next_due_on')
                    ->where('next_due_on', '<', $today)
                    ->with('asset.currentAssignment.assignedToUser'),
                'event' => NotificationEvent::MaintenanceOverdue,
                'payload' => fn(MaintenancePlan $plan): array => $this->maintenancePayload($plan, 'maintenance_overdue'),
            ],
        ]);
    }

    /**
     * @return TNotifyPayload
     */
    private function assetReturnPayload(AssetAssignment $assignment): array {
        $asset = $assignment->asset;
        $title = $asset !== null ? trim($asset->asset_no . ' — ' . $asset->name, ' —') : '';

        return [
            'title' => $title,
            'message' => (string) __('notification.message.asset_return_overdue', [
                'date' => $assignment->expected_return_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.asset_return_overdue',
            'message_params' => ['date' => $assignment->expected_return_at?->toIso8601String() ?? '–'],
            'url' => $asset !== null ? route('assets.show', $asset) : null,
            'due_at' => $assignment->expected_return_at,
        ];
    }

    /**
     * Asset-Verantwortlicher eines Wartungsplans (MVP-336): der aktuelle
     * Ausgabe-Inhaber des verknüpften Assets (offene Zuweisung), sofern
     * vorhanden — sonst greift der Rollen-Fallback der Regel (teamleitung).
     */
    private function maintenanceAffected(MaintenancePlan $plan): ?User {
        return $plan->asset?->currentAssignment?->assignedToUser;
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function maintenancePayload(MaintenancePlan $plan, string $messageKey): array {
        return [
            'title' => (string) $plan->label,
            'message' => (string) __('notification.message.' . $messageKey, [
                'label' => (string) $plan->label,
                'date' => $plan->next_due_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => [
                'label' => (string) $plan->label,
                'date' => $plan->next_due_on?->toDateString() ?? '–',
            ],
            'url' => route('assets.index'),
            'due_at' => $plan->next_due_on,
        ];
    }
}
