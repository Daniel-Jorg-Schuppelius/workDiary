<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageHandoverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\User\UserRole;
use App\Models\Audit\AuditLog;
use App\Models\Knowledge\{KnowledgePage, KnowledgeSpace};
use App\Models\Platform\User;
use App\Services\Knowledge\Pages\Offboarding\KnowledgePageOffboardingHandover;
use App\Services\Org\UserOffboardingService;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Seitenverantwortung der Wissensseiten im Austritts- und im Übergabe-Dialog
 * (Feature 191/202): die Seiten der Person gehen samt Papierkorb an die
 * Nachfolge, fremde Seiten bleiben, jede Übertragung steht im Audit.
 */
final class KnowledgePageHandoverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $anna;

    private KnowledgeSpace $space;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->anna = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Anna Beispiel']);
        $this->space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Handbuch']);
    }

    private function page(User $owner, string $title): KnowledgePage {
        return KnowledgePage::factory()->create([
            'organization_id' => $this->organization->id,
            'knowledge_space_id' => $this->space->id,
            'title' => $title,
            'owner_user_id' => $owner->id,
        ]);
    }

    public function test_the_final_handover_moves_the_page_responsibility_including_the_trash(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $carl = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Carl Probe']);
        $first = $this->page($this->anna, 'Urlaubsantrag stellen');
        $trashed = $this->page($this->anna, 'Altes Formular');
        $trashed->delete();
        $foreign = $this->page($lead, 'Notfallplan');

        $this->assertContains('knowledge_pages', array_column(app(UserOffboardingService::class)->handovers($this->anna), 'key'));
        $this->actingAs($lead)->get(route('delegations.handover.dialog', $this->anna))
            ->assertOk()
            ->assertSee('handovers[knowledge_pages]', false)
            ->assertSee(trans_choice('knowledge_pages.handover.label', 2, ['count' => 2]));

        $this->actingAs($lead)->post(route('delegations.handover.store', $this->anna), [
            'handovers' => ['knowledge_pages' => Sqid::encode(User::class, (int) $carl->id)],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame($carl->id, $first->fresh()?->owner_user_id);
        $this->assertSame($carl->id, KnowledgePage::withTrashed()->findOrFail($trashed->id)->owner_user_id);
        $this->assertSame($lead->id, $foreign->fresh()?->owner_user_id, 'fremde Seiten bleiben');
        $this->assertSame(0, app(KnowledgePageOffboardingHandover::class)->count($this->anna));
        $this->assertSame(2, AuditLog::query()->where('event', 'knowledge_page.owner_transferred')->count());
    }

    public function test_the_exit_dialog_offers_and_hands_over_the_page_responsibility(): void {
        $admin = $this->orgAdmin();
        $successor = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Dora Nachfolge']);
        $page = $this->page($this->anna, 'Rufbereitschaft');

        $this->actingAs($admin)->get(route('org.members.offboard.dialog', $this->anna))
            ->assertOk()
            ->assertSee('handovers[knowledge_pages]', false);

        $this->actingAs($admin)->post(route('org.members.offboard', $this->anna), [
            'left_at' => now()->toDateString(),
            'handovers' => ['knowledge_pages' => $successor->sqid],
        ])->assertRedirect();

        $this->assertSame($successor->id, $page->fresh()?->owner_user_id);
    }
}
