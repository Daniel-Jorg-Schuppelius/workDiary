<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LtiRegistrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\{LearningLtiPlatform, LearningLtiTool};
use App\Models\Platform\User;
use ELearningToolkit\Lti\Keys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** LTI-1.3-Registrierungen der Organisation (Feature 149): Tools und Plattformen. */
class LtiRegistrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_the_page_shows_what_a_tool_operator_needs(): void {
        $this->actingAs($this->manager())
            ->get(route('learning.lti-registrations.index'))
            ->assertOk()
            ->assertSee(route('learning.lti.jwks'))
            ->assertSee(route('learning.lti.platform.auth'))
            ->assertSee(route('learning.lti.deep-linking.return'));
    }

    public function test_only_learning_managers_reach_the_registrations(): void {
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($learner)->get(route('learning.lti-registrations.index'))->assertForbidden();
        $this->actingAs($learner)->post(route('learning.lti-registrations.tools.store'), $this->toolInput())->assertForbidden();

        $this->assertSame(0, LearningLtiTool::query()->count());
    }

    public function test_a_tool_gets_its_ids_and_clean_redirect_uris(): void {
        $this->actingAs($this->manager())
            ->post(route('learning.lti-registrations.tools.store'), $this->toolInput())
            ->assertRedirect(route('learning.lti-registrations.index'));

        $tool = LearningLtiTool::query()->firstOrFail();
        $this->assertTrue(Str::isUuid($tool->client_id), 'Die Client-ID vergibt WorkDiary.');
        $this->assertNotSame('', $tool->deployment_id);
        $this->assertSame(['https://tool.example.org/lti/launch', 'https://tool.example.org/lti/deep'], $tool->redirect_uris);
        $this->assertTrue($tool->share_name);
        $this->assertFalse($tool->share_email);
        $this->assertTrue($tool->is_active);
        $this->assertSame($this->organization->id, $tool->organization_id);
    }

    public function test_a_tool_needs_keys_and_complete_addresses(): void {
        $manager = $this->manager();
        $store = route('learning.lti-registrations.tools.store');

        $this->actingAs($manager)->post($store, $this->toolInput(['jwks_url' => null]))->assertSessionHasErrors('jwks_url');
        $this->actingAs($manager)->post($store, $this->toolInput(['jwks_url' => null, 'public_jwks' => '{"keys":[]}']))->assertSessionHasErrors('public_jwks');
        $this->actingAs($manager)->post($store, $this->toolInput(['redirect_uris' => 'tool/launch']))->assertSessionHasErrors('redirect_uris');
        $this->assertSame(0, LearningLtiTool::query()->count());

        $this->actingAs($manager)
            ->post($store, $this->toolInput(['jwks_url' => null, 'public_jwks' => json_encode(Keys::publicKeySet([Keys::generate('tool-1')]))]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame(1, LearningLtiTool::query()->count());
    }

    public function test_editing_keeps_the_ids_and_deleting_removes_the_tool(): void {
        $manager = $this->manager();
        $this->actingAs($manager)->post(route('learning.lti-registrations.tools.store'), $this->toolInput());
        $tool = LearningLtiTool::query()->firstOrFail();
        $clientId = $tool->client_id;

        $this->actingAs($manager)->get(route('learning.lti-registrations.tools.edit', $tool))->assertOk()->assertSee($clientId);
        $this->actingAs($manager)
            ->put(route('learning.lti-registrations.tools.update', $tool), $this->toolInput(['name' => 'Umbenannt', 'is_active' => null]))
            ->assertRedirect(route('learning.lti-registrations.index'));

        $tool->refresh();
        $this->assertSame('Umbenannt', $tool->name);
        $this->assertSame($clientId, $tool->client_id);
        $this->assertFalse($tool->is_active);

        $this->actingAs($manager)->delete(route('learning.lti-registrations.tools.destroy', $tool))->assertRedirect();
        $this->assertSame(0, LearningLtiTool::query()->count());
    }

    public function test_a_platform_is_registered_once_per_issuer_and_client(): void {
        $manager = $this->manager();
        $input = [
            'name' => 'Moodle der Berufsschule',
            'issuer' => 'https://moodle.example.org',
            'client_id' => 'abc123',
            'deployment_ids' => "1\n2\n1",
            'authorization_endpoint' => 'https://moodle.example.org/mod/lti/auth.php',
            'jwks_url' => 'https://moodle.example.org/mod/lti/certs.php',
            'is_active' => '1',
        ];

        $this->actingAs($manager)->post(route('learning.lti-registrations.platforms.store'), $input)->assertRedirect(route('learning.lti-registrations.index'));
        $platform = LearningLtiPlatform::query()->firstOrFail();
        $this->assertSame(['1', '2'], $platform->deployment_ids);
        $this->assertSame(LearningLtiPlatform::lookupHash('https://moodle.example.org', 'abc123'), $platform->lookup_hash);

        $this->actingAs($manager)->post(route('learning.lti-registrations.platforms.store'), $input)->assertSessionHasErrors('client_id');
        $this->assertSame(1, LearningLtiPlatform::query()->count());

        $this->actingAs($manager)->get(route('learning.lti-registrations.platforms.edit', $platform))->assertOk()->assertSee('abc123');
        $this->actingAs($manager)
            ->put(route('learning.lti-registrations.platforms.update', $platform), ['name' => 'Moodle neu'] + $input)
            ->assertRedirect();
        $this->assertSame('Moodle neu', $platform->refresh()->name);

        $this->actingAs($manager)->delete(route('learning.lti-registrations.platforms.destroy', $platform))->assertRedirect();
        $this->assertSame(0, LearningLtiPlatform::query()->count());
    }

    public function test_the_course_catalog_links_managers_to_the_registrations(): void {
        $this->actingAs($this->manager())
            ->get(route('learning.courses.index'))
            ->assertOk()
            ->assertSee(route('learning.lti-registrations.index'), false);
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function toolInput(array $overrides = []): array {
        return $overrides + [
            'name' => 'Brandschutz-Tool',
            'login_url' => 'https://tool.example.org/lti/login',
            'launch_url' => 'https://tool.example.org/lti/launch',
            'redirect_uris' => "https://tool.example.org/lti/launch\n\nhttps://tool.example.org/lti/launch\nhttps://tool.example.org/lti/deep",
            'jwks_url' => 'https://tool.example.org/lti/jwks',
            'share_name' => '1',
            'is_active' => '1',
        ];
    }
}
