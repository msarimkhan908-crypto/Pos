<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Scopes\OrganizationScope;

/**
 * Add to any tenant-owned model to:
 *  1. Auto-scope all queries to the current organization.
 *  2. Auto-fill organization_id on create.
 */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (self $model): void {
            if (empty($model->organization_id) && app()->has(Organization::class)) {
                $model->organization_id = app(Organization::class)->id;
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
