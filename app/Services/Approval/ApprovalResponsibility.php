<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalResponsibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\ApprovalStepKind;
use App\Enums\User\UserRole;
use App\Models\Approval\Approval;
use App\Models\Platform\{Organization, User};
use App\Settings\SettingsRegistry;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wer eine Freigabestufe im Genehmigungs-Eingang entscheiden darf: die
 * genannte Person, die genannte Rolle, wer das genannte Recht hat, oder — bei
 * Stufen nach Art — die Rolle, die die Organisation der Stufe dieser Art zuordnet.
 */
class ApprovalResponsibility {
    /** @var array<string, UserRole|null> „Org-Id:Einstellung“ → Rolle, je Anfrage */
    private array $mappedRoles = [];

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function isResponsible(Approval $approval, User $user): bool {
        $rule = (array) $approval->approver_rule;

        return match ((string) ($rule['type'] ?? '')) {
            'user' => (int) ($rule['value'] ?? 0) === (int) $user->id,
            'role' => $user->hasRole((string) ($rule['value'] ?? '')),
            // Stufe nach Recht (MVP-1626), etwa die Freigabe eines Negativbestands.
            'permission' => $user->can((string) ($rule['value'] ?? '')),
            default => ($role = $this->mappedRole($approval)) !== null && $user->hasRole($role->value),
        };
    }

    /**
     * Wer die Stufe entscheiden darf — Empfänger der Benachrichtigung einer
     * Stufe (MVP-1522). Rollen gelten in der Organisation der Stufe, auch
     * ohne Anfrage-Kontext (Konsole, Queue).
     *
     * @return Collection<int, User>
     */
    public function responsibleUsers(Approval $approval): Collection {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId((int) $approval->organization_id);
        try {
            return User::query()
                ->where('organization_id', (int) $approval->organization_id)
                ->orderBy('id')
                ->get()
                ->filter(fn (User $user): bool => $this->isResponsible($approval, $user))
                ->values()
                ->toBase();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /** Rolle, die die Organisation der Stufenart zuordnet; null ohne Stufenart oder Zuordnung. */
    public function mappedRole(Approval $approval): ?UserRole {
        $setting = $this->kindOf($approval)?->roleSetting();
        if ($setting === null) {
            return null;
        }

        // Die Organisation der Stufe zählt, nicht die gerade gewählte des Nutzers.
        $cacheKey = $approval->organization_id . ':' . $setting;
        if (! array_key_exists($cacheKey, $this->mappedRoles)) {
            $organization = Organization::query()->find($approval->organization_id);
            // Leer oder unbekannt heißt Vorgabe — wie im Organisationsformular; ein auf
            // der Einstellungsseite geleertes Feld ließe die Stufe sonst ohne Rolle.
            $this->mappedRoles[$cacheKey] = $organization === null
                ? null
                : UserRole::tryFrom((string) $this->settings->effective($setting, $organization)->value)
                    ?? UserRole::tryFrom((string) config($setting));
        }

        return $this->mappedRoles[$cacheKey];
    }

    public function kindOf(Approval $approval): ?ApprovalStepKind {
        return ApprovalStepKind::tryFrom((string) data_get($approval->approver_rule, 'rule.kind', ''));
    }
}
