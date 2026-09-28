<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\ProcedureDocumentationStatus;
use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Services\Accounting\Posting\PostingInboxService;
use App\Services\Finance\ProcedureDocumentation\{ProcedureDocumentationComparison, ProcedureDocumentationService};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-995: Vier-Augen-Freigabe und Abschnittsvergleich der Verfahrensdokumentation. */
final class ProcedureDocumentationReviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ProcedureDocumentationService $service;

    private User $author;

    private User $reviewer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        $this->service = app(ProcedureDocumentationService::class);
        $this->author = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->reviewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        foreach ([$this->author, $this->reviewer] as $user) {
            $user->givePermissionTo(Permission::FinanceGobdExport->value);
        }
    }

    public function test_with_four_eyes_a_second_person_approves_or_rejects(): void {
        Setting::set(PostingInboxService::FOUR_EYES_KEY, true, SettingScope::Organization, $this->organization);
        $draft = $this->service->createDraft($this->organization, $this->author, ['general_description' => 'Erste Fassung']);

        try {
            $this->service->publish($draft, $this->author);
            $this->fail('Direktes Veröffentlichen trotz Vier-Augen-Prinzip.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->actingAs($this->author)->post(route('finance.procedure-documentation.submit', $draft))->assertRedirect();
        $draft->refresh();
        $this->assertSame(ProcedureDocumentationStatus::InReview, $draft->status);
        $this->assertSame($this->author->id, $draft->submitter_user_id);
        $this->actingAs($this->author)->post(route('finance.procedure-documentation.publish', $draft))->assertSessionHasErrors('status');

        $this->actingAs($this->reviewer)->post(route('finance.procedure-documentation.reject', $draft), ['review_note' => 'Sicherung fehlt'])->assertRedirect();
        $this->assertSame(ProcedureDocumentationStatus::Draft, $draft->refresh()->status);
        $this->assertSame('Sicherung fehlt', $draft->review_note);

        $this->service->submit($draft, $this->author);
        $this->actingAs($this->reviewer)->post(route('finance.procedure-documentation.publish', $draft->refresh()))->assertRedirect();
        $this->assertSame(ProcedureDocumentationStatus::Published, $draft->refresh()->status);
        $this->assertSame($this->reviewer->id, $draft->published_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'procedure_documentation.submitted', 'auditable_id' => $draft->id]);
    }

    public function test_without_four_eyes_the_author_publishes_directly(): void {
        $draft = $this->service->createDraft($this->organization, $this->author);

        $this->service->publish($draft, $this->author);

        $this->assertSame(ProcedureDocumentationStatus::Published, $draft->refresh()->status);
    }

    public function test_versions_are_compared_section_by_section(): void {
        $first = $this->service->createDraft($this->organization, $this->author, ['general_description' => 'Alt', 'change_history' => 'v1']);
        $this->service->publish($first, $this->author);
        $second = $this->service->createDraft($this->organization, $this->author, ['general_description' => 'Neu']);

        $rows = collect(app(ProcedureDocumentationComparison::class)->compare($first->refresh(), $second))->keyBy(fn (array $row): string => $row['part'] . ':' . $row['key']);
        $this->assertSame('changed', $rows['operator:general_description']['status']);
        $this->assertSame('Alt', $rows['operator:general_description']['old']);
        $this->assertSame('unchanged', $rows['operator:change_history']['status'], 'Freitexte werden vorbelegt');
        $this->assertSame('unchanged', $rows['operator:user_documentation']['status']);
        $this->assertTrue($rows->has('generated:numbering'));

        $this->actingAs($this->author)->get(route('finance.procedure-documentation.compare', ['document' => $second, 'with' => $first->sqid]))
            ->assertOk()->assertSee('Alt')->assertSee('Neu')->assertSee(__('procedure-documentation.compare.status.changed'));
        $this->actingAs($this->author)->get(route('finance.procedure-documentation.show', $second))->assertOk()
            ->assertSee(route('finance.procedure-documentation.compare', ['document' => $second, 'with' => $first->sqid]), false);
    }
}
