<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditTrailSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\{AuditLog, User};
use Illuminate\Database\Eloquent\Model;

/**
 * Audit-Ereignisse mit Bezug zur betroffenen Person: Ereignisse ÜBER die
 * Person (auditable = User) und VON der Person ausgelöste (user_id) — je als
 * Zähler + Zeitraum. Die Hash-Ketten-Einträge selbst bleiben unangetastet.
 */
class AuditTrailSection extends AbstractSubjectSection {
    public function key(): string {
        return 'audit_trail';
    }

    public function title(): string {
        return __('Protokollereignisse (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;
        $orgId = (int) $u->organization_id;

        return ['families' => [
            $this->family(
                'audit_logs_about',
                __('Ereignisse über die Person'),
                AuditLog::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->where('auditable_type', User::class)
                    ->where('auditable_id', $u->id),
                'created_at',
            ),
            $this->family(
                'audit_logs_by',
                __('Ereignisse durch die Person'),
                AuditLog::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->where('user_id', $u->id),
                'created_at',
            ),
        ]];
    }
}
