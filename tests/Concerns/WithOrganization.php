<?php

namespace Tests\Concerns;

use App\Models\Organization;

/**
 * Sets up a default Organization and binds it as 'currentOrganization'
 * in the service container so that BelongsToOrganization / OrganizationScope
 * work correctly inside feature tests.
 *
 * Usage:
 *   use Tests\Concerns\WithOrganization;
 *   // in setUp() or directly in a test:
 *   $this->setUpOrganization();
 */
trait WithOrganization
{
    protected Organization $organization;

    protected function setUpOrganization(?array $attributes = []): void
    {
        $this->organization = Organization::factory()->create($attributes);
        app()->instance('currentOrganization', $this->organization);
    }
}
