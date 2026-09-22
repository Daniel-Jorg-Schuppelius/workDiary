<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwareTariffProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Tariff;

use App\Enums\Lexoffice\{LexwareFeature, LexwarePlan};
use Carbon\CarbonImmutable;

/**
 * Tarifprofil einer Organisation (Feature 158): gebuchter Tarif mit Quelle
 * und Bestätigungsdatum, optionale Testfrist mit bestätigtem Folgetarif,
 * gewählter Übergabeweg und die bewusst lokal aktivierten Ergänzungen.
 * Reines Wertobjekt aus den Org-Einstellungen `lexware.*`.
 */
final class LexwareTariffProfile {
    /**
     * @param list<LexwareFeature> $localFeatures
     */
    public function __construct(
        public readonly LexwarePlan $plan,
        public readonly string $source,
        public readonly ?CarbonImmutable $confirmedOn,
        public readonly ?CarbonImmutable $trialEndsOn,
        public readonly LexwarePlan $trialSuccessorPlan,
        public readonly string $handoverChannel,
        public readonly array $localFeatures,
    ) {}

    public function isLocallyActive(LexwareFeature $feature): bool {
        return in_array($feature, $this->localFeatures, true);
    }

    /** Testzugang abgelaufen: der bestätigte Folgetarif gilt, sonst „unbekannt". */
    public function effectivePlan(?CarbonImmutable $today = null): LexwarePlan {
        $today ??= CarbonImmutable::today();
        if ($this->trialEndsOn !== null && $this->trialEndsOn->lt($today)) {
            return $this->trialSuccessorPlan;
        }

        return $this->plan;
    }

    public function isTrialExpired(?CarbonImmutable $today = null): bool {
        return $this->trialEndsOn !== null && $this->trialEndsOn->lt($today ?? CarbonImmutable::today());
    }
}
