<?php

namespace App\Scopes;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Auto-scopes every query on the model to the currently authenticated
 * organization. Attach this scope to any tenant-owned model.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->has(Organization::class)) {
            $organization = app(Organization::class);
            $builder->where($model->getTable().'.organization_id', $organization->id);
        }
    }
}
