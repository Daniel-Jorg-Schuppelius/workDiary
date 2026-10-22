<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OwnActionsReportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Access;

use App\Enums\User\{Permission, UserRole};
use App\Models\Platform\{Organization, User};
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Nach der Rechte-Umstellung (Vollscan 2026-10-21, lk-06) prüfen die Policies
 * auch eigene Zeiten, Urlaub, Stempeln und Fahrten über Rechte. Die
 * Standardrollen bekommen sie beim Deploy; eigene Rollen, `training_manager`
 * und Konten ohne Rolle nicht. Der Bericht nennt, wen das trifft — er ändert nichts.
 */
class OwnActionsReportCommand extends Command {
    protected $signature = 'permissions:own-actions-report {--organization= : nur diese Organisation (ID)}';

    protected $description = 'Listet aktive Mitarbeiterkonten, denen Rechte für eigene Zeiten, Stundenzettel, Urlaub, Stempeln oder Fahrten fehlen';

    private const OWN_ACTIONS = [
        Permission::TimeEntryCreate,
        Permission::TimesheetCreate,
        Permission::VacationRequest,
        Permission::AttendanceManage,
        Permission::TravelLogManage,
    ];

    public function handle(PermissionRegistrar $registrar): int {
        $organizations = Organization::query()
            ->when($this->option('organization') !== null, fn ($query) => $query->whereKey((int) $this->option('organization')))
            ->orderBy('id')
            ->get();

        $rows = [];
        $previousTeamId = $registrar->getPermissionsTeamId();
        try {
            foreach ($organizations as $organization) {
                // Ohne Team-Kontext fände hasEffectivePermission keine Org-Rollen.
                $registrar->setPermissionsTeamId($organization->id);
                $users = User::query()
                    ->where('organization_id', $organization->id)
                    ->whereNull('customer_id')
                    ->whereNull('deactivated_at')
                    ->orderBy('name')
                    ->get();
                foreach ($users as $user) {
                    // Admins dürfen alles; der Anbieter-Support ist bewusst nur lesend.
                    if ($user->isAdmin() || $user->hasRole(UserRole::Support->value)) {
                        continue;
                    }
                    $missing = array_values(array_filter(self::OWN_ACTIONS, static fn (Permission $permission): bool => ! $user->hasEffectivePermission($permission->value)));
                    if ($missing !== []) {
                        $rows[] = [
                            (string) $organization->name,
                            (string) $user->name,
                            (string) $user->email,
                            implode(', ', $user->getRoleNames()->all()) ?: '—',
                            implode(', ', array_map(static fn (Permission $permission): string => $permission->value, $missing)),
                        ];
                    }
                }
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        if ($rows === []) {
            $this->info('Alle aktiven Mitarbeiterkonten dürfen ihre eigenen Zeiten, Stundenzettel, Urlaube, Stempelungen und Fahrten erfassen.');

            return self::SUCCESS;
        }

        $this->table(['Organisation', 'Name', 'E-Mail', 'Rollen', 'Fehlende Rechte'], $rows);
        $this->warn(sprintf('%d Konten fehlen Rechte für eigene Vorgänge — Rolle zuweisen oder die eigene Rolle im Rollen-Editor ergänzen.', count($rows)));

        return self::SUCCESS;
    }
}
