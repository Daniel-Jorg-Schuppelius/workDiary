<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsCertificateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Models\Isms\{IsmsCertificate, IsmsNormStatus};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsCertificate>
 */
class IsmsCertificateFactory extends Factory {
    protected $model = IsmsCertificate::class;

    public function definition(): array {
        return [
            'isms_norm_status_id' => IsmsNormStatus::factory(),
            'certified_organization' => fake()->company(),
            'scope_description' => fake()->sentence(8),
            'certification_body' => fake()->company() . ' Cert',
            'certificate_no' => strtoupper(fake()->unique()->bothify('CERT-####-??')),
            'issued_on' => now()->subMonths(2)->toDateString(),
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addYears(3)->toDateString(),
            'surveillance_audit_1_on' => null,
            'surveillance_audit_2_on' => null,
            'document_id' => null,
            'notes' => null,
        ];
    }

    /** Abgelaufenes Zertifikat (valid_until in der Vergangenheit). */
    public function expired(): self {
        return $this->state(fn() => [
            'issued_on' => now()->subYears(4)->toDateString(),
            'valid_from' => now()->subYears(4)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }

    /** Zertifikat läuft innerhalb der nächsten X Tage ab. */
    public function expiringInDays(int $days): self {
        return $this->state(fn() => [
            'issued_on' => now()->subYears(3)->toDateString(),
            'valid_from' => now()->subYears(3)->toDateString(),
            'valid_until' => now()->addDays($days)->toDateString(),
        ]);
    }
}
