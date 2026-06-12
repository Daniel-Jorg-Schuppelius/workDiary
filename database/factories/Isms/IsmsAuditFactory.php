<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{AuditKind, AuditStatus};
use App\Models\Isms\{IsmsAudit, IsmsScope};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsAudit>
 */
class IsmsAuditFactory extends Factory {
    protected $model = IsmsAudit::class;

    public function definition(): array {
        return [
            'isms_scope_id' => IsmsScope::factory(),
            'audit_no' => fake()->unique()->numberBetween(1, 999999),
            'title' => 'Internes Audit ' . fake()->numberBetween(2026, 2030),
            'norm' => 'ISO/IEC 27001',
            'edition' => '2022',
            'kind' => AuditKind::Internal->value,
            'status' => AuditStatus::Planned->value,
            'planned_on' => now()->addMonth()->toDateString(),
            'performed_from' => null,
            'performed_to' => null,
            'lead_auditor_user_id' => null,
            'auditors' => null,
            'criteria' => fake()->sentence(8),
            'independence_note' => null,
            'summary' => null,
        ];
    }

    public function status(AuditStatus $status): self {
        return $this->state(fn() => ['status' => $status->value]);
    }

    /** Laufendes Audit (Feststellungen erfassbar). */
    public function inProgress(): self {
        return $this->status(AuditStatus::InProgress);
    }

    /** Audit mit Durchführungszeitraum + Zusammenfassung (reportIssued-fähig). */
    public function performed(): self {
        return $this->state(fn() => [
            'status' => AuditStatus::InProgress->value,
            'performed_from' => now()->subDays(3)->toDateString(),
            'performed_to' => now()->subDay()->toDateString(),
            'summary' => 'Audit ohne wesentliche Abweichungen durchgeführt.',
        ]);
    }
}
