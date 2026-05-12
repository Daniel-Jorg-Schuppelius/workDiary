<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<\Illuminate\Database\Eloquent\Model> */
class OrganizationScope implements Scope {
    public function apply(Builder $builder, Model $model): void {
        if (! app()->bound('currentOrganization')) {
            return;
        }

        /** @var \App\Models\Organization|null $org */
        $org = app('currentOrganization');

        if ($org instanceof \App\Models\Organization) {
            $builder->where($model->getTable() . '.organization_id', $org->id);
        }
    }
}
