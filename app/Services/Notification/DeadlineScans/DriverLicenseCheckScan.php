<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DriverLicenseCheckScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{DriverLicenseCheck, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Führerscheinkontrolle (MVP-417): jüngste Kontrolle je Fahrer mit
 * Fälligkeit innerhalb des Vorlaufs (--expiring-days) oder überfällig.
 * Empfänger: der Fahrer selbst (notify_affected) plus Teamleitung
 * (Fuhrparkverantwortung). Dedup pro Kontrolle über das
 * notification_dispatch_log; überfällige Kontrollen sperren zusätzlich
 * die Fahrzeugreservierung (VehicleReservationService-Guard).
 */
class DriverLicenseCheckScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'driver_license_checks';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($options->expiringDays);

        // Jüngste Kontrolle je Fahrer (ältere Zeilen sind Historie).
        $latestIds = DriverLicenseCheck::query()
            ->withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        return $this->runScan($dispatcher, [
            'affected' => fn(DriverLicenseCheck $check): ?User => $check->user,
            'require_affected' => true,
            'due' => [
                'query' => fn() => DriverLicenseCheck::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $latestIds)
                    ->whereDate('next_due_on', '<=', $horizon->toDateString())
                    ->with('user:id,name,organization_id')
                    ->orderBy('id'),
                'event' => NotificationEvent::DriverLicenseCheckDue,
                'payload' => function (DriverLicenseCheck $check) use ($today): array {
                    $name = (string) $check->user?->name;
                    $overdue = $today->greaterThan($check->next_due_on);

                    return [
                        'title' => $overdue
                            ? (string) __('Führerscheinkontrolle überfällig: :name', ['name' => $name])
                            : (string) __('Führerscheinkontrolle fällig: :name', ['name' => $name]),
                        'title_key' => $overdue
                            ? 'Führerscheinkontrolle überfällig: :name'
                            : 'Führerscheinkontrolle fällig: :name',
                        'title_params' => ['name' => $name],
                        'url' => route('driver-license-checks.index'),
                        'due_at' => $check->next_due_on,
                    ];
                },
            ],
        ]);
    }
}
