<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderRadarUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Tenders;

use App\Enums\Applications\TenderProcedureType;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\{Organization, User};
use App\Models\Tenders\{TenderFilterProfile, TenderNotice, TenderNoticeMatch};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Treffer-Inbox und Suchprofile des Bekanntmachungs-Radars (MVP-630).
 *
 * Der Radar schlägt vor, er entscheidet nicht: Was nicht passt, wird
 * ausgeblendet und bleibt als Beleg; was passt, wird zum Vergabevorgang.
 */
final class TenderRadarUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function notice(array $attributes = []): TenderNotice {
        return TenderNotice::query()->create(array_replace([
            'notice_id' => 'n-' . fake()->unique()->numerify('######'),
            'version' => '1',
            'title' => 'Neubau Kita — Rohbauarbeiten',
            'summary' => 'Rohbau in Massivbauweise.',
            'buyer_name' => 'Stadt Bonn',
            'procedure_method' => 'open',
            'cpv_codes' => ['45210000'],
            'nuts_code' => 'DEA22',
            'estimated_value' => 250000.0,
            'currency' => 'EUR',
            'published_on' => now()->subDay()->toDateString(),
            'submission_deadline' => now()->addWeeks(3),
            'url' => 'https://oeffentlichevergabe.de/notice/1',
        ], $attributes));
    }

    private function match(?TenderNotice $notice = null, string $state = TenderNoticeMatch::STATE_NEW, ?int $organizationId = null): TenderNoticeMatch {
        return TenderNoticeMatch::query()->create([
            'organization_id' => $organizationId ?? $this->organization->id,
            'tender_notice_id' => ($notice ?? $this->notice())->id,
            'state' => $state,
        ]);
    }

    public function test_inbox_lists_new_matches(): void {
        TenderFilterProfile::query()->create(['organization_id' => $this->organization->id, 'name' => 'Hochbau']);
        $this->match();

        $this->actingAs($this->admin)
            ->get(route('tender-radar.index'))
            ->assertOk()
            ->assertSee('Neubau Kita — Rohbauarbeiten', escape: false)
            ->assertSee('Stadt Bonn');
    }

    /** Ohne Profil sucht der Radar nichts — das gehört gesagt, nicht verschwiegen. */
    public function test_inbox_asks_for_a_profile_first(): void {
        $this->actingAs($this->admin)
            ->get(route('tender-radar.index'))
            ->assertOk()
            ->assertSee('Noch kein Suchprofil angelegt');
    }

    /** Ausgeblendetes bleibt erhalten und verschwindet nur aus der Liste. */
    public function test_muting_keeps_the_match_as_evidence(): void {
        $match = $this->match();

        $this->actingAs($this->admin)->post(route('tender-radar.mute', $match))->assertRedirect();

        $this->assertSame(TenderNoticeMatch::STATE_MUTED, $match->refresh()->state);
        $this->actingAs($this->admin)
            ->get(route('tender-radar.index'))
            ->assertDontSee('Neubau Kita — Rohbauarbeiten', escape: false);
        $this->actingAs($this->admin)
            ->get(route('tender-radar.index', ['state' => 'muted']))
            ->assertSee('Neubau Kita — Rohbauarbeiten', escape: false);
    }

    public function test_muted_match_can_be_restored(): void {
        $match = $this->match(state: TenderNoticeMatch::STATE_MUTED);

        $this->actingAs($this->admin)->post(route('tender-radar.restore', $match))->assertRedirect();

        $this->assertSame(TenderNoticeMatch::STATE_NEW, $match->refresh()->state);
    }

    /** Die Übernahme belegt vor, was die Bekanntmachung hergibt. */
    public function test_conversion_prefills_the_tender_case(): void {
        $match = $this->match();

        $this->actingAs($this->admin)->post(route('tender-radar.convert', $match))->assertRedirect();

        $opportunity = ApplicationOpportunity::query()->firstOrFail();
        $this->assertSame('Neubau Kita — Rohbauarbeiten', $opportunity->title);
        $this->assertSame('Stadt Bonn', $opportunity->awarding_body);
        $this->assertSame(['45210000'], $opportunity->cpv_codes);
        $this->assertSame('DEA22', $opportunity->nuts_code);
        $this->assertSame(TenderProcedureType::OpenProcedure, $opportunity->procedure_type);
        $this->assertSame('https://oeffentlichevergabe.de/notice/1', $opportunity->notice_url);

        $match->refresh();
        $this->assertSame(TenderNoticeMatch::STATE_CONVERTED, $match->state);
        $this->assertSame($opportunity->id, $match->application_opportunity_id);
    }

    /** Zweimal übernehmen legt keinen zweiten Vorgang an. */
    public function test_conversion_happens_once(): void {
        $match = $this->match();

        $this->actingAs($this->admin)->post(route('tender-radar.convert', $match))->assertRedirect();
        $this->actingAs($this->admin)->post(route('tender-radar.convert', $match))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, ApplicationOpportunity::query()->count());
    }

    /**
     * Eine Bekanntmachung ist öffentlich, der Treffer ist es nicht: Er sagt,
     * wofür sich ein Betrieb interessiert.
     */
    public function test_matches_of_other_organisations_are_not_reachable(): void {
        $other = Organization::factory()->create();
        $foreign = $this->match(organizationId: $other->id);

        $this->actingAs($this->admin)->post(route('tender-radar.mute', $foreign))->assertNotFound();
    }

    public function test_profile_is_created_from_free_text_lists(): void {
        $this->actingAs($this->admin)->post(route('tender-radar.profiles.store'), [
            'name' => 'Hochbau in NRW',
            'active' => '1',
            'cpv_codes' => '45, 45210000',
            'nuts_codes' => 'dea DE2',
            'keywords' => 'Rohbau, Sanierung',
            'excluded_keywords' => 'Abbruch',
            'min_value' => '50000',
        ])->assertRedirect(route('tender-radar.profiles'));

        $profile = TenderFilterProfile::query()->firstOrFail();
        $this->assertSame(['45', '45210000'], $profile->cpv_codes);
        // Codes werden normalisiert - Vergabestellen schreiben sie mal groß,
        // mal klein, mal mit Bindestrich.
        $this->assertSame(['DEA', 'DE2'], $profile->nuts_codes);
        $this->assertSame(['Rohbau', 'Sanierung'], $profile->keywords);
        $this->assertSame(['Abbruch'], $profile->excluded_keywords);
        $this->assertTrue($profile->active);
    }

    public function test_profile_can_be_edited_and_deleted(): void {
        $profile = TenderFilterProfile::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Alt',
            'cpv_codes' => ['45'],
        ]);

        $this->actingAs($this->admin)->get(route('tender-radar.profiles.edit', $profile))->assertOk();
        $this->actingAs($this->admin)->put(route('tender-radar.profiles.update', $profile), [
            'name' => 'Neu',
            'cpv_codes' => '71',
        ])->assertRedirect(route('tender-radar.profiles'));

        $profile->refresh();
        $this->assertSame('Neu', $profile->name);
        $this->assertSame(['71'], $profile->cpv_codes);
        // Ohne Häkchen ist das Profil aus - der Schalter fehlt im Formular,
        // wenn er nicht gesetzt wurde.
        $this->assertFalse($profile->active);

        $this->actingAs($this->admin)->delete(route('tender-radar.profiles.destroy', $profile))->assertRedirect();
        $this->assertSame(0, TenderFilterProfile::query()->count());
    }

    /** Fremde Profile sind nicht erreichbar, auch nicht mit gültiger ID. */
    public function test_foreign_profile_is_not_editable(): void {
        $other = Organization::factory()->create();
        $foreign = TenderFilterProfile::query()->create(['organization_id' => $other->id, 'name' => 'Fremd']);

        $this->actingAs($this->admin)->get(route('tender-radar.profiles.edit', $foreign))->assertNotFound();
    }

    /** Wer die Vergabeakte nicht führen darf, ändert auch am Radar nichts. */
    public function test_plain_user_cannot_convert(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $match = $this->match();

        $this->actingAs($user)->post(route('tender-radar.convert', $match))->assertForbidden();
    }

    /**
     * Der Demo-Weg muss an einem Stück laufen (MVP-635): Profil → Treffer →
     * Vergabevorgang → Submissionsergebnis.
     */
    public function test_demo_seeder_builds_the_whole_path(): void {
        (new \Database\Seeders\TenderDemoSeeder)->run($this->organization);

        $this->assertSame(1, TenderFilterProfile::query()->count());
        $this->assertSame(1, TenderNoticeMatch::query()->where('state', TenderNoticeMatch::STATE_NEW)->count());
        $this->assertSame(2, ApplicationOpportunity::query()->count());

        $lost = ApplicationOpportunity::query()->where('status', 'lost')->firstOrFail();
        $this->assertSame(3, $lost->competitorBids()->count());
        $this->assertTrue($lost->competitorBids()->where('is_own', true)->exists());

        // Zweimal laufen lassen darf nichts verdoppeln.
        (new \Database\Seeders\TenderDemoSeeder)->run($this->organization);
        $this->assertSame(2, ApplicationOpportunity::query()->count());
        $this->assertSame(3, $lost->competitorBids()->count());
    }

    public function test_profiles_page_lists_profiles(): void {
        TenderFilterProfile::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Hochbau in NRW',
            'cpv_codes' => ['45'],
            'nuts_codes' => ['DEA'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('tender-radar.profiles'))
            ->assertOk()
            ->assertSee('Hochbau in NRW');
    }
}
