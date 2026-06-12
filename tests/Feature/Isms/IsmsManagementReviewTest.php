<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsManagementReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\ReviewStatus;
use App\Models\Isms\{IsmsManagementReview, IsmsScope};
use App\Models\{Organization, User};
use App\Services\Isms\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Managementbewertung (Feature 046, Inkrement C): Nummernvergabe je
 * Organisation, Freigabe nach dem 046-Prinzip (Person + Zeitpunkt),
 * UNVERÄNDERLICHKEIT freigegebener Protokolle (update/delete ⇒ Fehler),
 * Permissions und Mandantengrenze.
 */
class IsmsManagementReviewTest extends TestCase {
    use RefreshDatabase;

    public function test_store_creates_review_with_sequential_numbers(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $scope = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        foreach ([1, 2] as $i) {
            $this->actingAs($admin)
                ->post(route('isms.reviews.store'), $this->reviewPayload(['scope' => $scope->sqid]))
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $numbers = IsmsManagementReview::query()->orderBy('review_no')->pluck('review_no')->all();
        $this->assertSame([1, 2], $numbers, 'review_no läuft je Organisation fortlaufend');

        /** @var IsmsManagementReview $review */
        $review = IsmsManagementReview::query()->firstOrFail();
        $this->assertSame(ReviewStatus::Draft, $review->status, 'Bewertungen starten immer als Entwurf');
        $this->assertSame('MR-1', $review->displayNo());
        $this->assertNull($review->approved_by_user_id);
        $this->assertNull($review->approved_at);
    }

    public function test_approve_sets_person_and_time_and_freezes_review(): void {
        $admin = User::factory()->admin()->create();
        $review = $this->makeReview($admin);

        $this->actingAs($admin)
            ->post(route('isms.reviews.approve', $review))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame(ReviewStatus::Approved, $review->status);
        $this->assertSame((int) $admin->id, (int) $review->approved_by_user_id, 'Freigabe dokumentiert die Person');
        $this->assertNotNull($review->approved_at, 'Freigabe dokumentiert den Zeitpunkt');

        // Danach unveränderlich: Update wird abgewiesen.
        $this->actingAs($admin)
            ->from(route('isms.reviews.index'))
            ->put(route('isms.reviews.update', $review), $this->reviewPayload(['decisions' => 'Nachträglich geändert.']))
            ->assertRedirect(route('isms.reviews.index'))
            ->assertSessionHasErrors('status');
        $this->assertNotSame('Nachträglich geändert.', $review->refresh()->decisions);

        // Erneute Freigabe und Löschung sind ebenfalls gesperrt.
        $this->actingAs($admin)
            ->from(route('isms.reviews.index'))
            ->post(route('isms.reviews.approve', $review))
            ->assertRedirect(route('isms.reviews.index'))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->from(route('isms.reviews.index'))
            ->delete(route('isms.reviews.destroy', $review))
            ->assertRedirect(route('isms.reviews.index'))
            ->assertSessionHasErrors('status');
        $this->assertNotNull(IsmsManagementReview::query()->find($review->id), 'Freigegebene Bewertung bleibt erhalten');

        // Service direkt: ValidationException.
        try {
            app(AuditService::class)->updateReview($review, $admin, ['decisions' => 'Direkt am Service.']);
            $this->fail('Erwartete ValidationException blieb aus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_draft_review_can_be_updated_and_deleted(): void {
        $admin = User::factory()->admin()->create();
        $review = $this->makeReview($admin);

        $this->actingAs($admin)
            ->put(route('isms.reviews.update', $review), $this->reviewPayload(['decisions' => 'Aktualisierte Entscheidung.']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('Aktualisierte Entscheidung.', $review->refresh()->decisions);

        $this->actingAs($admin)
            ->delete(route('isms.reviews.destroy', $review))
            ->assertRedirect(route('isms.reviews.index'));
        $this->assertNull(IsmsManagementReview::query()->find($review->id));
    }

    public function test_regular_user_cannot_access_reviews(): void {
        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
        $review = $this->makeReview($admin);

        $this->actingAs($user)->get(route('isms.reviews.index'))->assertForbidden();
        $this->actingAs($user)->post(route('isms.reviews.approve', $review))->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_manage(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $gf->organization_id]);
        $review = $this->makeReview($admin);

        $this->actingAs($gf)->get(route('isms.reviews.index'))->assertOk();
        $this->actingAs($gf)->get(route('isms.reviews.show', $review))->assertOk();

        $this->actingAs($gf)->post(route('isms.reviews.approve', $review))->assertForbidden();
        $this->actingAs($gf)
            ->put(route('isms.reviews.update', $review), $this->reviewPayload())
            ->assertForbidden();

        $this->assertSame(ReviewStatus::Draft, $review->refresh()->status);
    }

    public function test_cross_organization_review_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-review-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreignReview = $this->makeReview($otherAdmin);

        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)->get(route('isms.reviews.show', $foreignReview))->assertNotFound();
        $this->actingAs($admin)->post(route('isms.reviews.approve', $foreignReview))->assertNotFound();

        $this->assertSame(ReviewStatus::Draft, $foreignReview->refresh()->status);
    }

    /** Bewertung (Entwurf) im Default-Scope der Organisation des Users. */
    private function makeReview(User $owner): IsmsManagementReview {
        app()->instance('currentOrganization', $owner->organization);

        $scope = IsmsScope::query()->firstOrCreate(
            ['organization_id' => $owner->organization_id, 'is_default' => true],
            ['name' => 'Gesamtorganisation'],
        );

        return IsmsManagementReview::factory()->create([
            'organization_id' => $owner->organization_id,
            'isms_scope_id' => $scope->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reviewPayload(array $overrides = []): array {
        return $overrides + [
            'held_on' => now()->subDay()->toDateString(),
            'participants' => 'Geschäftsführung, ISB',
            'inputs' => 'Auditergebnisse, Kennzahlen, Risikolage.',
            'decisions' => 'Ressourcen freigegeben.',
            'follow_ups' => null,
        ];
    }
}
