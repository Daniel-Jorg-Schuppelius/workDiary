<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentAssetCapitalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Investments\InvestmentCase;
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleUnavailableException;
use App\Services\Investments\Contracts\AssetCapitalizer;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Database\Eloquent\Model;

/** Anlage aus einer Investition (MVP-909) über den Anlagendienst, Quelle = Investition. */
final class InvestmentAssetCapitalizer implements AssetCapitalizer {
    public function __construct(
        private readonly FixedAssetService $assets,
        private readonly FeatureFlagResolver $features,
    ) {}

    public function available(): bool {
        return $this->features->isEnabled('module.finance');
    }

    public function capitalize(InvestmentCase $case, User $actor, array $data): Model {
        if (! $this->available()) {
            throw ModuleUnavailableException::for('finance');
        }

        return $this->assets->create(Organization::query()->findOrFail($case->organization_id), $actor, [
            'name' => $data['name'],
            'acquired_on' => $data['acquired_on'],
            'acquisition_cost' => $data['acquisition_cost'],
            'useful_life_months' => $data['useful_life_months'],
            'source_type' => $case->getMorphClass(),
            'source_id' => $case->id,
        ]);
    }
}
