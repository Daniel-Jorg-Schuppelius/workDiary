<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LtiDeepLinkingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningCourse, LearningLtiLink, LearningLtiTool, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningLtiPlatformService};
use DateTimeImmutable;
use ELearningToolkit\Lti\{ContentItem, DeepLinkingResponse, DeepLinkingSettings, InMemoryNonceStore, Keys, LaunchValidator, LoginInitiation, MessageType, Registration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Jose\Component\Core\JWK;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * LTI 1.3 Deep Linking als Plattform (Feature 149, Stufe 9b).
 *
 * Die Autorin wählt beim Tool aus; das Tool schickt die Auswahl signiert zurück.
 * Geprüft aus Sicht des Tools, mit den Bausteinen des Toolkits.
 */
class LtiDeepLinkingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const REDIRECT = 'https://tool.example.org/lti/launch';

    private static ?JWK $toolKey = null;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_an_author_selects_content_at_the_tool_and_it_is_linked(): void {
        [$course, $unit, $tool, $author] = $this->scenario();
        [$platform, $settings] = $this->deepLinkingRequest($course, $unit, $tool, $author, 'dl-nonce-1');

        $this->assertSame(['ltiResourceLink'], $settings->acceptTypes);
        $this->assertFalse($settings->acceptMultiple);

        $response = DeepLinkingResponse::build($platform, $tool->deployment_id, $settings, [
            ContentItem::ltiResourceLink('https://tool.example.org/kurse/42', 'Kapitel 42', null, ['kapitel' => '42']),
        ], self::toolKey(), 'antwort-nonce-1', new DateTimeImmutable);

        // Der Rücksprung ist ein fremder POST ohne Sitzung.
        $this->app['auth']->forgetGuards();
        $this->post($response['returnUrl'], [DeepLinkingResponse::FORM_PARAMETER => $response['jwt']])
            ->assertRedirect(route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid]));

        $link = LearningLtiLink::query()->where('learning_unit_id', $unit->id)->firstOrFail();
        $this->assertSame($tool->id, $link->learning_lti_tool_id);
        $this->assertSame('https://tool.example.org/kurse/42', $link->url);
        $this->assertSame('Kapitel 42', $link->title);
        $this->assertSame(['kapitel' => '42'], $link->custom);
        $this->assertTrue(Str::isUuid($link->resource_link_id));

        $this->post($response['returnUrl'], [DeepLinkingResponse::FORM_PARAMETER => $response['jwt']])
            ->assertStatus(400);
    }

    public function test_foreign_keys_and_forged_states_link_nothing(): void {
        [$course, $unit, $tool, $author] = $this->scenario();
        [$platform, $settings] = $this->deepLinkingRequest($course, $unit, $tool, $author, 'dl-nonce-2');
        $items = [ContentItem::ltiResourceLink('https://tool.example.org/kurse/7')];
        $this->app['auth']->forgetGuards();

        $foreign = DeepLinkingResponse::build($platform, $tool->deployment_id, $settings, $items, Keys::generate('tool-1'), 'antwort-nonce-2', new DateTimeImmutable);
        $this->post($foreign['returnUrl'], [DeepLinkingResponse::FORM_PARAMETER => $foreign['jwt']])->assertStatus(400);

        $genuine = DeepLinkingResponse::build($platform, $tool->deployment_id, $settings, $items, self::toolKey(), 'antwort-nonce-3', new DateTimeImmutable);
        $this->post(route('learning.lti.deep-linking.return', ['zustand' => 'gefaelscht.abc']), [DeepLinkingResponse::FORM_PARAMETER => $genuine['jwt']])->assertStatus(400);

        $this->assertSame(0, LearningLtiLink::query()->count());
    }

    public function test_only_course_authors_may_select_content(): void {
        [$course, $unit, $tool] = $this->scenario();
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($learner)
            ->post(route('learning.courses.units.lti.deep-linking', ['course' => $course->sqid, 'unit' => $unit->sqid]), ['tool' => $tool->sqid])
            ->assertForbidden();
    }

    public function test_a_link_can_be_set_by_hand_and_removed(): void {
        [$course, $unit, $tool, $author] = $this->scenario();
        $route = ['course' => $course->sqid, 'unit' => $unit->sqid];

        $this->actingAs($author)->post(route('learning.courses.units.lti.store', $route), [
            'tool' => $tool->sqid,
            'title' => 'Kapitel 1',
            'url' => 'https://tool.example.org/kurse/1',
            'custom' => "abschnitt=1\n modus = pruefung \nkaputt\n=leer",
        ])->assertRedirect(route('learning.courses.units.edit', $route));

        $link = LearningLtiLink::query()->where('learning_unit_id', $unit->id)->firstOrFail();
        $this->assertSame(['abschnitt' => '1', 'modus' => 'pruefung'], $link->custom);

        $other = $this->tool('Zweites Tool');
        $this->actingAs($author)->post(route('learning.courses.units.lti.store', $route), ['tool' => $other->sqid])->assertRedirect();
        $relinked = LearningLtiLink::query()->where('learning_unit_id', $unit->id)->firstOrFail();
        $this->assertNotSame($link->resource_link_id, $relinked->resource_link_id, 'Ein anderes Tool heißt eine andere Ressource.');

        $this->actingAs($author)->delete(route('learning.courses.units.lti.destroy', $route))->assertRedirect();
        $this->assertSame(0, LearningLtiLink::query()->count());
    }

    public function test_editor_and_learner_view_show_the_lti_unit(): void {
        [$course, $unit, $tool, $author] = $this->scenario();

        $this->actingAs($author)
            ->get(route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid]))
            ->assertOk()
            ->assertSee($tool->name)
            ->assertSee((string) __('learning.action.lti_select'));

        app(LearningLtiPlatformService::class)->saveLink($unit, $tool, 'Kapitel 1', null, []);
        $courses = app(LearningCourseService::class);
        $courses->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee(route('learning.my.lti.launch', [$enrollment, $unit]), false);
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    private static function toolKey(): JWK {
        return self::$toolKey ??= Keys::generate('tool-1');
    }

    /** @return array{LearningCourse, LearningUnit, LearningLtiTool, User} */
    private function scenario(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'LTI-Kurs']);
        $unit = $courses->addUnit($course, ['title' => 'Brandschutz im Tool', 'kind' => LearningUnitKind::Lti->value]);
        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        return [$course->refresh(), $unit->refresh(), $this->tool('Brandschutz-Tool'), $author];
    }

    private function tool(string $name): LearningLtiTool {
        return LearningLtiTool::query()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'client_id' => 'client-' . Str::lower(Str::random(10)),
            'deployment_id' => 'deployment-1',
            'login_url' => 'https://tool.example.org/lti/login',
            'launch_url' => 'https://tool.example.org/lti/launch',
            'deep_linking_url' => 'https://tool.example.org/lti/deep-linking',
            'redirect_uris' => [self::REDIRECT],
            'public_jwks' => json_encode(Keys::publicKeySet([self::toolKey()])),
            'is_active' => true,
        ]);
    }

    /**
     * Die Autorin startet die Auswahl; das Tool liest Login-Anstoß und ID-Token.
     *
     * @return array{Registration, DeepLinkingSettings}
     */
    private function deepLinkingRequest(LearningCourse $course, LearningUnit $unit, LearningLtiTool $tool, User $author, string $nonce): array {
        $start = $this->actingAs($author)->post(route('learning.courses.units.lti.deep-linking', ['course' => $course->sqid, 'unit' => $unit->sqid]), ['tool' => $tool->sqid]);
        $start->assertRedirect();
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        $login = LoginInitiation::fromParameters($query);
        $this->assertSame('https://tool.example.org/lti/deep-linking', $login->targetLinkUri);

        $platform = $this->platform($tool);
        $auth = $this->actingAs($author)->get($login->authenticationRequestUrl($platform, self::REDIRECT, 'zustand-1', $nonce));
        $auth->assertOk();

        $message = (new LaunchValidator(new InMemoryNonceStore))->validate($this->idToken($auth), $platform, new DateTimeImmutable, $nonce);
        $this->assertSame(MessageType::DeepLinkingRequest, $message->type);
        $this->assertNotNull($message->deepLinkingSettings);

        return [$platform, $message->deepLinkingSettings];
    }

    private function platform(LearningLtiTool $tool): Registration {
        $jwks = $this->getJson(route('learning.lti.jwks'))->assertOk()->json();
        $this->assertIsArray($jwks);

        return new Registration(LearningLtiPlatformService::issuer(), $tool->client_id, Keys::keySet($jwks), [$tool->deployment_id], route('learning.lti.platform.auth'));
    }

    /** @param  TestResponse<Response>  $response */
    private function idToken(TestResponse $response): string {
        $this->assertSame(1, preg_match('/name="id_token" value="([A-Za-z0-9_\-.]+)"/', (string) $response->getContent(), $match));

        return $match[1] ?? '';
    }
}
