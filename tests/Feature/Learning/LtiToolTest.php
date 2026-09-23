<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LtiToolTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Communication\ExternalParticipant;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningLtiPlatform, LearningLtiSubject};
use App\Services\Learning\{LearningCourseService, LearningLtiToolService};
use DateTimeImmutable;
use ELearningToolkit\Lti\{AuthenticationRequest, DeepLinkingResponse, DeepLinkingSettings, IdTokenBuilder, InMemoryNonceStore, Keys, Registration, Roles};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jose\Component\Core\{JWK, JWKSet};
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * WorkDiary als LTI-1.3-Tool (Feature 149, Stufe 9c).
 *
 * Geprüft aus Sicht einer fremden Plattform, gebaut mit den Bausteinen des Toolkits:
 * Login-Anstoß, signiertes ID-Token, Deep Linking.
 */
class LtiToolTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const ISSUER = 'https://lms.example.org';

    private const CLIENT = 'workdiary-client';

    private const DEPLOYMENT = 'deployment-1';

    private const JWKS = 'https://lms.example.org/jwks';

    private static ?JWK $platformKey = null;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        FakePluginHttp::fake([self::JWKS => FakePluginHttp::response(Keys::publicKeySet([self::platformKey()]))]);

        LearningLtiPlatform::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Moodle der Berufsschule',
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT,
            'deployment_ids' => [self::DEPLOYMENT],
            'authorization_endpoint' => self::ISSUER . '/auth',
            'jwks_url' => self::JWKS,
            'is_active' => true,
        ]);
    }

    public function test_the_login_sends_the_browser_to_the_platform(): void {
        $auth = $this->login(route('learning.lti.tool.launch'));

        $this->assertSame(self::CLIENT, $auth['client_id'] ?? null);
        $this->assertSame(route('learning.lti.tool.launch'), $auth['redirect_uri'] ?? null);
        $this->assertNotEmpty($auth['state'] ?? null);
        $this->assertNotEmpty($auth['nonce'] ?? null);
    }

    public function test_a_launch_enrolls_an_external_participant_and_opens_the_course(): void {
        $course = $this->course('Arbeitsschutz extern', true);
        $target = app(LearningLtiToolService::class)->courseTarget($course);

        $this->launchAs('lms-person-17', $target, ['name' => 'Erika Muster'])->assertRedirect(route('learning.external.show'));
        $this->get(route('learning.external.show'))->assertOk()->assertSee('Arbeitsschutz extern');

        $participant = ExternalParticipant::query()->firstOrFail();
        $this->assertSame('Erika Muster', $participant->name);
        $this->assertSame(1, LearningLtiSubject::query()->count());
        $this->assertSame(1, LearningEnrollment::query()->where('external_participant_id', $participant->id)->count());

        // Dieselbe Person ein zweites Mal: kein neuer Teilnehmer, keine zweite Einschreibung.
        $this->launchAs('lms-person-17', $target)->assertRedirect(route('learning.external.show'));
        $this->assertSame(1, ExternalParticipant::query()->count());
        $this->assertSame(1, LearningEnrollment::query()->count());
    }

    public function test_state_counts_once_and_foreign_keys_are_rejected(): void {
        $course = $this->course('Arbeitsschutz extern', true);
        $target = app(LearningLtiToolService::class)->courseTarget($course);

        $auth = $this->login($target);
        $token = $this->idToken($auth, 'lms-person-1', [Roles::LEARNER], $target);
        $this->post(route('learning.lti.tool.launch'), ['id_token' => $token, 'state' => $auth['state']])->assertRedirect();
        $this->post(route('learning.lti.tool.launch'), ['id_token' => $token, 'state' => $auth['state']])->assertStatus(400);

        $foreign = $this->login($target);
        $forged = $this->idToken($foreign, 'lms-person-2', [Roles::LEARNER], $target, Keys::generate('fremd-1'));
        $this->post(route('learning.lti.tool.launch'), ['id_token' => $forged, 'state' => $foreign['state']])->assertStatus(400);

        $this->assertSame(1, ExternalParticipant::query()->count());
    }

    public function test_only_courses_released_for_lti_can_be_launched(): void {
        $course = $this->course('Nur intern', false);

        $this->launchAs('lms-person-3', app(LearningLtiToolService::class)->courseTarget($course))->assertStatus(400);

        $this->assertSame(0, ExternalParticipant::query()->count());
    }

    public function test_deep_linking_offers_released_courses_and_signs_the_choice(): void {
        $offered = $this->course('Arbeitsschutz extern', true);
        $this->course('Nur intern', false);
        $settings = new DeepLinkingSettings('https://lms.example.org/deep-linking/return', ['ltiResourceLink'], ['window', 'iframe'], acceptMultiple: false, data: 'lms-daten');

        $auth = $this->login(route('learning.lti.tool.launch'));
        $token = $this->idToken($auth, 'lms-lehrkraft-1', [Roles::INSTRUCTOR], route('learning.lti.tool.launch'), null, $settings);

        $this->post(route('learning.lti.tool.launch'), ['id_token' => $token, 'state' => $auth['state']])
            ->assertRedirect(route('learning.lti.tool.deep-linking'));
        $this->get(route('learning.lti.tool.deep-linking'))->assertOk()->assertSee('Arbeitsschutz extern')->assertDontSee('Nur intern');

        $answer = $this->post(route('learning.lti.tool.deep-linking.submit'), ['course' => $offered->sqid]);
        $answer->assertOk();
        $this->assertStringContainsString('action="https://lms.example.org/deep-linking/return"', (string) $answer->getContent());
        $this->assertSame(1, preg_match('/name="JWT" value="([A-Za-z0-9_\-.]+)"/', (string) $answer->getContent(), $match));

        $jwks = $this->getJson(route('learning.lti.jwks'))->assertOk()->json();
        $this->assertIsArray($jwks);
        $result = DeepLinkingResponse::validate(
            $match[1] ?? '',
            new Registration(self::ISSUER, self::CLIENT, Keys::keySet($jwks), [self::DEPLOYMENT]),
            self::ISSUER,
            self::DEPLOYMENT,
            $settings,
            new InMemoryNonceStore,
            new DateTimeImmutable,
        );

        $this->assertCount(1, $result['items']);
        $this->assertSame(app(LearningLtiToolService::class)->courseTarget($offered), $result['items'][0]['url'] ?? null);
        $this->assertSame('Arbeitsschutz extern', $result['items'][0]['title'] ?? null);
    }

    public function test_learners_cannot_choose_content(): void {
        $this->course('Arbeitsschutz extern', true);
        $settings = new DeepLinkingSettings('https://lms.example.org/deep-linking/return', ['ltiResourceLink'], ['window']);

        $auth = $this->login(route('learning.lti.tool.launch'));
        $token = $this->idToken($auth, 'lms-person-4', [Roles::LEARNER], route('learning.lti.tool.launch'), null, $settings);

        $this->post(route('learning.lti.tool.launch'), ['id_token' => $token, 'state' => $auth['state']])->assertStatus(400);
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    private static function platformKey(): JWK {
        return self::$platformKey ??= Keys::generate('lms-1');
    }

    private function course(string $title, bool $ltiAvailable): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => $title, 'lti_available' => $ltiAvailable]);
        $courses->addUnit($course, ['title' => 'Grundlagen', 'kind' => LearningUnitKind::Content->value]);
        $courses->release($course->refresh(), null);

        return $course->refresh();
    }

    /** @return array<mixed> Parameter der Authentifizierungsanfrage an die Plattform */
    private function login(string $target): array {
        $response = $this->get(route('learning.lti.tool.login', [
            'iss' => self::ISSUER,
            'login_hint' => 'hinweis-17',
            'target_link_uri' => $target,
            'client_id' => self::CLIENT,
            'lti_deployment_id' => self::DEPLOYMENT,
        ]));
        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(self::ISSUER . '/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * @param  array<mixed>  $auth
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $claims
     */
    private function idToken(array $auth, string $subject, array $roles, string $target, ?JWK $key = null, ?DeepLinkingSettings $settings = null, array $claims = []): string {
        $tool = new Registration(self::ISSUER, self::CLIENT, new JWKSet([]), [self::DEPLOYMENT], null, [route('learning.lti.tool.launch')]);
        $request = AuthenticationRequest::fromParameters($auth, $tool);
        $now = new DateTimeImmutable;

        if ($settings !== null) {
            return IdTokenBuilder::deepLinking(self::ISSUER, $tool, self::DEPLOYMENT, $request, $subject, $roles, $settings, $target, $key ?? self::platformKey(), $now, $claims);
        }

        return IdTokenBuilder::resourceLink(self::ISSUER, $tool, self::DEPLOYMENT, $request, $subject, $roles, $target, 'resource-link-1', $key ?? self::platformKey(), $now, $claims);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function launchAs(string $subject, string $target, array $claims = []): \Illuminate\Testing\TestResponse {
        $auth = $this->login($target);

        return $this->post(route('learning.lti.tool.launch'), [
            'id_token' => $this->idToken($auth, $subject, [Roles::LEARNER], $target, null, null, $claims),
            'state' => $auth['state'],
        ]);
    }
}
