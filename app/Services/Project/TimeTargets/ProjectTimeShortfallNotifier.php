<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallNotifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\TimeTargets;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Notifications\Project\ProjectTimeShortfallNotification;
use App\Support\CarbonFmt;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Benachrichtigt die Projektleitung über neu entdeckte Befunde (Feature 194,
 * MVP-1469): eine Meldung je Projekt und Tag. Projektleitung sind die
 * zuweisbaren Personen des Projekts mit dem Recht, das Projekt zu bearbeiten;
 * ohne solche die Organisationsnutzer mit diesem Recht. Admins zählen immer.
 */
final class ProjectTimeShortfallNotifier {
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * @param  Collection<int, ProjectTimeShortfall>  $detected
     * @return int Anzahl versendeter Meldungen (Empfänger × Tage)
     */
    public function notify(Project $project, Collection $detected): int {
        if ($detected->isEmpty()) {
            return 0;
        }
        $recipients = $this->recipients($project);
        if ($recipients->isEmpty()) {
            return 0;
        }
        $sent = 0;
        $url = route('projects.show', ['project' => $project, 'tab' => 'time', 'show' => 'shortfalls']);
        foreach ($detected->groupBy(static fn (ProjectTimeShortfall $s): string => $s->date->toDateString()) as $date => $group) {
            $notification = new ProjectTimeShortfallNotification(
                (string) $project->name,
                CarbonFmt::fdate(\Illuminate\Support\Carbon::parse((string) $date)),
                $group->count(),
                $url,
            );
            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
                $sent++;
            }
            ProjectTimeShortfall::query()->whereIn('id', $group->pluck('id')->all())->update(['notified_at' => now()]);
        }

        return $sent;
    }

    /** @return Collection<int, User> */
    private function recipients(Project $project): Collection {
        // Konsole hat keinen Spatie-Team-Kontext; ohne ihn findet hasEffectivePermission keine Org-Rollen.
        $previousTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($project->organization_id);
        try {
            $leads = static fn (User $user): bool => $user->isAdmin() || $user->hasEffectivePermission(Permission::ProjectUpdate->value);
            $members = $project->assignableUsers()->filter($leads)->values();
            if ($members->isNotEmpty()) {
                return $members;
            }

            return User::query()->where('organization_id', $project->organization_id)->get()->filter($leads)->values();
        } finally {
            $this->registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
