<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5RuntimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningProgressStatus, LearningUnitKind};
use App\Models\Learning\{LearningCmi5Package, LearningCmi5Registration, LearningCmi5Session, LearningCmi5Unit, LearningEnrollment, LearningUnit, LearningUnitProgress, LearningXapiDocument, LearningXapiStatement};
use App\Models\Platform\User;
use App\Services\Learning\{LearningCmi5Runtime, LearningCmi5Service, LearningCourseService, LearningEnrollmentService};
use ELearningToolkit\Cmi5\Cmi5;
use ELearningToolkit\XApi\Verbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * cmi5-Laufzeit (Feature 149): Start, Fetch-URL, LRS und Erfüllung.
 *
 * Geprüft aus Sicht einer AU: Sie weiß nur, was Start-URL und LMS.LaunchData
 * hergeben — und was gegen die Sitzung verstößt, prallt ab.
 */
class Cmi5RuntimeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        foreach ($this->cleanup as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_launch_prepares_the_session_and_hands_the_au_its_parameters(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();
        $intro = $this->au($package, 0);

        $au = $this->start($enrollment, $learner, $unit, $intro);

        $this->assertStringStartsWith('https://content.example.org/einleitung/index.html?', $au['location']);
        parse_str((string) parse_url($au['location'], PHP_URL_QUERY), $params);
        $this->assertSame(LearningCmi5Runtime::endpoint(), $params['endpoint'] ?? null);
        $this->assertSame($intro->activity_id, $au['activityId']);
        $this->assertSame(['homePage' => rtrim((string) config('app.url'), '/'), 'name' => $learner->sqid], $au['actor']['account'] ?? null);

        $registration = LearningCmi5Registration::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();
        $session = LearningCmi5Session::query()->where('learning_cmi5_registration_id', $registration->id)->firstOrFail();
        $extensions = $au['context']['extensions'] ?? null;
        $this->assertIsArray($extensions);
        $this->assertSame($registration->registration, $au['registration']);
        $this->assertSame($session->session_id, $extensions[Cmi5::EXTENSION_SESSION_ID] ?? null);
        $this->assertNotNull($session->fetch_used_at);

        $this->assertTrue(LearningXapiStatement::query()->where('verb', Verbs::LAUNCHED)->where('object_id', $intro->activity_id)->exists());
        $this->assertTrue(LearningXapiDocument::query()->where('kind', LearningXapiDocument::KIND_AGENT_PROFILE)->where('document_id', Cmi5::PROFILE_LEARNER_PREFERENCES)->exists());
    }

    public function test_the_fetch_url_hands_out_its_token_exactly_once(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();
        $au = $this->start($enrollment, $learner, $unit, $this->au($package, 0));

        $this->postJson($au['fetch'])->assertOk()->assertJson(['error-code' => '1']);
        $this->postJson(LearningCmi5Runtime::endpoint() . 'fetch/' . Str::random(64))->assertOk()->assertJson(['error-code' => '2']);
    }

    public function test_the_lrs_needs_the_session_token_and_the_xapi_version(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();
        $au = $this->start($enrollment, $learner, $unit, $this->au($package, 0));

        $this->getJson($this->lrs('about'))->assertOk()->assertJson(['version' => ['1.0.3']])->assertHeader('Access-Control-Allow-Origin', '*');
        $this->getJson($this->lrs('statements'), ['X-Experience-API-Version' => '1.0.3'])->assertUnauthorized()->assertHeader('X-Experience-API-Version', '1.0.3');
        $this->getJson($this->lrs('statements'), ['Authorization' => 'Basic ' . Str::random(48), 'X-Experience-API-Version' => '1.0.3'])->assertUnauthorized();
        $this->getJson($this->lrs('statements'), ['Authorization' => $au['headers']['Authorization']])->assertStatus(400)->assertJson(['error' => 'unsupported_version']);
        $this->getJson($this->lrs('statements'), $au['headers'])->assertOk()->assertJsonStructure(['statements', 'more']);

        // Ohne eigene Preflight-Route antwortete Laravel selbst — ohne CORS-Header.
        $preflight = $this->call('OPTIONS', $this->lrs('statements'), [], [], [], $this->transformHeadersToServerVars([
            'Origin' => 'https://content.example.org',
            'Access-Control-Request-Method' => 'POST',
        ]));
        $preflight->assertNoContent();
        $this->assertStringContainsString('Authorization', (string) $preflight->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_satisfying_every_au_satisfies_block_and_course_and_completes_the_unit(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();

        $intro = $this->start($enrollment, $learner, $unit, $this->au($package, 0));
        $this->send($intro, $this->statement($intro, Verbs::INITIALIZED))->assertOk();
        $this->send($intro, $this->statement($intro, Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT5M']))->assertOk();
        $this->send($intro, $this->statement($intro, Verbs::TERMINATED, ['duration' => 'PT5M']))->assertOk();

        $this->assertFalse($this->unitCompleted($enrollment, $unit), 'Die Prüfung steht noch aus.');

        $test = $this->start($enrollment, $learner, $unit, $this->au($package, 1));
        $this->assertStringContainsString('modus=pruefung', $test['location']);
        $this->send($test, $this->statement($test, Verbs::INITIALIZED))->assertOk();

        // Unter dem masteryScore gibt es kein passed — die AU darf es nicht behaupten.
        $this->send($test, $this->statement($test, Verbs::PASSED, ['success' => true, 'score' => ['scaled' => 0.5], 'duration' => 'PT3M']))
            ->assertForbidden()
            ->assertJson(['error' => 'mastery_score_violated']);
        $this->assertFalse($this->unitCompleted($enrollment, $unit));

        $this->send($test, $this->statement($test, Verbs::PASSED, ['success' => true, 'score' => ['scaled' => 0.9], 'duration' => 'PT4M']))->assertOk();

        $registration = LearningCmi5Registration::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertNotNull($registration->satisfied_at);

        $block = ($package->blocks ?? [])[0] ?? null;
        $this->assertNotNull($block);

        foreach ([$block['activity_id'], $package->activity_id] as $activityId) {
            $this->assertSame(1, LearningXapiStatement::query()->where('verb', Verbs::SATISFIED)->where('object_id', $activityId)->count());
        }

        $this->assertTrue($this->unitCompleted($enrollment, $unit));
    }

    public function test_foreign_actors_duplicates_and_statements_after_terminated_are_handled(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();
        $au = $this->start($enrollment, $learner, $unit, $this->au($package, 0));

        $foreign = $this->statement($au, Verbs::INITIALIZED);
        $foreign['actor'] = ['objectType' => 'Agent', 'account' => ['homePage' => 'https://example.org', 'name' => 'jemand']];
        $this->send($au, $foreign)->assertForbidden()->assertJson(['error' => 'actor_mismatch']);

        $this->send($au, $this->statement($au, Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M']))
            ->assertForbidden()
            ->assertJson(['error' => 'not_initialized']);

        $initialized = $this->statement($au, Verbs::INITIALIZED);
        $this->send($au, $initialized)->assertOk();
        // Doppelte Zustellung ist bei xAPI normal und ändert nichts.
        $this->send($au, $initialized)->assertOk();
        $this->assertSame(1, LearningXapiStatement::query()->where('verb', Verbs::INITIALIZED)->count());

        $conflict = $initialized;
        $conflict['verb'] = ['id' => Verbs::TERMINATED];
        $this->send($au, $conflict)->assertStatus(409);

        $this->send($au, $this->statement($au, Verbs::TERMINATED, ['duration' => 'PT1M']))->assertOk();
        $this->send($au, $this->statement($au, Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M']))
            ->assertForbidden()
            ->assertJson(['error' => 'session_ended']);
    }

    public function test_launch_data_stays_read_only_while_own_state_documents_round_trip(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();
        $au = $this->start($enrollment, $learner, $unit, $this->au($package, 0));
        $query = ['activityId' => $au['activityId'], 'agent' => $au['agent'], 'registration' => $au['registration']];
        $server = $this->transformHeadersToServerVars($au['headers'] + ['Content-Type' => 'application/json']);

        $this->call('PUT', $this->lrs('activities/state', $query + ['stateId' => Cmi5::STATE_LAUNCH_DATA]), [], [], [], $server, '{}')->assertForbidden();
        $this->call('PUT', $this->lrs('activities/state', $query + ['stateId' => 'fortschritt']), [], [], [], $server, '{"seite":3}')->assertNoContent();
        $this->call('POST', $this->lrs('activities/state', $query + ['stateId' => 'fortschritt']), [], [], [], $server, '{"notiz":"a"}')->assertNoContent();

        $document = $this->getJson($this->lrs('activities/state', $query + ['stateId' => 'fortschritt']), $au['headers'])->assertOk();
        $this->assertSame(['seite' => 3, 'notiz' => 'a'], $document->json());
        $this->assertNotNull($document->headers->get('ETag'));

        $ids = $this->getJson($this->lrs('activities/state', $query), $au['headers'])->assertOk()->json();
        $this->assertIsArray($ids);
        $this->assertEqualsCanonicalizing([Cmi5::STATE_LAUNCH_DATA, 'fortschritt'], $ids);

        $other = (string) json_encode(['objectType' => 'Agent', 'account' => ['homePage' => 'https://example.org', 'name' => 'jemand']]);
        $this->getJson($this->lrs('activities/state', ['agent' => $other] + $query + ['stateId' => 'fortschritt']), $au['headers'])->assertForbidden();

        // Alles löschen heißt: alles außer den Startdaten des LMS.
        $this->call('DELETE', $this->lrs('activities/state', $query), [], [], [], $server)->assertNoContent();
        $this->getJson($this->lrs('activities/state', $query), $au['headers'])->assertOk()->assertExactJson([Cmi5::STATE_LAUNCH_DATA]);
    }

    public function test_learner_preferences_are_only_replaced_with_a_matching_etag(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();
        $au = $this->start($enrollment, $learner, $unit, $this->au($package, 0));
        $url = $this->lrs('agents/profile', ['agent' => $au['agent'], 'profileId' => Cmi5::PROFILE_LEARNER_PREFERENCES]);
        $body = '{"languagePreference":"en-US","audioPreference":"off"}';

        $etag = (string) $this->getJson($url, $au['headers'])->assertOk()->headers->get('ETag');

        $this->call('PUT', $url, [], [], [], $this->transformHeadersToServerVars($au['headers'] + ['Content-Type' => 'application/json']), $body)->assertStatus(409);
        $this->call('PUT', $url, [], [], [], $this->transformHeadersToServerVars($au['headers'] + ['Content-Type' => 'application/json', 'If-Match' => '"falsch"']), $body)->assertStatus(412);
        $this->call('PUT', $url, [], [], [], $this->transformHeadersToServerVars($au['headers'] + ['Content-Type' => 'application/json', 'If-Match' => $etag]), $body)->assertNoContent();

        $this->assertSame('en-US', $this->getJson($url, $au['headers'])->assertOk()->json('languagePreference'));
    }

    public function test_a_new_launch_abandons_the_open_session(): void {
        [$enrollment, $learner, $unit, $package] = $this->scenario();
        $first = $this->start($enrollment, $learner, $unit, $this->au($package, 0));
        $this->send($first, $this->statement($first, Verbs::INITIALIZED))->assertOk();

        $this->start($enrollment, $learner, $unit, $this->au($package, 0));

        $this->assertSame(1, LearningCmi5Session::query()->whereNotNull('abandoned_at')->count());
        $this->assertTrue(LearningXapiStatement::query()->where('verb', Verbs::ABANDONED)->exists());
        // Die abgebrochene Sitzung nimmt nichts mehr an.
        $this->send($first, $this->statement($first, Verbs::TERMINATED, ['duration' => 'PT1M']))->assertUnauthorized();
    }

    public function test_a_cmi5_unit_cannot_be_completed_by_hand(): void {
        [$enrollment, $learner, $unit] = $this->scenario();

        $this->actingAs($learner)
            ->post(route('learning.my.units.complete', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]))
            ->assertForbidden();

        $this->assertFalse($this->unitCompleted($enrollment, $unit));
    }

    public function test_only_the_enrolled_learner_can_launch(): void {
        [$enrollment, , $unit, $package] = $this->scenario();
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)
            ->post(route('learning.my.cmi5.launch', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid, 'au' => $this->au($package, 0)->sqid]))
            ->assertNotFound();

        $this->assertSame(0, LearningCmi5Session::query()->count());
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    private function structure(): string {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<courseStructure xmlns="https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd">
  <course id="https://example.org/kurse/arbeitsschutz">
    <title><langstring lang="de-DE">Arbeitsschutz</langstring></title>
  </course>
  <au id="https://example.org/au/einleitung" moveOn="Completed">
    <title><langstring lang="de-DE">Einleitung</langstring></title>
    <url>https://content.example.org/einleitung/index.html</url>
  </au>
  <block id="https://example.org/block/pruefung">
    <title><langstring lang="de-DE">Prüfung</langstring></title>
    <au id="https://example.org/au/test" moveOn="Passed" masteryScore="0.8">
      <title><langstring lang="de-DE">Test</langstring></title>
      <url>https://content.example.org/test/index.html?modus=pruefung</url>
    </au>
  </block>
</courseStructure>
XML;
    }

    /** @return array{LearningEnrollment, User, LearningUnit, LearningCmi5Package} */
    private function scenario(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'cmi5-Kurs']);
        $unit = $courses->addUnit($course, ['title' => 'Arbeitsschutz', 'kind' => LearningUnitKind::Cmi5->value]);

        $base = (string) tempnam(sys_get_temp_dir(), 'cmi5');
        $path = $base . '.xml';
        $this->cleanup[] = $base;
        $this->cleanup[] = $path;
        file_put_contents($path, $this->structure());

        $package = app(LearningCmi5Service::class)->import($unit, $path, 'cmi5.xml');
        $courses->release($course, null);

        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        return [$enrollment, $learner, $unit->refresh(), $package];
    }

    private function au(LearningCmi5Package $package, int $position): LearningCmi5Unit {
        $au = $package->units()->where('position', $position)->first();
        $this->assertInstanceOf(LearningCmi5Unit::class, $au);

        return $au;
    }

    /**
     * AU starten und anmelden, wie es die AU selbst täte: Start-URL lesen, Token
     * über die Fetch-URL holen, LMS.LaunchData laden.
     *
     * @return array{location: string, fetch: string, registration: string, activityId: string, agent: string, actor: array<mixed>, headers: array<string, string>, context: array<mixed>}
     */
    private function start(LearningEnrollment $enrollment, User $learner, LearningUnit $unit, LearningCmi5Unit $au): array {
        $response = $this->actingAs($learner)->post(route('learning.my.cmi5.launch', [
            'enrollment' => $enrollment->sqid,
            'unit' => $unit->sqid,
            'au' => $au->sqid,
        ]));
        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        $fetch = $this->param($params, 'fetch');
        $agent = $this->param($params, 'actor');
        $registration = $this->param($params, 'registration');
        $activityId = $this->param($params, 'activityId');
        $actor = json_decode($agent, true);
        $this->assertIsArray($actor);

        $token = $this->postJson($fetch)->assertOk()->json('auth-token');
        $this->assertIsString($token);
        $headers = ['Authorization' => 'Basic ' . $token, 'X-Experience-API-Version' => '1.0.3'];

        $launchData = $this->getJson($this->lrs('activities/state', [
            'activityId' => $activityId,
            'agent' => $agent,
            'registration' => $registration,
            'stateId' => Cmi5::STATE_LAUNCH_DATA,
        ]), $headers)->assertOk()->json();
        $this->assertIsArray($launchData);
        $context = $launchData['contextTemplate'] ?? null;
        $this->assertIsArray($context);

        return [
            'location' => $location,
            'fetch' => $fetch,
            'registration' => $registration,
            'activityId' => $activityId,
            'agent' => $agent,
            'actor' => $actor,
            'headers' => $headers,
            'context' => $context,
        ];
    }

    /**
     * cmi5-Statement, wie eine AU es aus der Kontextvorlage in LMS.LaunchData baut.
     *
     * @param  array{location: string, fetch: string, registration: string, activityId: string, agent: string, actor: array<mixed>, headers: array<string, string>, context: array<mixed>}  $au
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function statement(array $au, string $verb, array $result = []): array {
        $categories = [['id' => Cmi5::CATEGORY_CMI5]];

        if (array_key_exists('success', $result) || array_key_exists('completion', $result)) {
            $categories[] = ['id' => Cmi5::CATEGORY_MOVE_ON];
        }

        $context = $au['context'];
        $activities = is_array($context['contextActivities'] ?? null) ? $context['contextActivities'] : [];
        $activities['category'] = $categories;
        $context['contextActivities'] = $activities;
        $context['registration'] = $au['registration'];

        $statement = [
            'id' => Str::uuid()->toString(),
            'actor' => $au['actor'],
            'verb' => ['id' => $verb],
            'object' => ['objectType' => 'Activity', 'id' => $au['activityId']],
            'context' => $context,
        ];

        if ($result !== []) {
            $statement['result'] = $result;
        }

        return $statement;
    }

    /**
     * @param  array{location: string, fetch: string, registration: string, activityId: string, agent: string, actor: array<mixed>, headers: array<string, string>, context: array<mixed>}  $au
     * @param  array<string, mixed>  $statement
     * @return TestResponse<Response>
     */
    private function send(array $au, array $statement): TestResponse {
        return $this->postJson($this->lrs('statements'), $statement, $au['headers']);
    }

    /** @param  array<string, string>  $query */
    private function lrs(string $path, array $query = []): string {
        return LearningCmi5Runtime::endpoint() . $path . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /** @param  array<mixed>  $params */
    private function param(array $params, string $key): string {
        $value = $params[$key] ?? null;
        $this->assertIsString($value);

        return $value;
    }

    private function unitCompleted(LearningEnrollment $enrollment, LearningUnit $unit): bool {
        return LearningUnitProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('learning_unit_id', $unit->id)
            ->where('status', LearningProgressStatus::Completed->value)
            ->exists();
    }
}
