<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeWishesPresencePrivacyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Attendance\PresenceStatusKind;
use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Enums\Wishes\AppWishKind;
use App\Models\Knowledge\{KnowledgePageVisit, KnowledgeSpace};
use App\Models\Platform\User;
use App\Models\Privacy\DataSubjectRequest;
use App\Models\Time\PresenceStatus;
use App\Models\Wishes\{AppWish, AppWishVote};
use App\Services\Attendance\PresenceStatusService;
use App\Services\Knowledge\Pages\{KnowledgePageService, KnowledgeReadingService};
use App\Services\Org\UserOffboardingService;
use App\Services\Privacy\{DataSubjectRequestService, SubjectDataExporter};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wissensseiten, Wünsche und Anwesenheitsstand im Datenschutz (Aufräumrunde
 * Phase 177): die Auskunft nach Art. 15 nennt Lesebestätigungen und
 * Seitenbesuche der Wissensseiten sowie eigene Wünsche und Stimmen; der
 * Anwesenheitsstand endet mit dem Austritt.
 */
final class KnowledgeWishesPresencePrivacyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
        app()->instance('currentOrganization', $this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function dsr(): DataSubjectRequest {
        return app(DataSubjectRequestService::class)->open(
            $this->organization,
            DataSubjectRequestType::Access,
            'Erika Beispiel',
            'Bitte Auskunft nach Art. 15.',
            null,
            $this->orgAdmin(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function section(array $payload, string $key): array {
        foreach ((array) $payload['sections'] as $section) {
            if (is_array($section) && ($section['key'] ?? null) === $key) {
                return $section;
            }
        }
        $this->fail("Abschnitt {$key} fehlt in der Auskunft.");
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, array<string, mixed>> Tabelle → Familie
     */
    private function families(array $section): array {
        $families = [];
        foreach ((array) $section['families'] as $family) {
            if (is_array($family)) {
                $families[(string) $family['table']] = $family;
            }
        }

        return $families;
    }

    public function test_auskunft_nennt_lesebestaetigungen_und_seitenbesuche(): void {
        $member = $this->orgUser(['name' => 'Erika Beispiel']);
        $pages = app(KnowledgePageService::class);
        $space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id]);
        $page = $pages->approve($pages->create($space, null, null, $this->admin, ['title' => 'Brandschutzordnung', 'body' => 'Fassung eins']), $this->admin);
        app(KnowledgeReadingService::class)->confirm($page, $member);
        KnowledgePageVisit::query()->create([
            'organization_id' => $this->organization->id,
            'knowledge_page_id' => $page->id,
            'user_id' => $member->id,
            'seen_version' => 1,
            'visited_at' => CarbonImmutable::parse('2026-11-02 09:15:00'),
        ]);
        // Fremde Bestätigung zählt nicht.
        app(KnowledgeReadingService::class)->confirm($page, $this->admin);

        $payload = app(SubjectDataExporter::class)->build($this->dsr(), DataSubjectKind::User, $member);
        $section = $this->section($payload, 'knowledge');

        $families = $this->families($section);
        $this->assertSame(1, $families['knowledge_page_reads']['count']);
        $this->assertSame(1, $families['knowledge_page_visits']['count']);
        $this->assertSame('2026-11-02', $families['knowledge_page_visits']['from']);

        $reads = $section['lists'][__('knowledge_pages.privacy.reads')];
        $this->assertSame([['page' => 'Brandschutzordnung', 'version' => '1']], array_map(static fn (array $row): array => ['page' => $row['page'], 'version' => $row['version']], $reads));
        $this->assertSame('Brandschutzordnung', $section['lists'][__('knowledge_pages.privacy.visits')][0]['page']);
        $this->assertStringNotContainsString('Fassung eins', app(SubjectDataExporter::class)->toJson($payload), 'Seiteninhalte gehören nicht in die Auskunft.');

        // Seite im Papierkorb: der Titel bleibt in der Auskunft.
        $pages->trash($page->refresh(), $this->admin);
        $payload = app(SubjectDataExporter::class)->build($this->dsr(), DataSubjectKind::User, $member);
        $this->assertSame('Brandschutzordnung', $this->section($payload, 'knowledge')['lists'][__('knowledge_pages.privacy.reads')][0]['page']);
    }

    public function test_auskunft_nennt_eigene_wuensche_und_stimmen(): void {
        $member = $this->orgUser(['name' => 'Erika Beispiel']);
        $own = AppWish::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $member->id,
            'kind' => AppWishKind::Bug,
            'body' => 'Die Wochenansicht springt nach dem Speichern zurück.',
            'answer' => 'Ist behoben.',
        ]);
        $other = AppWish::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'body' => 'Filter nach Kunde im Arbeitsvorrat.',
        ]);
        AppWishVote::query()->create(['organization_id' => $this->organization->id, 'app_wish_id' => $other->id, 'user_id' => $member->id]);
        AppWishVote::query()->create(['organization_id' => $this->organization->id, 'app_wish_id' => $own->id, 'user_id' => $this->admin->id]);

        $payload = app(SubjectDataExporter::class)->build($this->dsr(), DataSubjectKind::User, $member);
        $section = $this->section($payload, 'wishes');

        $families = $this->families($section);
        $this->assertSame(1, $families['app_wishes']['count']);
        $this->assertSame(1, $families['app_wish_votes']['count']);
        $this->assertSame(
            [__('wishes.field.created_at'), __('wishes.field.kind'), __('wishes.field.status'), __('wishes.field.body'), __('wishes.field.answer')],
            array_values($families['app_wishes']['columns']),
        );
        $row = $families['app_wishes']['rows'][0];
        $this->assertSame(AppWishKind::Bug->label(), $row[1]);
        $this->assertSame('Die Wochenansicht springt nach dem Speichern zurück.', $row[3]);
        $this->assertSame('Ist behoben.', $row[4]);

        $votes = $section['lists'][__('wishes.privacy.votes')];
        $this->assertCount(1, $votes);
        $this->assertSame('Filter nach Kunde im Arbeitsvorrat.', $votes[0]['wish']);
        $this->assertStringNotContainsString($this->admin->name, app(SubjectDataExporter::class)->toJson(['votes' => $votes]), 'Wer einen Wunsch geäußert hat, steht nicht in der Stimmenliste.');
    }

    public function test_anwesenheitsstand_endet_mit_dem_austritt(): void {
        $member = $this->orgUser();
        $colleague = $this->orgUser();
        $presence = app(PresenceStatusService::class);
        $presence->set($member, PresenceStatusKind::DoNotDisturb, null);
        $presence->set($colleague, PresenceStatusKind::DoNotDisturb, null);

        app(UserOffboardingService::class)->execute($member, $this->admin);

        $this->assertFalse(PresenceStatus::query()->withoutGlobalScopes()->where('user_id', $member->id)->exists());
        $this->assertTrue(PresenceStatus::query()->withoutGlobalScopes()->where('user_id', $colleague->id)->exists(), 'Der Stand anderer Personen bleibt.');
    }
}
