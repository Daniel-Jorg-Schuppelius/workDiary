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

use App\Enums\Asset\MaintenanceWindowStatus;
use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\Asset\MaintenanceWindow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Lebenszyklus geplanter Wartungsfenster (MVP-055): ankündigen,
 * starten, beenden, verlängern, Rollback — mit expliziten erlaubten
 * Übergängen (DoD 022). Ankündigung meldet über die Betriebs-Schiene;
 * Abschluss/Abbruch löst die Aufgabe automatisch auf.
 */
class MaintenanceWindowService {
    public function __construct(private readonly OperationsAlertService $alerts) {}

    /** @param array<string, mixed> $attributes */
    public function plan(array $attributes, ?int $userId = null): MaintenanceWindow {
        $window = MaintenanceWindow::query()->create($attributes + [
            'status' => MaintenanceWindowStatus::Planned,
            'created_by' => $userId,
        ]);

        if ($window->isAnnouncedUpcoming()) {
            $this->announce($window);
        }

        return $window;
    }

    public function announce(MaintenanceWindow $window): void {
        if ($window->status === MaintenanceWindowStatus::Planned) {
            $this->transition($window, MaintenanceWindowStatus::Announced);
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
        $this->transition($window, MaintenanceWindowStatus::Active);
    }

    public function complete(MaintenanceWindow $window): void {
        $this->transition($window, MaintenanceWindowStatus::Completed, [
            'ends_at' => CarbonImmutable::now(),
        ]);
        $this->alerts->resolve('maintenance_window:' . $window->getKey());
    }

    public function extend(MaintenanceWindow $window, CarbonImmutable $newEnd): void {
        if ($newEnd->lessThanOrEqualTo($window->ends_at)) {
            throw new InvalidArgumentException('Verlängerung muss nach dem bisherigen Ende liegen.');
        }
        $this->transition($window, MaintenanceWindowStatus::Extended, ['ends_at' => $newEnd]);
    }

    public function rollback(MaintenanceWindow $window, ?string $notes = null): void {
        $this->transition($window, MaintenanceWindowStatus::RolledBack, [
            'ends_at' => CarbonImmutable::now(),
            'notes' => $notes ?? $window->notes,
        ]);
        $this->alerts->resolve('maintenance_window:' . $window->getKey());
    }

    public function cancel(MaintenanceWindow $window): void {
        $this->transition($window, MaintenanceWindowStatus::Cancelled);
        $this->alerts->resolve('maintenance_window:' . $window->getKey());
    }

    /**
     * Zeitgesteuerter Lebenszyklus (operations:scan): Ankündigung fällig,
     * Beginn erreicht, Ende überschritten.
     */
    public function tick(): void {
        foreach (MaintenanceWindow::openWindows() as $window) {
            if ($window->status === MaintenanceWindowStatus::Planned && $window->isAnnouncedUpcoming()) {
                $this->announce($window);
                continue;
            }
            if (in_array($window->status, [MaintenanceWindowStatus::Planned, MaintenanceWindowStatus::Announced], true)
                && $window->isEffectiveNow()) {
                $this->transition($window, MaintenanceWindowStatus::Active);
                continue;
            }
            if (in_array($window->status, [MaintenanceWindowStatus::Active, MaintenanceWindowStatus::Extended], true)
                && $window->ends_at->isPast()) {
                $this->transition($window, MaintenanceWindowStatus::Completed);
                $this->alerts->resolve('maintenance_window:' . $window->getKey());
            }
        }
    }

    /** @param array<string, mixed> $extra */
    private function transition(MaintenanceWindow $window, MaintenanceWindowStatus $to, array $extra = []): void {
        if (! $window->status->canTransitionTo($to)) {
            throw new InvalidArgumentException("Statuswechsel {$window->status->value} → {$to->value} ist nicht erlaubt.");
        }
        $window->update($extra + ['status' => $to]);
    }
}
