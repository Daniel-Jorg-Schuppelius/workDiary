<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwareFeatureResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Tariff;

use App\Enums\Finance\BillingMode;
use App\Enums\Lexoffice\{LexwareCoverage, LexwareFeature};
use App\Enums\User\Permission;
use App\Models\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;

/**
 * Zentrale Auflösung je Funktion (Feature 158, MVP-831): Lexware-Abdeckung
 * aus der Matrix, lokale Verfügbarkeit aus Modulfreigabe, Rechnungshoheit
 * und Benutzerrecht, aktive Zuständigkeit aus dem Tarifprofil. Der Tarif
 * ist keine Berechtigung: eine lokale Funktion wird nie entzogen, nur weil
 * ein höherer Tarif sie ebenfalls bietet.
 */
class LexwareFeatureResolver {
    public function __construct(
        private readonly LexwareTariffService $tariffs,
        private readonly LexwarePlanMatrix $matrix,
        private readonly FeatureFlagResolver $featureFlags,
    ) {}

    /** @return list<FeatureAvailability> */
    public function resolve(Organization $organization, User $user): array {
        $profile = $this->tariffs->profile();
        $plan = $profile->effectivePlan();
        $result = [];
        foreach (LexwareFeature::cases() as $feature) {
            $result[] = $this->resolveFeature($organization, $user, $feature, $profile, $plan);
        }

        return $result;
    }

    public function resolveOne(Organization $organization, User $user, LexwareFeature $feature): FeatureAvailability {
        $profile = $this->tariffs->profile();

        return $this->resolveFeature($organization, $user, $feature, $profile, $profile->effectivePlan());
    }

    /** Rechnungshoheit der Organisation als Standard — Kunden können abweichen (BillingModeResolver). */
    public function organizationBillsLocally(Organization $organization): bool {
        $mode = BillingMode::tryFrom((string) data_get($organization->settings, 'billing_mode', ''));

        return $mode === null || ! $mode->isExternal();
    }

    private function resolveFeature(Organization $organization, User $user, LexwareFeature $feature, LexwareTariffProfile $profile, \App\Enums\Lexoffice\LexwarePlan $plan): FeatureAvailability {
        $coverage = $this->matrix->coverage($plan, $feature);
        $moduleOn = $this->featureFlags->isEnabled('module.vertrieb');
        $billsLocally = $this->organizationBillsLocally($organization);
        $hasRight = $user->can(Permission::InvoiceViewAny->value);
        $localAvailable = $feature->isLocalMvp() && $moduleOn && $billsLocally && $hasRight;
        $localActive = $profile->isLocallyActive($feature);
        $channels = $profile->handoverChannel === LexwareTariffService::CHANNEL_API && $this->matrix->allowsOwnApiKey($plan)
            ? [LexwareTariffService::CHANNEL_MANUAL, LexwareTariffService::CHANNEL_API]
            : [LexwareTariffService::CHANNEL_MANUAL];

        [$state, $reason] = match (true) {
            $coverage === LexwareCoverage::Lexware => [FeatureAvailability::STATE_LEXWARE, 'lexware.reason.included'],
            $coverage === LexwareCoverage::Expansion => [FeatureAvailability::STATE_PLANNED, 'lexware.reason.planned'],
            $coverage === LexwareCoverage::Unknown && $localAvailable && $localActive => [FeatureAvailability::STATE_AVAILABLE, 'lexware.reason.unknown_plan_local'],
            $coverage === LexwareCoverage::Unknown => [FeatureAvailability::STATE_CHECK_AVAILABILITY, 'lexware.reason.unknown_plan'],
            ! $feature->isLocalMvp() => [FeatureAvailability::STATE_PLANNED, 'lexware.reason.planned'],
            ! $moduleOn => [FeatureAvailability::STATE_SETUP_REQUIRED, 'lexware.reason.module_missing'],
            ! $billsLocally => [FeatureAvailability::STATE_SETUP_REQUIRED, 'lexware.reason.billing_external'],
            ! $hasRight => [FeatureAvailability::STATE_SETUP_REQUIRED, 'lexware.reason.right_missing'],
            ! $localActive => [FeatureAvailability::STATE_SETUP_REQUIRED, 'lexware.reason.not_activated'],
            default => [FeatureAvailability::STATE_AVAILABLE, null],
        };

        return new FeatureAvailability(
            feature: $feature,
            coverage: $coverage,
            state: $state,
            localAvailable: $localAvailable,
            localActive: $localActive,
            reasonKey: $reason,
            actionRoute: $feature->localRoute(),
            actionParameters: $feature->localRouteParameters(),
            handoverChannels: $channels,
        );
    }
}
