<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceWindowService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Operations;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\MaintenanceWindow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Lebenszyklus geplanter Wartungsfenster (MVP-055): ankündigen,
 * starten, beenden, verlängern, Rollback — mit expliziten erlaubten
 * Übergängen (DoD 022). Ankündigung meldet über die Betriebs-Schiene;
 * Abschluss/Abbruch löst die Aufgabe automatisch auf.
 */
class MaintenanceWindowService {
    /** @var array<string, list<string>> erlaubte Statusübergänge */
    private const TRANSITIONS = [
        MaintenanceWindow::STATUS_PLANNED => [MaintenanceWindow::STATUS_ANNOUNCED, MaintenanceWindow::STATUS_ACTIVE, MaintenanceWindow::STATUS_CANCELLED],
        MaintenanceWindow::STATUS_ANNOUNCED => [MaintenanceWindow::STATUS_ACTIVE, MaintenanceWindow::STATUS_CANCELLED],
        MaintenanceWindow::STATUS_ACTIVE => [MaintenanceWindow::STATUS_COMPLETED, MaintenanceWindow::STATUS_EXTENDED, MaintenanceWindow::STATUS_ROLLED_BACK],
        MaintenanceWindow::STATUS_EXTENDED => [MaintenanceWindow::STATUS_COMPLETED, MaintenanceWindow::STATUS_ROLLED_BACK],
        MaintenanceWindow::STATUS_COMPLETED => [],
        MaintenanceWindow::STATUS_ROLLED_BACK => [],
        MaintenanceWindow::STATUS_CANCELLED => [],
    ];

    public function __construct(private readonly OperationsAlertService $alerts) {}

    /** @param array<string, mixed> $attributes */
    public function plan(array $attributes, ?int $userId = null): MaintenanceWindow {
        $window = MaintenanceWindow::query()->create($attributes + [
            'status' => MaintenanceWindow::STATUS_PLANNED,
            'created_by' => $userId,
        ]);

        if ($window->isAnnouncedUpcoming()) {
            $this->announce($window);
        }

        return $window;
    }

    public function announce(MaintenanceWindow $window): void {
        if ($window->status === MaintenanceWindow::STATUS_PLANNED) {
            $this->transition($window, MaintenanceWindow::STATUS_ANNOUNCED);
        }

        $this->alerts->report(new OperationsSignal(
            type: OperationsTaskType::MaintenanceScheduled,
            dedupeKey: 'maintenance_window:' . $window->getKey(),
            severity: OperationsTaskSeverity::Warning,
            titleKey: 'operations.task.maintenance_scheduled',
            params: [
                // ISO statt fertig formatiert → Anzeige übersetzt/formatiert
                // je Betrachter (NotificationText).
                'from' => $window->starts_at->toIso8601String(),
                'to' => $window->ends_at->toIso8601String(),
                'scope' => $window->message !== null ? ' ' . $window->message : '',
            ],
            organizationId: $window->scope === MaintenanceWindow::SCOPE_ORGANIZATION
                ? (int) $window->organization_id
                : null,
            linkRoute: 'admin.maintenance-windows.index',
        ));
    }

    public function start(MaintenanceWindow $window): void {
        $this->transition($window, MaintenanceWindow::STATUS_ACTIVE);
    }

    public function complete(MaintenanceWindow $window): void {
        $this->transition($window, MaintenanceWindow::STATUS_COMPLETED, [
            'ends_at' => CarbonImmutable::now(),
        ]);
        $this->alerts->resolve('maintenance_window:' . $window->getKey());
    }

    public function extend(MaintenanceWindow $window, CarbonImmutable $newEnd): void {
        if ($newEnd->lessThanOrEqualTo($window->ends_at)) {
            throw new InvalidArgumentException('Verlängerung muss nach dem bisherigen Ende liegen.');
        }
        $this->transition($window, MaintenanceWindow::STATUS_EXTENDED, ['ends_at' => $newEnd]);
    }

    public function rollback(MaintenanceWindow $window, ?string $notes = null): void {
        $this->transition($window, MaintenanceWindow::STATUS_ROLLED_BACK, [
            'ends_at' => CarbonImmutable::now(),
            'notes' => $notes ?? $window->notes,
        ]);
        $this->alerts->resolve('maintenance_window:' . $window->getKey());
    }

    public function cancel(MaintenanceWindow $window): void {
        $this->transition($window, MaintenanceWindow::STATUS_CANCELLED);
        $this->alerts->resolve('maintenance_window:' . $window->getKey());
    }

    /**
     * Zeitgesteuerter Lebenszyklus (operations:scan): Ankündigung fällig,
     * Beginn erreicht, Ende überschritten.
     */
    public function tick(): void {
        foreach (MaintenanceWindow::openWindows() as $window) {
            if ($window->status === MaintenanceWindow::STATUS_PLANNED && $window->isAnnouncedUpcoming()) {
                $this->announce($window);
                continue;
            }
            if (in_array($window->status, [MaintenanceWindow::STATUS_PLANNED, MaintenanceWindow::STATUS_ANNOUNCED], true)
                && $window->isEffectiveNow()) {
                $this->transition($window, MaintenanceWindow::STATUS_ACTIVE);
                continue;
            }
            if (in_array($window->status, [MaintenanceWindow::STATUS_ACTIVE, MaintenanceWindow::STATUS_EXTENDED], true)
                && $window->ends_at->isPast()) {
                $this->transition($window, MaintenanceWindow::STATUS_COMPLETED);
                $this->alerts->resolve('maintenance_window:' . $window->getKey());
            }
        }
    }

    /** @param array<string, mixed> $extra */
    private function transition(MaintenanceWindow $window, string $to, array $extra = []): void {
        $allowed = self::TRANSITIONS[$window->status] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Statuswechsel {$window->status} → {$to} ist nicht erlaubt.");
        }
        $window->update($extra + ['status' => $to]);
    }
}
