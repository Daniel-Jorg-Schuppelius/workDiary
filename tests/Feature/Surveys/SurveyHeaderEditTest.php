<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyHeaderEditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Models\Platform\User;
use App\Models\Survey\Survey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Fragebogen-Kopf bearbeiten (MVP-1610): Titel und Zweck jederzeit, Anonymität nur bis zur ersten Einladung. */
final class SurveyHeaderEditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function survey(): Survey {
        return Survey::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Jahres-NPS',
            'active' => true,
            'anonymous' => false,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_header_is_editable_before_the_first_invitation(): void {
        $survey = $this->survey();

        $this->actingAs($this->admin)->get(route('surveys.show', $survey))->assertOk()->assertSee(route('surveys.edit', $survey), false);
        $this->actingAs($this->admin)->get(route('surveys.edit', $survey))->assertOk()->assertSee('Jahres-NPS');
        $this->actingAs($this->admin)
            ->put(route('surveys.update', $survey), ['title' => 'Kundenzufriedenheit', 'purpose' => 'Kurz und anonym', 'anonymous' => '1'])
            ->assertRedirect(route('surveys.show', $survey));

        $survey->refresh();
        $this->assertSame(['Kundenzufriedenheit', 'Kurz und anonym', true], [$survey->title, $survey->purpose, $survey->anonymous]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'survey.updated']);
    }

    public function test_anonymity_stays_after_the_first_invitation(): void {
        $survey = $this->survey();
        $survey->invitations()->create([
            'organization_id' => $this->organization->id,
            'email' => 'kunde@example.test',
            'token_hash' => str_repeat('a', 64),
            'expires_at' => now()->addDays(14),
            'status' => 'sent',
        ]);

        $this->actingAs($this->admin)->get(route('surveys.edit', $survey))->assertOk()->assertSee(__('survey.header.anonymous_locked'));
        $this->actingAs($this->admin)->put(route('surveys.update', $survey), ['title' => 'Neu', 'anonymous' => '1'])->assertRedirect();

        $survey->refresh();
        $this->assertSame('Neu', $survey->title);
        $this->assertFalse($survey->anonymous);
    }
}
