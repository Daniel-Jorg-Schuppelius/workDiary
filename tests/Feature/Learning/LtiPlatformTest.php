<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LtiPlatformTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningEnrollment, LearningLtiLink, LearningLtiTool, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningLtiPlatformService};
use DateTimeImmutable;
use ELearningToolkit\Lti\{InMemoryNonceStore, Keys, LaunchValidator, LoginInitiation, MessageType, Registration, Roles};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Jose\Component\Core\JWK;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * WorkDiary als LTI-1.3-Plattform (Feature 149, Stufe 9b).
 *
 * Geprüft aus Sicht des Tools: Es liest den Login-Anstoß, stellt die
 * Authentifizierungsanfrage und prüft das ID-Token mit dem Toolkit gegen die
 * veröffentlichte JWKS — so, wie ein fremdes Tool es täte.
 */
class LtiPlatformTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const REDIRECT = 'https://tool.example.org/lti/launch';

    private static ?JWK $toolKey = null;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_the_launch_hands_the_tool_a_login_initiation_it_accepts(): void {
        [$enrollment, $learner, $unit, $link, $tool] = $this->scenario();

        $login = $this->loginInitiation($this->launch($enrollment, $learner, $unit));

        $this->assertSame(LearningLtiPlatformService::issuer(), $login->issuer);
        $this->assertSame($tool->client_id, $login->clientId);
        $this->assertSame($tool->deployment_id, $login->deploymentId);
        $this->assertSame($tool->launch_url, $login->targetLinkUri);
        $this->assertNotNull($login->messageHint);
        $this->assertNotSame('', $link->resource_link_id);
    }

    public function test_the_id_token_passes_the_tools_launch_validation(): void {
        [$enrollment, $learner, $unit, $link, $tool] = $this->scenario();
        $login = $this->loginInitiation($this->launch($enrollment, $learner, $unit));

        $response = $this->actingAs($learner)->get($login->authenticationRequestUrl($this->platform($tool), self::REDIRECT, 'zustand-1', 'nonce-1'));

        $response->assertOk();
        $this->assertStringContainsString('form-action https://tool.example.org', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('action="' . self::REDIRECT . '"', (string) $response->getContent());
        $this->assertStringContainsString('name="state" value="zustand-1"', (string) $response->getContent());

        $message = (new LaunchValidator(new InMemoryNonceStore))->validate($this->idToken($response), $this->platform($tool), new DateTimeImmutable, 'nonce-1', $tool->launch_url);

        $this->assertSame(MessageType::ResourceLinkRequest, $message->type);
        $this->assertSame($learner->sqid, $message->subject);
        $this->assertSame($link->resource_link_id, $message->resourceLinkId);
        $this->assertTrue(Roles::includesAny($message->roles, Roles::LEARNER));
        $this->assertArrayNotHasKey('name', $message->claims, 'Ohne Freigabe kein Name.');
        $this->assertArrayNotHasKey('email', $message->claims, 'Ohne Freigabe keine Mail.');
        $this->assertSame('LTI-Kurs', $message->context()['title'] ?? null);
    }

    public function test_name_and_email_go_to_the_tool_only_when_released(): void {
        [$enrollment, $learner, $unit, , $tool] = $this->scenario(['share_name' => true, 'share_email' => true]);
        $login = $this->loginInitiation($this->launch($enrollment, $learner, $unit));

        $response = $this->actingAs($learner)->get($login->authenticationRequestUrl($this->platform($tool), self::REDIRECT, 'zustand-1', 'nonce-2'));
        $message = (new LaunchValidator(new InMemoryNonceStore))->validate($this->idToken($response), $this->platform($tool), new DateTimeImmutable, 'nonce-2');

        $this->assertSame($learner->name, $message->claims['name'] ?? null);
        $this->assertSame($learner->email, $message->claims['email'] ?? null);
    }

    public function test_unregistered_redirects_and_foreign_login_hints_get_no_token(): void {
        [$enrollment, $learner, $unit, , $tool] = $this->scenario();
        $login = $this->loginInitiation($this->launch($enrollment, $learner, $unit));

        $this->actingAs($learner)
            ->get($login->authenticationRequestUrl($this->platform($tool), 'https://angreifer.example.org/fang', 'z', 'n'))
            ->assertStatus(400)
            ->assertDontSee('name="id_token"', false);

        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)
            ->get($login->authenticationRequestUrl($this->platform($tool), self::REDIRECT, 'z', 'n'))
            ->assertStatus(400)
            ->assertDontSee('name="id_token"', false);
    }

    public function test_a_cross_site_post_without_session_is_relayed_once(): void {
        [$enrollment, $learner, $unit, , $tool] = $this->scenario();
        $login = $this->loginInitiation($this->launch($enrollment, $learner, $unit));
        parse_str((string) parse_url($login->authenticationRequestUrl($this->platform($tool), self::REDIRECT, 'z', 'n'), PHP_URL_QUERY), $parameters);

        $this->app['auth']->forgetGuards();
        $relay = $this->post(route('learning.lti.platform.auth'), $parameters);

        $relay->assertOk();
        $this->assertStringContainsString("form-action 'self'", (string) $relay->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('name="_relay" value="1"', (string) $relay->getContent());
        $this->assertStringNotContainsString('name="id_token"', (string) $relay->getContent(), 'Die Weiterleitung gibt noch kein Token heraus.');

        $this->post(route('learning.lti.platform.auth'), $parameters + ['_relay' => '1'])->assertRedirect();
    }

    public function test_inactive_tools_and_foreign_enrollments_do_not_launch(): void {
        [$enrollment, $learner, $unit, , $tool] = $this->scenario();
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)
            ->post(route('learning.my.lti.launch', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]))
            ->assertNotFound();

        $tool->forceFill(['is_active' => false])->save();
        $this->actingAs($learner)
            ->post(route('learning.my.lti.launch', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]))
            ->assertNotFound();
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    private static function toolKey(): JWK {
        return self::$toolKey ??= Keys::generate('tool-1');
    }

    /**
     * @param  array<string, mixed>  $toolOverrides
     * @return array{LearningEnrollment, User, LearningUnit, LearningLtiLink, LearningLtiTool}
     */
    private function scenario(array $toolOverrides = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'LTI-Kurs']);
        $unit = $courses->addUnit($course, ['title' => 'Brandschutz im Tool', 'kind' => LearningUnitKind::Lti->value]);

        $tool = LearningLtiTool::query()->create($toolOverrides + [
            'organization_id' => $this->organization->id,
            'name' => 'Brandschutz-Tool',
            'client_id' => 'client-' . Str::lower(Str::random(10)),
            'deployment_id' => 'deployment-1',
            'login_url' => 'https://tool.example.org/lti/login',
            'launch_url' => 'https://tool.example.org/lti/launch',
            'redirect_uris' => [self::REDIRECT],
            'public_jwks' => json_encode(Keys::publicKeySet([self::toolKey()])),
            'is_active' => true,
        ]);

        $link = LearningLtiLink::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'learning_lti_tool_id' => $tool->id,
            'resource_link_id' => Str::uuid()->toString(),
        ]);

        $courses->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        return [$enrollment, $learner, $unit->refresh(), $link, $tool];
    }

    private function launch(LearningEnrollment $enrollment, User $learner, LearningUnit $unit): string {
        $response = $this->actingAs($learner)->post(route('learning.my.lti.launch', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]));
        $response->assertRedirect();

        return (string) $response->headers->get('Location');
    }

    private function loginInitiation(string $location): LoginInitiation {
        $this->assertStringStartsWith('https://tool.example.org/lti/login?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return LoginInitiation::fromParameters($query);
    }

    /** Die Plattform, wie das Tool sie registriert hätte — mit der veröffentlichten JWKS. */
    private function platform(LearningLtiTool $tool): Registration {
        $jwks = $this->getJson(route('learning.lti.jwks'))->assertOk()->json();
        $this->assertIsArray($jwks);

        return new Registration(
            LearningLtiPlatformService::issuer(),
            $tool->client_id,
            Keys::keySet($jwks),
            [$tool->deployment_id],
            route('learning.lti.platform.auth'),
        );
    }

    /** @param  TestResponse<Response>  $response */
    private function idToken(TestResponse $response): string {
        $this->assertSame(1, preg_match('/name="id_token" value="([A-Za-z0-9_\-.]+)"/', (string) $response->getContent(), $match));

        return $match[1] ?? '';
    }
}
