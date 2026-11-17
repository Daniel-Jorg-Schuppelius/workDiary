<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServiceWaiverPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\ServicePlan;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Services\Delegation\ActsForResolver;

/**
 * Leistungsplan (Feature 199) — die Rechte hängen am eigenen Datensatz des
 * Plans („entfällt“), gelten aber für die ganze Sicht:
 *
 * - `viewAny`: den Plan öffnen — mit `diary.servicePlan.view` ganz, sonst
 *   jede Person mit Zugang zum Auftragsbuch nur mit ihren Zellen (zuständig
 *   oder in Vertretung, MVP-1648).
 * - `viewAll`: alle Zellen sehen und exportieren (`diary.servicePlan.view`).
 * - `manage`: „entfällt“, Perioden anlegen, Listen importieren, Kundenliste
 *   abgleichen (`diary.servicePlan.manage`).
 * - `annotate`: „Unterlagen da am“ und Bemerkung einer Zelle — mit `manage`
 *   überall, sonst die zuständige Person und wer sie heute vertritt. Die
 *   Vertretung verleiht kein Recht: ohne Zugang zum Plan auch keine Zelle.
 */
class CustomerServiceWaiverPolicy {
    use HasAdminBypass;

    public function __construct(private readonly ActsForResolver $actsFor) {}

    public function viewAny(User $user): bool {
        return $this->viewAll($user)
            || $user->can(Permission::DiaryViewOwn->value)
            || $user->can(Permission::DiaryViewAny->value);
    }

    public function viewAll(User $user): bool {
        return $user->can(Permission::DiaryServicePlanView->value) || $this->manage($user);
    }

    public function manage(User $user): bool {
        return $user->can(Permission::DiaryServicePlanManage->value);
    }

    public function annotate(User $user, ?int $responsibleId = null): bool {
        if ($this->manage($user)) {
            return true;
        }

        return $this->viewAny($user) && $this->actsFor->actsFor($user, $responsibleId);
    }
}
