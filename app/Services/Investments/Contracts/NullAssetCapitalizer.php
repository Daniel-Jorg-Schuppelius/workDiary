<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullAssetCapitalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments\Contracts;

use App\Models\Investments\InvestmentCase;
use App\Models\Platform\User;
use App\Modules\ModuleUnavailableException;
use Illuminate\Database\Eloquent\Model;

final class NullAssetCapitalizer implements AssetCapitalizer {
    public function available(): bool {
        return false;
    }

    public function capitalize(InvestmentCase $case, User $actor, array $data): Model {
        throw ModuleUnavailableException::for('finance');
    }
}
