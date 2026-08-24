<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportContextBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Models\Accounting\AccountingProfile;
use App\Models\Organization;
use App\Services\Accounting\TaxationMethodResolver;
use Carbon\CarbonImmutable;

/**
 * Kopfzeile für Berichtsexporte (Feature 125, MVP-676): Methode, Zeitraum und
 * Datenstand gehören dazu.
 */
class ExportContextBuilder {
    public function __construct(private readonly TaxationMethodResolver $taxation) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        $taxation = $this->taxation->at($organization, $to);

        return [
            'organization' => $organization->name,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'profile' => $profile?->profit_determination->value ?? '—',
            'profile_label' => $profile?->profit_determination->label() ?? '—',
            'currency' => $profile?->base_currency->value ?? 'EUR',
            'taxation' => $taxation->value,
            'taxation_label' => $taxation->label(),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
