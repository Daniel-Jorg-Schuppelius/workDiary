<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConfidentialContractScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract\Scopes;

use App\Enums\Contract\ContractKind;
use App\Models\Platform\User;
use App\Services\Hr\PersonnelFilePermissions as HR;
use Illuminate\Database\Eloquent\{Builder, Model, Scope};
use Illuminate\Support\Facades\Auth;

/**
 * Arbeitsverträge (MVP-939) sieht nur, wer die Personalakte lesen darf.
 * Greift bei angemeldeten Personen; öffentliche Signaturlinks und
 * Hintergrundläufe (ohne Anmeldung) sind nicht betroffen.
 *
 * @implements Scope<Model>
 */
final class ConfidentialContractScope implements Scope {
    public function apply(Builder $builder, Model $model): void {
        $user = Auth::user();
        if ($user instanceof User && ! $user->hasEffectivePermission(HR::VIEW_ANY)) {
            $builder->where($model->qualifyColumn('kind'), '!=', ContractKind::Employment->value);
        }
    }
}
