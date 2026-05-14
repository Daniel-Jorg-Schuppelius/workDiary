<?php

namespace App\Models\Scopes;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('currentOrganization')) {
            return;
        }

        /** @var Organization|null $org */
        $org = app('currentOrganization');

        if ($org instanceof Organization) {
            $builder->where($model->getTable().'.organization_id', $org->id);
        }
    }
}
