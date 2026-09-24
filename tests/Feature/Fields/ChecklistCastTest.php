<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecklistCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fields;

use App\Enums\User\UserRole;
use App\Models\Applications\{EmployeeDraft, JobApplication};
use App\Models\Form\FormTemplate;
use App\Models\Platform\User;
use App\Services\Applications\RecruitingService;
use App\Services\Fields\FieldDocument;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Checklisten über den Feldschema-Baustein (MVP-866): Cast, Altbestand, Migration. */
class ChecklistCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->userWithRole(UserRole::Personalverwaltung->value);
        $this->actingAs($this->user);
    }

    private function acceptedApplication(): JobApplication {
        app(RecruitingService::class)->intake(['candidate_name' => 'Kim Beispiel', 'email' => 'kim@example.test', 'source' => 'website'], $this->user);
        $application = JobApplication::query()->firstOrFail();
        JobApplication::query()->whereKey($application->id)->update(['status' => 'accepted']);

        return $application->fresh() ?? $application;
    }

    public function test_onboarding_checklist_is_stored_canonically_and_read_as_document(): void {
        $draft = app(RecruitingService::class)->createEmployeeDraft($this->acceptedApplication(), $this->user, ['PHP'], ['Vertrag abgelegt', 'Schlüssel übergeben']);

        $raw = DB::table('employee_drafts')->where('id', $draft->id)->value('checklist');
        $decoded = JsonHelper::decode((string) $raw, true);
        $this->assertSame(['schema', 'values'], array_keys($decoded));
        $this->assertSame('boolean', $decoded['schema'][0]['type']);
        $this->assertSame(['vertrag_abgelegt' => false, 'schlussel_ubergeben' => false], $decoded['values']);

        $document = $draft->fresh()?->checklist;
        $this->assertInstanceOf(FieldDocument::class, $document);
        $this->assertSame(['done' => 0, 'total' => 2], $document->progress());

        $this->get(route('recruiting.applications.show', $draft->application))->assertOk()->assertSee('☐ Vertrag abgelegt');
    }

    public function test_legacy_checklist_json_is_read_and_rewritten_canonically(): void {
        $draft = app(RecruitingService::class)->createEmployeeDraft($this->acceptedApplication(), $this->user);
        DB::table('employee_drafts')->where('id', $draft->id)->update([
            'checklist' => JsonHelper::encode([['label' => 'Arbeitsvertrag unterschrieben abgelegt', 'done' => true], ['label' => 'Erstunterweisung geplant', 'done' => false]]),
        ]);

        $document = EmployeeDraft::query()->findOrFail($draft->id)->checklist;
        $this->assertInstanceOf(FieldDocument::class, $document);
        $this->assertTrue($document->values->get('arbeitsvertrag_unterschrieben_abgelegt'));
        $this->assertSame(['done' => 1, 'total' => 2], $document->progress());

        $model = EmployeeDraft::query()->findOrFail($draft->id);
        $model->checklist = $document;
        $model->save();
        $this->assertStringContainsString('"schema"', (string) DB::table('employee_drafts')->where('id', $draft->id)->value('checklist'));
    }

    public function test_migration_canonicalizes_checklists_and_former_type_names(): void {
        $draft = app(RecruitingService::class)->createEmployeeDraft($this->acceptedApplication(), $this->user);
        DB::table('employee_drafts')->where('id', $draft->id)->update(['checklist' => JsonHelper::encode(['Schlüssel', 'Papiere'])]);
        $template = FormTemplate::factory()->create(['organization_id' => $this->organization->id, 'fields' => [
            ['key' => 'zustand', 'label' => 'Zustand', 'type' => 'select', 'required' => true, 'options' => ['gut'], 'help' => null, 'unit' => null, 'visible_if' => null],
            ['key' => 'ok', 'label' => 'OK', 'type' => 'checkbox', 'required' => false, 'options' => [], 'help' => null, 'unit' => null, 'visible_if' => null],
        ]]);

        $migration = require base_path('database/migrations/2027_02_24_120000_canonicalize_field_schemas_and_checklists.php');
        $migration->up();
        $migration->up(); // idempotent

        $checklist = JsonHelper::decode((string) DB::table('employee_drafts')->where('id', $draft->id)->value('checklist'), true);
        $this->assertSame(['schlussel', 'papiere'], array_column($checklist['schema'], 'key'));
        $this->assertSame(['schlussel' => false, 'papiere' => false], $checklist['values']);

        $fields = JsonHelper::decode((string) DB::table('form_templates')->where('id', $template->id)->value('fields'), true);
        $this->assertSame(['choice', 'boolean'], array_column($fields, 'type'));
    }
}
