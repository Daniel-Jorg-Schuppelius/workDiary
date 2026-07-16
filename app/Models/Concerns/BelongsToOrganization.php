<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BelongsToOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Exceptions\MissingOrganizationException;
use App\Models\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically scopes a model to the current organization and populates
 * organization_id on creation when an organization context is active.
 *
 * @property int $organization_id
 *
 * @method static void addGlobalScope(\Illuminate\Database\Eloquent\Scope<\Illuminate\Database\Eloquent\Model>|\Closure $scope)
 * @method static void creating(\Closure $callback)
 */
trait BelongsToOrganization {
    public static function bootBelongsToOrganization(): void {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (self $model): void {
            if (empty($model->organization_id) && app()->bound('currentOrganization')) {
                /** @var Organization|null $org */
                $org = app('currentOrganization');
                if ($org instanceof Organization) {
                    $model->organization_id = $org->id;
                }
            }

            // Fallback: organization_id vom Benutzer ableiten (Konsole/Queue/Test ohne currentOrganization-Bindung).
            if (
                empty($model->organization_id)
                && array_key_exists('user_id', $model->getAttributes())
                && ! empty($model->getAttribute('user_id'))
            ) {
                $owner = User::query()
                    ->whereKey($model->getAttribute('user_id'))
                    ->first();
                if ($owner !== null && ! empty($owner->organization_id)) {
                    $model->organization_id = $owner->organization_id;
                }
            }

            // Verhindert Waisen-Records: eingeloggter Benutzer ohne Org-Zuordnung darf keinen
            // tenant-scoped Datensatz anlegen. Auth-lose Kontexte (Konsole/Queue/Seeder) bleiben unberührt.
            if (
                empty($model->organization_id)
                && Auth::check()
            ) {
                /** @var User|null $authUser */
                $authUser = Auth::user();
                if (
                    $authUser instanceof User
                    && empty($authUser->organization_id)
                ) {
                    throw new MissingOrganizationException(static::class);
                }
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }
}
