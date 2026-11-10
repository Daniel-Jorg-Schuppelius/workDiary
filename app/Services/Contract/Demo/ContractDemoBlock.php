<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\Demo;

use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractPartnerType, ContractTermKind, IndexationMethod};
use App\Models\Contract\{Contract, ContractIndexation, ContractObligation};
use App\Models\Platform\Organization;
use App\Services\Contract\ContractService;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;

/**
 * Verträge zur Vorführung (MVP-1533): ein laufender Wartungsvertrag mit dem
 * Hauptkunden (Kündigungsfrist und Jahresgespräch im Fristenkalender), eine
 * unbefristete Softwarelizenz mit jährlicher Zahlung und ein Mietvertrag im
 * Entwurf.
 */
final class ContractDemoBlock implements DemoBlock {
    public function __construct(private readonly ContractService $contracts) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        $customer = $context->mainCustomer;
        if (! $context->moduleActive('module.contracts') || $admin === null || $customer === null) {
            return ['contracts' => 0];
        }
        $organization = $context->organization;
        $today = CarbonImmutable::now(Tz::ofOrganization($organization))->startOfDay();

        $maintenanceStart = $today->subMonths(8)->startOfMonth();
        $maintenance = $this->contracts->create($organization, $admin, [
            'title' => (string) __('demo.contracts.maintenance_title', ['customer' => $customer->name]),
            'kind' => ContractKind::Maintenance,
            'partner_type' => ContractPartnerType::Customer,
            'customer_id' => $customer->id,
            'term_kind' => ContractTermKind::Fixed,
            'starts_on' => $maintenanceStart->toDateString(),
            'ends_on' => $maintenanceStart->addMonths(24)->subDay()->toDateString(),
            'min_term_months' => 24,
            'auto_renew' => true,
            'renew_period_months' => 12,
            'notice_period_days' => 90,
            'indexation_method' => IndexationMethod::None,
            'value_amount' => '390.00',
            'currency' => CurrencyCode::Euro,
            'value_period' => 'monthly',
            'responsible_user_id' => $admin->id,
            'notes' => (string) __('demo.contracts.maintenance_notes'),
        ]);
        $this->contracts->activate($maintenance, $admin);
        $this->contracts->addObligation($maintenance, [
            'kind' => ContractObligationKind::Review,
            'title' => (string) __('demo.contracts.review_title'),
            'due_on' => $today->addDays(25)->toDateString(),
            'warn_days_before' => 30,
            'responsible_user_id' => $admin->id,
        ]);

        $license = $this->contracts->create($organization, $admin, [
            'title' => (string) __('demo.contracts.license_title'),
            'kind' => ContractKind::License,
            'partner_type' => ContractPartnerType::Other,
            'partner_name' => (string) __('demo.contracts.license_partner'),
            'term_kind' => ContractTermKind::OpenEnded,
            'starts_on' => $today->subYears(2)->startOfYear()->toDateString(),
            'min_term_months' => 12,
            'auto_renew' => false,
            'notice_period_days' => 30,
            'indexation_method' => IndexationMethod::None,
            'value_amount' => '1188.00',
            'currency' => CurrencyCode::Euro,
            'value_period' => 'yearly',
            'responsible_user_id' => $admin->id,
        ]);
        $this->contracts->activate($license, $admin);
        $this->contracts->addObligation($license, [
            'kind' => ContractObligationKind::Payment,
            'title' => (string) __('demo.contracts.payment_title'),
            'due_on' => $today->addDays(45)->toDateString(),
            'warn_days_before' => 14,
            'recurring' => true,
            'recurrence_months' => 12,
            'responsible_user_id' => $admin->id,
        ]);

        $this->contracts->create($organization, $admin, [
            'title' => (string) __('demo.contracts.rent_title'),
            'kind' => ContractKind::Rent,
            'partner_type' => ContractPartnerType::Other,
            'partner_name' => (string) __('demo.contracts.rent_partner'),
            'term_kind' => ContractTermKind::Fixed,
            'starts_on' => $today->addMonth()->startOfMonth()->toDateString(),
            'ends_on' => $today->addMonth()->startOfMonth()->addYears(3)->subDay()->toDateString(),
            'auto_renew' => true,
            'renew_period_months' => 12,
            'notice_period_days' => 180,
            'indexation_method' => IndexationMethod::None,
            'value_amount' => '850.00',
            'currency' => CurrencyCode::Euro,
            'value_period' => 'monthly',
            'responsible_user_id' => $admin->id,
        ]);

        return ['contracts' => 3];
    }

    public function purge(Organization $organization): void {
        $contractIds = Contract::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('id');
        ContractObligation::query()->withoutGlobalScopes()->whereIn('contract_id', $contractIds)->delete();
        ContractIndexation::query()->withoutGlobalScopes()->whereIn('contract_id', $contractIds)->delete();
        Contract::query()->withoutGlobalScopes()->whereKey($contractIds)->delete();
    }
}
