<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectBillingRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LexofficeArticle;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Plugins\Lexoffice\LexofficeMapper;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Project\ProjectStatus;

class ProjectBillingRuleTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'hourly_rate' => 80,
        ]);
    }

    private function makeProject(array $attrs = []): Project
    {
        return Project::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'P '.uniqid('', true),
            'status' => ProjectStatus::Active->value,
        ], $attrs));
    }

    public function test_fallback_rule_matches_any_kind(): void
    {
        $project = $this->makeProject();
        $rule = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => null,
            'item_type' => 'service',
            'unit_name' => 'Stunde',
        ]);

        $resolved = $project->resolveBillingRule(TimeEntryKind::Work->value);
        $this->assertNotNull($resolved);
        $this->assertSame((int) $rule->id, (int) $resolved->id);
    }

    public function test_kind_specific_rule_wins_over_fallback(): void
    {
        $project = $this->makeProject();
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => null,
            'item_type' => 'service',
        ]);
        $travel = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => TimeEntryKind::Travel->value,
            'item_type' => 'service',
            'unit_name' => 'Kilometer',
        ]);

        $resolved = $project->resolveBillingRule(TimeEntryKind::Travel->value);
        $this->assertSame((int) $travel->id, (int) $resolved?->id);
    }

    public function test_sub_project_inherits_rule_from_parent_and_can_override(): void
    {
        $parent = $this->makeProject();
        $parent->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => null,
            'item_type' => 'service',
            'unit_name' => 'Stunde-Parent',
        ]);

        $child = Project::create([
            'organization_id' => $this->organization->id,
            'parent_id' => $parent->id,
            'name' => 'Sub',
            'status' => ProjectStatus::Active->value,
        ]);

        $resolved = $child->resolveBillingRule(TimeEntryKind::Work->value);
        $this->assertSame('Stunde-Parent', $resolved?->unit_name);

        $childRule = $child->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => TimeEntryKind::Work->value,
            'item_type' => 'service',
            'unit_name' => 'Stunde-Child',
        ]);

        $child->refresh();
        $resolved = $child->resolveBillingRule(TimeEntryKind::Work->value);
        $this->assertSame((int) $childRule->id, (int) $resolved?->id);
        $this->assertSame('Stunde-Child', $resolved?->unit_name);
    }

    public function test_higher_priority_rule_wins(): void
    {
        $project = $this->makeProject();
        $low = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => TimeEntryKind::Work->value,
            'item_type' => 'service',
            'priority' => 1,
        ]);
        $high = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => TimeEntryKind::Work->value,
            'item_type' => 'service',
            'priority' => 10,
        ]);

        $resolved = $project->resolveBillingRule(TimeEntryKind::Work->value);
        $this->assertSame((int) $high->id, (int) $resolved?->id);
    }

    public function test_mapper_renders_article_id_and_unit_from_rule(): void
    {
        $project = $this->makeProject();
        LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'art-1',
            'name' => 'Beratung',
            'type' => 'service',
            'currency' => 'EUR',
        ]);
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => null,
            'lexoffice_article_id' => 'art-1',
            'item_type' => 'service',
            'unit_name' => 'Beratungs-Stunde',
            'vat_rate' => 7.0,
            'net_unit_price' => 123.45,
        ]);

        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'kind' => TimeEntryKind::Work->value,
            'started_at' => CarbonImmutable::parse('2026-05-01 09:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-01 11:00'),
            'minutes' => 120,
            'rate' => 200.0,
            'billable' => true,
        ]);

        $mapper = new LexofficeMapper;
        $payload = $mapper->timeEntriesToVoucherPayload(
            $this->customer,
            collect([$entry->fresh(['project'])]),
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertNotEmpty($payload['voucherItems']);
        $item = $payload['voucherItems'][0];
        $this->assertSame('art-1', $item['id']);
        $this->assertSame('Beratungs-Stunde', $item['unitName']);
        $this->assertSame(7.0, $item['unitPrice']['taxRatePercentage']);
        $this->assertSame(123.45, $item['unitPrice']['netAmount']);
    }
}
