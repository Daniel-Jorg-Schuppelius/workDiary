<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalQueryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Customer\CustomerQueryStatus;
use App\Enums\Project\ProjectStatus;
use App\Mail\CustomerQueryAnsweredMail;
use App\Models\{Comment, Customer, CustomerQuery, DiaryEntry, Project, TimeEntry, User};
use App\Services\Customer\CustomerQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-512: Rückfragen/Kommentare zu freigegebenen Portal-Inhalten —
 * Subject-Allowlist, doppelte Sichtbarkeits-Gates, Nachweis statt Löschen,
 * interne Kommentare bleiben unsichtbar.
 */
class CustomerPortalQueryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private DiaryEntry $diary;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Erika Kundin',
        ]);
        $this->allowPortal($this->customer);

        $this->diary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_portal_user_can_raise_query_on_visible_diary(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.queries.store'), [
                'subject_type' => 'diary',
                'subject' => $this->diary->sqid,
                'question' => 'Wann wird der Auftrag abgeschlossen?',
            ])
            ->assertRedirect(route('customer.queries.index'));

        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->customer->id, (int) $query->customer_id);
        $this->assertSame($this->diary->getKey(), (int) $query->subject_id);
        $this->assertSame('Erika Kundin', $query->asker_name);
        $this->assertTrue($query->isOpen());

        // Erscheint in der Portal-Liste und auf der Auftrags-Detailseite.
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.queries.index'))
            ->assertOk()
            ->assertSee('Wann wird der Auftrag abgeschlossen?');
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.diary.show', $this->diary))
            ->assertOk()
            ->assertSee('Wann wird der Auftrag abgeschlossen?');
    }

    public function test_without_queries_capability_endpoints_are_404(): void {
        $this->allowPortal($this->customer, ['diary']); // queries fehlt bewusst
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.queries.index'))->assertNotFound();
        $this->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => $this->diary->sqid,
            'question' => 'Test',
        ])->assertNotFound();

        // Der freigegebene Auftrag zeigt keinen Rückfragen-Bereich.
        $this->get(route('customer.diary.show', $this->diary))
            ->assertOk()
            ->assertDontSee(__('Rückfrage stellen'));
    }

    public function test_capability_cannot_make_invisible_subjects_queryable(): void {
        // queries an, aber diary NICHT freigegeben → Subject unsichtbar → 404.
        $this->allowPortal($this->customer, ['queries']);
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.queries.store'), [
                'subject_type' => 'diary',
                'subject' => $this->diary->sqid,
                'question' => 'Test',
            ])
            ->assertNotFound();

        $this->assertSame(0, CustomerQuery::query()->withoutGlobalScopes()->count());
    }

    public function test_foreign_manipulated_and_disallowed_subjects_are_rejected(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignDiary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->actingAs($this->portalUser, 'customer');

        // Auftrag eines fremden Kunden.
        $this->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => $foreignDiary->sqid,
            'question' => 'Fremd',
        ])->assertNotFound();

        // Nicht erlaubter Subject-Typ.
        $this->post(route('customer.queries.store'), [
            'subject_type' => 'customer',
            'subject' => $this->customer->sqid,
            'question' => 'Typ nicht erlaubt',
        ])->assertNotFound();

        // Manipulierte ID.
        $this->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => 'manipuliert',
            'question' => 'Kaputt',
        ])->assertNotFound();

        $this->assertSame(0, CustomerQuery::query()->withoutGlobalScopes()->count());
    }

    public function test_unpublished_time_entry_is_not_queryable_in_published_scope(): void {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Query-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->portalUser->id,
        ]);
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->portalUser->id,
            'date' => '2026-07-15',
            'minutes' => 60,
        ]);

        $this->allowPortal($this->customer, timeScope: 'published');
        $this->actingAs($this->portalUser, 'customer');

        $this->post(route('customer.queries.store'), [
            'subject_type' => 'time_entry',
            'subject' => $entry->sqid,
            'question' => 'Zu dieser Zeit',
        ])->assertNotFound();

        // Nach Veröffentlichung ist die Rückfrage möglich.
        $entry->forceFill(['customer_visible_at' => now()])->save();
        $this->post(route('customer.queries.store'), [
            'subject_type' => 'time_entry',
            'subject' => $entry->sqid,
            'question' => 'Zu dieser Zeit',
        ])->assertRedirect(route('customer.queries.index'));
    }

    public function test_answer_reaches_only_own_customer_and_mails_asker(): void {
        Mail::fake();
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.queries.store'), [
                'subject_type' => 'diary',
                'subject' => $this->diary->sqid,
                'question' => 'Bitte um Rückmeldung.',
            ])->assertRedirect();

        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();
        $agent = $this->orgUser();
        app(CustomerQueryService::class)->answer($query, $agent, 'Gerne — morgen früh.');

        Mail::assertSent(CustomerQueryAnsweredMail::class, fn(CustomerQueryAnsweredMail $mail): bool => $mail->hasTo($this->portalUser->email));

        // Eigener Kunde sieht die Antwort mit Autor und Status.
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.queries.index'))
            ->assertOk()
            ->assertSee('Gerne — morgen früh.')
            ->assertSee(__('beantwortet'));

        // Ein anderer Kunde derselben Org sieht sie nicht.
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($otherCustomer);
        $otherPortalUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $this->actingAs($otherPortalUser, 'customer')
            ->get(route('customer.queries.index'))
            ->assertOk()
            ->assertDontSee('Gerne — morgen früh.');
    }

    public function test_withdraw_is_status_change_not_delete(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.queries.store'), [
                'subject_type' => 'diary',
                'subject' => $this->diary->sqid,
                'question' => 'Bitte zurückziehen.',
            ])->assertRedirect();

        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.queries.withdraw', $query))
            ->assertRedirect(route('customer.queries.index'));

        $fresh = $query->fresh();
        $this->assertSame(CustomerQueryStatus::Closed, $fresh->status, 'Rücknahme ist eine Statusänderung.');
        $this->assertSame('Bitte zurückziehen.', $fresh->question, 'Der Nachweis bleibt erhalten.');

        // Fremde Rückfrage kann nicht zurückgezogen werden.
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignQuery = CustomerQuery::query()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => $this->diary->getMorphClass(),
            'subject_id' => $this->diary->getKey(),
            'customer_id' => $otherCustomer->id,
            'question' => 'Fremde Frage',
            'status' => CustomerQueryStatus::Open->value,
        ]);
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.queries.withdraw', $foreignQuery))
            ->assertNotFound();
    }

    public function test_input_is_escaped_and_whitespace_rejected(): void {
        $this->actingAs($this->portalUser, 'customer');

        $this->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => $this->diary->sqid,
            'question' => "   \n\t  ",
        ])->assertSessionHasErrors('question');

        $this->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => $this->diary->sqid,
            'question' => '<script>alert(1)</script>',
        ])->assertRedirect();

        $this->get(route('customer.queries.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_internal_comments_never_appear_in_portal(): void {
        Comment::query()->create([
            'organization_id' => $this->organization->id,
            'commentable_type' => $this->diary->getMorphClass(),
            'commentable_id' => $this->diary->getKey(),
            'user_id' => $this->orgUser()->id,
            'body' => 'Interner Vermerk: Kunde zahlt schleppend.',
        ]);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.diary.show', $this->diary))
            ->assertOk()
            ->assertDontSee('Interner Vermerk');
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.queries.index'))
            ->assertOk()
            ->assertDontSee('Interner Vermerk');
    }

    public function test_rate_limit_blocks_flooding(): void {
        $this->actingAs($this->portalUser, 'customer');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.queries.store'), [
                'subject_type' => 'diary',
                'subject' => $this->diary->sqid,
                'question' => 'Frage Nr. ' . $i,
            ])->assertRedirect();
        }

        $this->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => $this->diary->sqid,
            'question' => 'Eine zu viel',
        ])->assertStatus(429);
    }
}
