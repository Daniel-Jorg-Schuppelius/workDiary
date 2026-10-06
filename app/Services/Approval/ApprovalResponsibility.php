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

/**
 * Wer eine Freigabestufe im Genehmigungs-Eingang entscheiden darf: die
 * genannte Person, die genannte Rolle oder — bei Stufen nach Art — die
 * Rolle, die die Organisation der Stufe dieser Art zuordnet.
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
            default => ($role = $this->mappedRole($approval)) !== null && $user->hasRole($role->value),
        };
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
