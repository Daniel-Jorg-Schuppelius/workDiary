<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Scopes;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
class OrganizationScope implements Scope {
    public function apply(Builder $builder, Model $model): void {
        if (! app()->bound('currentOrganization')) {
            return;
        }

        /** @var Organization|null $org */
        $org = app('currentOrganization');

        if ($org instanceof Organization) {
            $builder->where($model->getTable() . '.organization_id', $org->id);
        }
    }
}
