<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionDraftTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Form;

use App\Enums\Form\FormSubmissionStatus;
use App\Models\Diary\DiaryEntry;
use App\Models\Form\{FormSubmission, FormTemplate};
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{MorphMap, Sqid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Route};
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Speichern je Feld (Feature 203, MVP-1650): Entwurf entsteht mit dem ersten
 * Feld, der gemeinsame Endpunkt prüft nur das eine Feld, die Version schützt
 * vor stillem Überschreiben, Absenden prüft alles samt Pflicht. Entwürfe sind
 * persönlich.
 */
class FormSubmissionDraftTest extends TestCase {
    use RefreshDatabase;

    private User $user;

    private FormTemplate $template;

    protected function setUp(): void {
        parent::setUp();
        $this->user = User::factory()->user()->create();
        app()->instance('currentOrganization', $this->user->organization);
        $this->template = FormTemplate::factory()->active()->create([
            'organization_id' => $this->user->organization_id,
            'created_by_user_id' => $this->user->id,
            'fields' => [
                ['key' => 'anlass', 'label' => 'Anlass', 'type' => 'section', 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
                ['key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => 'text', 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
                ['key' => 'messwert', 'label' => 'Messwert', 'type' => 'number', 'required' => true, 'options' => [], 'help' => null, 'unit' => 'kWh'],
                ['key' => 'zustand', 'label' => 'Zustand', 'type' => 'choice', 'required' => true, 'options' => ['gut', 'mittel', 'schlecht', 'defekt'], 'help' => null, 'unit' => null, 'allows_note' => true],
                ['key' => 'geprueft', 'label' => 'Geprüft', 'type' => 'boolean', 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ],
        ]);
    }

    public function test_existing_submissions_count_as_submitted(): void {
        $id = DB::table('form_submissions')->insertGetId([
            'organization_id' => $this->user->organization_id,
            'form_template_id' => $this->template->id,
            'fields_snapshot' => json_encode($this->template->fields),
            'values' => json_encode(['bemerkung' => 'Bestand']),
            'submitted_by_user_id' => $this->user->id,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = FormSubmission::query()->findOrFail($id);
        $this->assertSame(FormSubmissionStatus::Submitted, $row->status);
        $this->assertSame(1, $row->version);
        $this->assertFalse($row->isDraft());
    }

    public function test_draft_starts_with_the_first_field_and_is_reused(): void {
        $first = $this->startDraft()->assertCreated()->assertJsonStructure(['draft', 'version', 'url', 'reload_url']);
        $again = $this->startDraft()->assertOk();

        $this->assertSame($first->json('draft'), $again->json('draft'));
        $draft = $this->draft();
        $this->assertTrue($draft->isDraft());
        $this->assertNull($draft->submitted_at);
        $this->assertSame($this->template->fields, $draft->fields_snapshot);
        $this->assertStringContainsString('/field-documents/' . MorphMap::alias(FormSubmission::class) . '/' . $draft->sqid . '/fields/__FIELD__', (string) $first->json('url'));
    }

    public function test_saving_a_field_checks_only_that_field_and_counts_by_section(): void {
        $this->startDraft();

        // Pflichtfelder bleiben offen — der Entwurf lässt sich trotzdem speichern.
        $response = $this->saveField('bemerkung', ['value' => 'Erste Antwort', 'version' => 1])->assertOk();
        $response->assertJsonPath('version', 2)
            ->assertJsonPath('answered', true)
            ->assertJsonPath('sections.0.key', 'anlass')
            ->assertJsonPath('sections.0.answered', 1)
            ->assertJsonPath('sections.0.total', 4);

        $this->saveField('messwert', ['value' => 'keine Zahl', 'version' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
        $this->saveField('zustand', ['value' => 'unbekannt', 'version' => 2])->assertStatus(422);

        $draft = $this->draft();
        $this->assertSame(2, $draft->version);
        $this->assertSame(['bemerkung' => 'Erste Antwort'], $draft->values);
    }

    public function test_two_quick_changes_are_both_saved_in_order(): void {
        $this->startDraft();

        $this->saveField('bemerkung', ['value' => 'A', 'version' => 1])->assertOk()->assertJsonPath('version', 2);
        $this->saveField('messwert', ['value' => '4.5', 'version' => 2])->assertOk()->assertJsonPath('version', 3);
        $this->saveField('geprueft', ['value' => '1', 'version' => 3])->assertOk()->assertJsonPath('version', 4);
        $this->saveField('zustand', ['value' => 'mittel', 'note' => 'nach Rückfrage', 'version' => 4])->assertOk();

        $draft = $this->draft();
        $this->assertSame('A', $draft->values['bemerkung']);
        $this->assertSame(4.5, $draft->values['messwert']);
        $this->assertTrue($draft->values['geprueft']);
        $this->assertSame('mittel', $draft->values['zustand']);
        $this->assertSame('nach Rückfrage', $draft->fieldDocumentValues()->note('zustand'));
        // Zwischenstände schreiben kein Änderungsprotokoll je Feld.
        $this->assertSame(0, DB::table('audit_logs')->where('auditable_id', $draft->id)->where('event', 'updated')->count());
    }

    public function test_stale_version_gives_a_conflict_instead_of_overwriting(): void {
        $this->startDraft();
        $this->saveField('bemerkung', ['value' => 'aus Fenster 1', 'version' => 1])->assertOk();

        $this->saveField('bemerkung', ['value' => 'aus Fenster 2', 'version' => 1])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'stale')
            ->assertJsonPath('version', 2);

        $this->assertSame('aus Fenster 1', $this->draft()->values['bemerkung']);
    }

    public function test_note_needs_a_question_that_allows_it(): void {
        $this->startDraft();

        $this->saveField('bemerkung', ['note' => 'geht nicht', 'version' => 1])->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->saveField('unbekannt', ['value' => 'x', 'version' => 1])->assertStatus(422);
        $this->saveField('anlass', ['value' => 'x', 'version' => 1])->assertStatus(422);
    }

    public function test_drafts_are_personal_and_the_endpoint_is_scoped(): void {
        $this->startDraft();
        $draft = $this->draft();
        $colleague = User::factory()->user()->create(['organization_id' => $this->user->organization_id]);
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->user->organization_id]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->user->organization_id]);
        $stranger = User::factory()->user()->create();

        $url = $this->fieldUrl($draft, 'bemerkung');
        $this->actingAs($colleague)->patchJson($url, ['value' => 'x', 'version' => 1])->assertForbidden();
        $this->actingAs($admin)->patchJson($url, ['value' => 'x', 'version' => 1])->assertForbidden();
        $this->actingAs($stranger)->patchJson($url, ['value' => 'x', 'version' => 1])->assertNotFound();
        $this->actingAs($lead)->get(route('form-submissions.show', $draft))->assertForbidden();
        $this->actingAs($lead)->get(route('form-submissions.index'))->assertOk()->assertDontSee('form-draft-' . $draft->id);
        $this->actingAs($this->user)->get(route('form-submissions.index'))->assertOk()->assertSee('form-draft-' . $draft->id);

        // Nur Aliase von Trägern mit dem Vertrag.
        $this->actingAs($this->user)
            ->patchJson(route('field-documents.autosave', ['type' => MorphMap::alias(FormTemplate::class), 'document' => $this->template->sqid, 'field' => 'name']), ['value' => 'x', 'version' => 1])
            ->assertNotFound();
        $this->actingAs($this->user)
            ->patchJson('/field-documents/users/' . $this->user->id . '/fields/name', ['value' => 'x', 'version' => 1])
            ->assertNotFound();
    }

    /** Jede Feldänderung ist ein Aufruf: großzügig gedrosselt, je angemeldeter Person. */
    public function test_autosave_endpoint_carries_its_own_rate_limit(): void {
        $route = Route::getRoutes()->getByName('field-documents.autosave');
        $this->assertNotNull($route);
        $this->assertContains('throttle:120,1', $route->gatherMiddleware());

        $this->startDraft();
        $this->actingAs($this->user)->patchJson($this->fieldUrl($this->draft(), 'bemerkung'), ['value' => 'Erste Zeile', 'version' => 1])
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120');
    }

    public function test_module_off_locks_the_endpoint(): void {
        $this->startDraft();
        $draft = $this->draft();
        config(['license.feature_overrides' => ['module.forms' => false]]);
        app(FeatureFlagResolver::class)->flush();

        try {
            $this->actingAs($this->user)->patchJson($this->fieldUrl($draft, 'bemerkung'), ['value' => 'x', 'version' => 1])->assertStatus(423);
            $this->assertSame([], $this->draft()->values);
        } finally {
            config(['license.feature_overrides' => []]);
            app(FeatureFlagResolver::class)->flush();
        }
    }

    public function test_reopening_the_dialog_resumes_the_draft_with_its_values(): void {
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->user->organization_id, 'user_id' => $this->user->id, 'title' => 'Wartung']);
        $this->startDraft($entry);
        $this->saveField('bemerkung', ['value' => 'Nach dem Neuladen sichtbar', 'version' => 1])->assertOk();

        $this->actingAs($this->user)
            ->get(route('form-submissions.create', ['template' => $this->template->sqid, 'subject_kind' => 'diary', 'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id)]))
            ->assertOk()
            ->assertSee('Nach dem Neuladen sichtbar')
            ->assertSee('name="draft" value="' . $this->draft()->sqid . '"', false)
            ->assertSee('name="draft_version" value="2"', false)
            ->assertSee('x-data="fieldForm"', false);

        $this->assertSame(FormSubmissionStatus::Draft, $this->draft()->status);
        $this->actingAs($this->user)->get(route('form-submissions.edit', $this->draft()))->assertOk()->assertSee('Nach dem Neuladen sichtbar');
        // Ohne Entwurf startet der Dialog leer und legt erst beim ersten Feld einen an.
        $this->actingAs($this->user)
            ->get(route('form-submissions.create', ['template' => $this->template->sqid]))
            ->assertOk()
            ->assertSee('name="draft" value=""', false)
            // Startadresse des Entwurfs steht JSON-kodiert in data-autosave.
            ->assertSee(str_replace('/', '\\/', route('form-submissions.drafts.store')), false);
    }

    public function test_submitting_a_draft_checks_required_fields_and_finalizes_it(): void {
        $this->startDraft();
        $this->saveField('bemerkung', ['value' => 'Zwischenstand', 'version' => 1])->assertOk();
        $draft = $this->draft();

        // Pflichtfelder greifen erst beim Absenden.
        $this->actingAs($this->user)->postJson(route('form-submissions.store'), [
            'form_template_id' => $this->template->sqid,
            'draft' => $draft->sqid,
            'draft_version' => 2,
            'values' => ['bemerkung' => 'Zwischenstand'],
        ])->assertStatus(422)->assertJsonValidationErrors(['values.messwert', 'values.zustand']);

        // Veraltete Version: Hinweis statt stillem Überschreiben.
        $this->actingAs($this->user)->postJson(route('form-submissions.store'), [
            'form_template_id' => $this->template->sqid,
            'draft' => $draft->sqid,
            'draft_version' => 1,
            'values' => ['bemerkung' => 'Final', 'messwert' => '3', 'zustand' => 'gut'],
        ])->assertStatus(409)->assertJsonPath('reason', 'stale');

        $this->actingAs($this->user)->post(route('form-submissions.store'), [
            'form_template_id' => $this->template->sqid,
            'draft' => $draft->sqid,
            'draft_version' => 2,
            'values' => ['bemerkung' => 'Final', 'messwert' => '3', 'zustand' => 'defekt'],
            'notes' => ['zustand' => 'Pumpe tauschen'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, FormSubmission::query()->count(), 'kein zweiter Datensatz');
        $submitted = $draft->refresh();
        $this->assertSame(FormSubmissionStatus::Submitted, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertSame(3, $submitted->version);
        $this->assertSame('Final', $submitted->values['bemerkung']);
        $this->assertSame('Pumpe tauschen', $submitted->fieldDocumentValues()->note('zustand'));

        // Abgesendet: nicht mehr je Feld änderbar, doppeltes Absenden legt nichts an.
        $this->saveField('bemerkung', ['value' => 'später', 'version' => 3])->assertForbidden();
        $this->actingAs($this->user)->post(route('form-submissions.store'), [
            'form_template_id' => $this->template->sqid,
            'draft' => $draft->sqid,
            'values' => ['bemerkung' => 'nochmal'],
        ])->assertRedirect();
        $this->assertSame(1, FormSubmission::query()->count());
        $this->assertSame('Final', $submitted->refresh()->values['bemerkung']);
    }

    public function test_offline_sync_submits_the_draft_instead_of_a_second_submission(): void {
        $this->startDraft();
        $draft = $this->draft();

        $ref = app(\App\Services\Form\Sync\FormSubmissionSyncHandler::class)->handle($this->user, 'form.submission', [
            'template' => $this->template->sqid,
            'draft' => $draft->sqid,
            'values' => ['bemerkung' => 'offline', 'messwert' => '1', 'zustand' => 'gut'],
            'notes' => ['zustand' => 'im Keller erfasst'],
        ]);

        $this->assertSame('form_submissions:' . $draft->id, $ref);
        $this->assertSame(1, FormSubmission::query()->count());
        $this->assertSame(FormSubmissionStatus::Submitted, $draft->refresh()->status);
        $this->assertSame('im Keller erfasst', $draft->fieldDocumentValues()->note('zustand'));
    }

    public function test_draft_follows_a_changed_template_and_keeps_old_answers_as_notes(): void {
        $this->startDraft();
        $this->saveField('bemerkung', ['value' => 'leicht verkratzt', 'version' => 1])->assertOk();

        $fields = $this->template->fields;
        $fields[1] = ['key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => 'choice', 'required' => true, 'options' => ['ok', 'nicht ok'], 'help' => null, 'unit' => null];
        $this->template->update(['fields' => $fields]);

        $this->actingAs($this->user)->get(route('form-submissions.edit', $this->draft()))
            ->assertOk()
            ->assertSee(e(__('form.draft.rebased')), false)
            ->assertSee('leicht verkratzt');

        $draft = $this->draft();
        $this->assertSame($fields, $draft->fields_snapshot);
        $this->assertNull($draft->fieldDocumentValues()->get('bemerkung'));
        $this->assertSame('leicht verkratzt', $draft->fieldDocumentValues()->note('bemerkung'));
        $this->assertSame(3, $draft->version);
    }

    public function test_discarding_removes_only_own_drafts(): void {
        $this->startDraft();
        $draft = $this->draft();
        $colleague = User::factory()->user()->create(['organization_id' => $this->user->organization_id]);

        $this->actingAs($colleague)->delete(route('form-submissions.destroy', $draft))->assertForbidden();
        $this->actingAs($this->user)->delete(route('form-submissions.destroy', $draft))->assertRedirect();
        $this->assertSoftDeleted('form_submissions', ['id' => $draft->id]);

        $submitted = FormSubmission::factory()->create([
            'organization_id' => $this->user->organization_id,
            'form_template_id' => $this->template->id,
            'submitted_by_user_id' => $this->user->id,
        ]);
        $this->actingAs($this->user)->delete(route('form-submissions.destroy', $submitted))->assertForbidden();
    }

    public function test_pdf_prints_like_paper_with_checkboxes_and_page_footer(): void {
        $submission = FormSubmission::factory()->create([
            'organization_id' => $this->user->organization_id,
            'form_template_id' => $this->template->id,
            'fields_snapshot' => $this->template->fields,
            'values' => ['bemerkung' => 'Alles gut', 'messwert' => 3.0, 'zustand' => 'mittel', 'geprueft' => true],
            'submitted_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('form-submissions.pdf', $submission))->assertOk();
        $text = $this->pdfText($response);

        $this->assertStringContainsString(__('fields.paper.topic'), $text);
        $this->assertStringContainsString(__('fields.paper.details'), $text);
        $this->assertStringContainsString('☑ mittel', $text);
        $this->assertStringContainsString('☐ defekt', $text);
        $this->assertMatchesRegularExpression('/' . preg_quote((string) __('fields.paper.page'), '/') . '\s*1\s*' . preg_quote((string) __('fields.paper.of_pages', ['pages' => 1]), '/') . '/u', $text);
        $this->assertStringContainsString(__('fields.paper.as_of'), $text);
    }

    /** @return TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function startDraft(?DiaryEntry $subject = null): TestResponse {
        return $this->actingAs($this->user)->postJson(route('form-submissions.drafts.store'), array_filter([
            'form_template_id' => $this->template->sqid,
            'subject_kind' => $subject !== null ? 'diary' : null,
            'subject_id' => $subject !== null ? Sqid::encode(DiaryEntry::class, $subject->id) : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function saveField(string $key, array $payload): TestResponse {
        return $this->actingAs($this->user)->patchJson($this->fieldUrl($this->draft(), $key), $payload);
    }

    private function fieldUrl(FormSubmission $draft, string $key): string {
        return route('field-documents.autosave', ['type' => $draft->getMorphClass(), 'document' => $draft->sqid, 'field' => $key]);
    }

    private function draft(): FormSubmission {
        return FormSubmission::query()->latest('id')->firstOrFail();
    }

    /** @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response */
    private function pdfText(TestResponse $response): string {
        try {
            \PDFToolkit\Config\Config::getInstance();
        } catch (\Throwable $e) {
            $this->markTestSkipped('PDF-Toolkit-Konfiguration unvollständig: ' . $e->getMessage());
        }
        $path = tempnam(sys_get_temp_dir(), 'wd-form-') . '.pdf';
        file_put_contents($path, (string) $response->getContent());
        try {
            return \PDFToolkit\Registries\PDFReaderRegistry::getInstance()->extractTextOnly($path)->getTextOrDefault();
        } finally {
            @unlink($path);
        }
    }
}
