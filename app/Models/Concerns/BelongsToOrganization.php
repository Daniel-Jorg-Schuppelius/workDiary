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

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }
}
