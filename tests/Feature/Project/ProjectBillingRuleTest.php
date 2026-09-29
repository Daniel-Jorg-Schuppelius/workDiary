<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectBillingRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Project;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Events\Article\ArticlesMerged;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalArticleMapping;
use App\Models\Platform\{Organization, User};
use App\Models\Plugins\Lexoffice\LexofficeArticle;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Lexoffice\LexofficeMapper;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ProjectBillingRuleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
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

    private function makeProject(array $attrs = []): Project {
        return Project::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'P ' . uniqid('', true),
            'status' => ProjectStatus::Active->value,
        ], $attrs));
    }

    public function test_fallback_rule_matches_any_kind(): void {
        $project = $this->makeProject();
        $rule = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'unit_name' => 'Stunde',
        ]);

        $resolved = $project->resolveBillingRule(TimeEntryKind::Work->value);
        $this->assertNotNull($resolved);
        $this->assertSame((int) $rule->id, (int) $resolved->id);
    }

    public function test_kind_specific_rule_wins_over_fallback(): void {
        $project = $this->makeProject();
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
        ]);
        $travel = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => TimeEntryKind::Travel->value,
            'item_type' => 'service',
            'unit_name' => 'Kilometer',
        ]);

        $resolved = $project->resolveBillingRule(TimeEntryKind::Travel->value);
        $this->assertSame((int) $travel->id, (int) $resolved?->id);
    }

    public function test_sub_project_inherits_rule_from_parent_and_can_override(): void {
        $parent = $this->makeProject();
        $parent->billingRules()->create([
            'organization_id' => $this->organization->id,
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
            'applies_to_kind' => TimeEntryKind::Work->value,
            'item_type' => 'service',
            'unit_name' => 'Stunde-Child',
        ]);

        $child->refresh();
        $resolved = $child->resolveBillingRule(TimeEntryKind::Work->value);
        $this->assertSame((int) $childRule->id, (int) $resolved?->id);
        $this->assertSame('Stunde-Child', $resolved?->unit_name);
    }

    public function test_higher_priority_rule_wins(): void {
        $project = $this->makeProject();
        $low = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => TimeEntryKind::Work->value,
            'item_type' => 'service',
            'priority' => 1,
        ]);
        $high = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => TimeEntryKind::Work->value,
            'item_type' => 'service',
            'priority' => 10,
        ]);

        $resolved = $project->resolveBillingRule(TimeEntryKind::Work->value);
        $this->assertSame((int) $high->id, (int) $resolved?->id);
    }

    public function test_mapper_renders_article_id_and_unit_from_rule(): void {
        $project = $this->makeProject();
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'art-1',
            'name' => 'Beratung',
            'type' => 'service',
            'currency' => 'EUR',
        ]);
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'article_ref' => 'lex:' . $article->id,
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

    public function test_project_show_renders_billing_tab_for_billing_manager(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $project = $this->makeProject();
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => TimeEntryKind::Work->value,
            'item_type' => 'service',
            'priority' => 5,
        ]);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('Taktung & Zusammenfassung'))
            ->assertSee(__('invoicing.service_rules.title'));
    }

    public function test_project_show_hides_billing_tab_for_regular_user(): void {
        $project = $this->makeProject();

        $this->actingAs($this->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee(__('Taktung & Zusammenfassung'));
    }

    public function test_billing_rule_create_dialog_renders(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $project = $this->makeProject();

        $this->actingAs($admin)
            ->get(route('projects.billing-rules.create', $project) . '?dialog=1')
            ->assertOk()
            ->assertSee(__('Neue Abrechnungs-Regel'));
    }

    public function test_rule_stores_catalog_key_of_local_article_and_rejects_foreign_article(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $project = $this->makeProject();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Wartung', 'sellable' => true]);
        $foreignOrg = Organization::factory()->create();
        $foreign = Article::factory()->create(['organization_id' => $foreignOrg->id, 'name' => 'Fremd', 'sellable' => true]);

        $this->actingAs($admin)
            ->get(route('projects.billing-rules.create', $project) . '?dialog=1')
            ->assertOk()
            ->assertSee('art:' . $article->sqid)
            ->assertDontSee('Fremd');

        $this->actingAs($admin)
            ->post(route('projects.billing-rules.store', $project), ['article' => 'art:' . $foreign->sqid, 'item_type' => 'service'])
            ->assertSessionHasErrors('article');

        $this->actingAs($admin)
            ->post(route('projects.billing-rules.store', $project), ['article' => 'art:' . $article->sqid, 'item_type' => 'service'])
            ->assertRedirect();

        $rule = $project->billingRules()->sole();
        $this->assertSame('art:' . $article->id, $rule->article_ref);

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertSee('Wartung');
    }

    public function test_mapper_translates_local_article_to_lexoffice_id_via_mapping(): void {
        $project = $this->makeProject();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Wartung', 'sellable' => true]);
        ExternalArticleMapping::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'external_id' => 'lx-uuid-7',
            'article_id' => $article->id,
            'sync_status' => 'linked',
        ]);
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'article_ref' => 'art:' . $article->id,
            'item_type' => 'service',
        ]);
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'kind' => TimeEntryKind::Work->value,
            'started_at' => CarbonImmutable::parse('2026-05-01 09:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-01 10:00'),
            'minutes' => 60,
            'rate' => 90.0,
            'billable' => true,
        ]);

        $payload = (new LexofficeMapper)->timeEntriesToVoucherPayload(
            $this->customer,
            collect([$entry->fresh(['project'])]),
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertSame('lx-uuid-7', $payload['voucherItems'][0]['id']);
    }

    public function test_article_merge_repoints_rule_and_default_service(): void {
        $project = $this->makeProject();
        $source = Article::factory()->create(['organization_id' => $this->organization->id]);
        $target = Article::factory()->create(['organization_id' => $this->organization->id]);
        $rule = $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'article_ref' => 'art:' . $source->id,
            'item_type' => 'service',
        ]);
        $this->organization->forceFill(['settings' => ['invoicing' => ['default_service_article' => 'art:' . $source->id]]])->save();

        event(new ArticlesMerged((int) $this->organization->id, (int) $source->id, (int) $target->id));

        $this->assertSame('art:' . $target->id, $rule->fresh()?->article_ref);
        $this->assertSame('art:' . $target->id, data_get($this->organization->fresh()?->settings, 'invoicing.default_service_article'));
    }
}
