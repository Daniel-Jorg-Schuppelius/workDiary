<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Form;

use App\Models\{DiaryEntry, FormSubmission, FormTemplate, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ausgefüllte Formulare (Feature 032): Submit-Happy-Path, Validierung je
 * Feldtyp, Snapshot-Stabilität, Liste + Filter, Panel am Auftrag, Cross-Org.
 */
class FormSubmissionTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_submit_active_template_with_subject(): void {
        $user = User::factory()->user()->create();
        $template = $this->makeActiveTemplateFor($user);
        $entry = $this->makeDiaryEntryFor($user, 'Wartung Heizung EG');

        $this->actingAs($user)
            ->post(route('form-submissions.store'), [
                'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
                'subject_kind' => 'diary',
                'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id),
                'values' => [
                    'bemerkung' => 'Alles in Ordnung',
                    'messwert' => '42.5',
                    'datum' => '2026-06-01',
                    'zustand' => 'gut',
                    'geprueft' => '1',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('form_submissions', [
            'form_template_id' => $template->id,
            'organization_id' => $user->organization_id,
            'submitted_by_user_id' => $user->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
        ]);

        app()->instance('currentOrganization', $user->organization);
        $submission = FormSubmission::query()->firstOrFail();

        // Snapshot = Felddefinition zum Ausfüllzeitpunkt.
        $this->assertSame($template->fields, $submission->fields_snapshot);
        // Werte typtreu normalisiert (number → float, checkbox → bool).
        $this->assertSame('Alles in Ordnung', $submission->values['bemerkung']);
        $this->assertSame(42.5, $submission->values['messwert']);
        $this->assertTrue($submission->values['geprueft']);
        $this->assertNull($submission->values['beschreibung']);
        $this->assertNotNull($submission->submitted_at);
    }

    public function test_submit_validates_each_field_type(): void {
        $user = User::factory()->user()->create();
        $template = $this->makeActiveTemplateFor($user, fields: [
            ['key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => 'text', 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'messwert', 'label' => 'Messwert', 'type' => 'number', 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'datum', 'label' => 'Datum', 'type' => 'date', 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'zustand', 'label' => 'Zustand', 'type' => 'select', 'required' => true, 'options' => ['gut', 'schlecht'], 'help' => null, 'unit' => null],
            ['key' => 'freigabe', 'label' => 'Freigabe', 'type' => 'checkbox', 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
        ]);

        $this->actingAs($user)
            ->postJson(route('form-submissions.store'), [
                'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => [
                    // bemerkung fehlt (required)
                    'messwert' => 'keine-zahl',
                    'datum' => 'kein-datum',
                    'zustand' => 'unbekannt',     // nicht in den Optionen
                    'freigabe' => '0',            // Pflicht-Checkbox nicht angehakt
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'values.bemerkung',
                'values.messwert',
                'values.datum',
                'values.zustand',
                'values.freigabe',
            ]);

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_inactive_template_cannot_be_submitted(): void {
        $user = User::factory()->user()->create();
        $draft = FormTemplate::factory()->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
        ]);

        // Ausfüll-Dialog für inaktive Vorlage existiert nicht (404) …
        $this->actingAs($user)
            ->get(route('form-submissions.create', ['template' => Sqid::encode(FormTemplate::class, $draft->id)]))
            ->assertNotFound();

        // … und ein direkter POST läuft ebenfalls ins Leere.
        $this->actingAs($user)
            ->postJson(route('form-submissions.store'), [
                'form_template_id' => Sqid::encode(FormTemplate::class, $draft->id),
                'values' => ['bemerkung' => 'x'],
            ])
            ->assertNotFound();
    }

    public function test_fields_snapshot_stays_stable_when_template_changes_later(): void {
        $user = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $user->organization_id]);
        $template = $this->makeActiveTemplateFor($user);

        $this->actingAs($user)
            ->post(route('form-submissions.store'), [
                'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => ['bemerkung' => 'Vorher', 'zustand' => 'gut'],
            ])
            ->assertRedirect();

        app()->instance('currentOrganization', $user->organization);
        $submission = FormSubmission::query()->firstOrFail();
        $snapshotBefore = $submission->fields_snapshot;

        // Vorlage wird später umgebaut (Label umbenannt, Feld entfernt).
        $this->actingAs($lead)
            ->put(route('form-templates.update', $template), [
                'name' => $template->name,
                'fields' => [
                    ['label' => 'Komplett anderes Feld', 'type' => 'text'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(['komplett_anderes_feld'], array_column($template->refresh()->fields, 'key'));
        // Der Snapshot der bestehenden Submission bleibt unverändert …
        $this->assertSame($snapshotBefore, $submission->refresh()->fields_snapshot);

        // … und die Read-Only-Seite rendert weiterhin die ALTEN Labels.
        $this->actingAs($user)
            ->get(route('form-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Bemerkung')
            ->assertSee('Vorher')
            ->assertDontSee('Komplett anderes Feld');
    }

    public function test_index_filters_by_template_and_period(): void {
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);

        $templateA = $this->makeActiveTemplateFor($lead, name: 'Protokoll Alpha');
        $templateB = $this->makeActiveTemplateFor($lead, name: 'Protokoll Beta');

        $old = FormSubmission::factory()->create([
            'organization_id' => $lead->organization_id,
            'form_template_id' => $templateA->id,
            'submitted_by_user_id' => $lead->id,
            'submitted_at' => '2026-01-15 10:00:00',
        ]);
        $recent = FormSubmission::factory()->create([
            'organization_id' => $lead->organization_id,
            'form_template_id' => $templateB->id,
            'submitted_by_user_id' => $lead->id,
            'submitted_at' => '2026-06-01 10:00:00',
        ]);

        // Globale Header-Zeitraumauswahl (Hausstandard) weit aufziehen, damit
        // hier ausschließlich der Vorlagen-Filter diskriminiert.
        $wideRange = [
            'ui.daterange.preset' => 'custom',
            'ui.daterange.from' => '2026-01-01',
            'ui.daterange.to' => '2026-12-31',
        ];

        // Filter nach Vorlage.
        $this->actingAs($lead)
            ->withSession($wideRange)
            ->get(route('form-submissions.index', ['template' => Sqid::encode(FormTemplate::class, $templateA->id)]))
            ->assertOk()
            ->assertSee('form-submission-' . $old->id)
            ->assertDontSee('form-submission-' . $recent->id);

        // Zeitraum kommt aus der globalen Header-Auswahl, nicht aus der
        // Filterleiste (kein from/to-Query-Parameter mehr).
        $this->actingAs($lead)
            ->withSession([
                'ui.daterange.preset' => 'custom',
                'ui.daterange.from' => '2026-05-01',
                'ui.daterange.to' => '2026-06-30',
            ])
            ->get(route('form-submissions.index'))
            ->assertOk()
            ->assertSee('form-submission-' . $recent->id)
            ->assertDontSee('form-submission-' . $old->id);
    }

    public function test_plain_user_sees_only_own_submissions_in_index(): void {
        $author = User::factory()->user()->create();
        $colleague = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        app()->instance('currentOrganization', $author->organization);

        $template = $this->makeActiveTemplateFor($author);
        $own = FormSubmission::factory()->create([
            'organization_id' => $author->organization_id,
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $author->id,
        ]);
        $foreign = FormSubmission::factory()->create([
            'organization_id' => $author->organization_id,
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $colleague->id,
        ]);

        $this->actingAs($author)
            ->get(route('form-submissions.index'))
            ->assertOk()
            ->assertSee('form-submission-' . $own->id)
            ->assertDontSee('form-submission-' . $foreign->id);

        // Teamleitung sieht beide.
        $this->actingAs($lead)
            ->get(route('form-submissions.index'))
            ->assertOk()
            ->assertSee('form-submission-' . $own->id)
            ->assertSee('form-submission-' . $foreign->id);
    }

    public function test_show_is_limited_to_owner_and_teamleitung(): void {
        $author = User::factory()->user()->create();
        $colleague = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        app()->instance('currentOrganization', $author->organization);

        $template = $this->makeActiveTemplateFor($author);
        $submission = FormSubmission::factory()->create([
            'organization_id' => $author->organization_id,
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $author->id,
        ]);

        $this->actingAs($author)->get(route('form-submissions.show', $submission))->assertOk();
        $this->actingAs($lead)->get(route('form-submissions.show', $submission))->assertOk();
        $this->actingAs($colleague)->get(route('form-submissions.show', $submission))->assertForbidden();
    }

    public function test_diary_show_page_renders_forms_panel_with_fill_action(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);

        $entry = $this->makeDiaryEntryFor($user, 'Wartung Heizung EG');
        $template = $this->makeActiveTemplateFor($user, name: 'Wartungsprotokoll Heizung');
        FormSubmission::factory()->create([
            'organization_id' => $user->organization_id,
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $user->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
        ]);

        $this->actingAs($user)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee(__('form.action.fill'))
            ->assertSee('Wartungsprotokoll Heizung');
    }

    public function test_fill_dialog_renders_fields_from_template(): void {
        $user = User::factory()->user()->create();
        $template = $this->makeActiveTemplateFor($user);

        $this->actingAs($user)
            ->get(route('form-submissions.create', ['template' => Sqid::encode(FormTemplate::class, $template->id)]))
            ->assertOk()
            ->assertSee('Bemerkung')
            ->assertSee('Zustand')
            ->assertSee('values[bemerkung]', false);
    }

    public function test_callcenter_without_permission_cannot_access_submissions(): void {
        $user = User::factory()->callcenter()->create();
        $template = $this->makeActiveTemplateFor($user);

        $this->actingAs($user)->get(route('form-submissions.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('form-submissions.store'), [
                'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => [],
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void {
        $this->get(route('form-submissions.index'))->assertRedirect(route('login'));
    }

    public function test_submission_and_cross_org_subject_are_isolated(): void {
        $user = User::factory()->user()->create();
        $stranger = User::factory()->user()->create(); // eigene Organisation
        app()->instance('currentOrganization', $user->organization);

        $template = $this->makeActiveTemplateFor($user);
        $submission = FormSubmission::factory()->create([
            'organization_id' => $user->organization_id,
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $user->id,
        ]);

        // Fremde Submission ist nicht erreichbar (Scope → 404).
        $this->actingAs($stranger)
            ->get(route('form-submissions.show', $submission))
            ->assertNotFound();

        // Fremde Vorlage ist nicht ausfüllbar.
        $this->actingAs($stranger)
            ->postJson(route('form-submissions.store'), [
                'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => ['bemerkung' => 'x'],
            ])
            ->assertNotFound();

        // Fremdes Subjekt (Auftrag aus anderer Org) wird nicht akzeptiert.
        $foreignEntry = $this->makeDiaryEntryFor($user, 'Fremder Auftrag');
        $strangerTemplate = $this->makeActiveTemplateFor($stranger);
        $this->actingAs($stranger)
            ->postJson(route('form-submissions.store'), [
                'form_template_id' => Sqid::encode(FormTemplate::class, $strangerTemplate->id),
                'subject_kind' => 'diary',
                'subject_id' => Sqid::encode(DiaryEntry::class, $foreignEntry->id),
                'values' => ['bemerkung' => 'x', 'zustand' => 'gut'],
            ])
            ->assertNotFound();
    }

    /**
     * @param  list<array{key: string, type: string, required?: bool}>  $extraFields
     * @return array{0: FormTemplate, 1: DiaryEntry}
     */
    private function templateWithFields(User $user, array $extraFields): array {
        $fields = [];
        foreach ($extraFields as $f) {
            $fields[] = [
                'key' => $f['key'],
                'label' => ucfirst($f['key']),
                'type' => $f['type'],
                'required' => $f['required'] ?? false,
                'options' => [],
                'help' => null,
                'unit' => null,
            ];
        }
        $template = $this->makeActiveTemplateFor($user, $fields);
        $entry = $this->makeDiaryEntryFor($user, 'Anhang-Formular');

        return [$template, $entry];
    }

    public function test_photo_and_file_fields_store_attachments(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();
        [$template, $entry] = $this->templateWithFields($user, [
            ['key' => 'foto', 'type' => 'photo'],
            ['key' => 'anhang', 'type' => 'file'],
        ]);

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
            'subject_kind' => 'diary',
            'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            'files' => [
                'foto' => UploadedFile::fake()->image('foto.jpg'),
                'anhang' => UploadedFile::fake()->create('bericht.pdf', 20, 'application/pdf'),
            ],
        ])->assertRedirect();

        app()->instance('currentOrganization', $user->organization);
        $submission = FormSubmission::query()->firstOrFail();

        $this->assertDatabaseHas('attachments', ['attachable_id' => $submission->id, 'meta_type' => 'field:foto']);
        $this->assertDatabaseHas('attachments', ['attachable_id' => $submission->id, 'meta_type' => 'field:anhang']);
        $this->assertSame('foto.jpg', $submission->values['foto']);
        $this->assertSame('bericht.pdf', $submission->values['anhang']);
    }

    public function test_signature_field_stores_png_attachment(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();
        [$template, $entry] = $this->templateWithFields($user, [
            ['key' => 'unterschrift', 'type' => 'signature'],
        ]);

        $binary = "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 64);
        $base64 = 'data:image/png;base64,' . base64_encode($binary);

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
            'subject_kind' => 'diary',
            'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            'signatures' => ['unterschrift' => $base64],
        ])->assertRedirect();

        app()->instance('currentOrganization', $user->organization);
        $submission = FormSubmission::query()->firstOrFail();

        $this->assertDatabaseHas('attachments', [
            'attachable_id' => $submission->id,
            'meta_type' => 'field:unterschrift',
            'mime' => 'image/png',
        ]);
        $this->assertSame('signed', $submission->values['unterschrift']);
    }

    public function test_required_file_field_missing_returns_error(): void {
        $user = User::factory()->user()->create();
        [$template, $entry] = $this->templateWithFields($user, [
            ['key' => 'pflichtdatei', 'type' => 'file', 'required' => true],
        ]);

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
            'subject_kind' => 'diary',
            'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id),
        ])->assertSessionHasErrors('values.pflichtdatei');

        $this->assertSame(0, FormSubmission::query()->count());
    }

    /**
     * Vorlage mit einem Bedingungsfeld: `schadenbeschreibung` (Pflicht) ist nur
     * sichtbar, wenn `schaden` = „ja". Basis für die Pflicht-je-Sichtbarkeit-Tests.
     */
    private function makeConditionalTemplateFor(User $user): FormTemplate {
        return $this->makeActiveTemplateFor($user, fields: [
            ['key' => 'schaden', 'label' => 'Schaden?', 'type' => 'select', 'required' => true, 'options' => ['ja', 'nein'], 'help' => null, 'unit' => null, 'visible_if' => null],
            ['key' => 'schadenbeschreibung', 'label' => 'Schadensbeschreibung', 'type' => 'text', 'required' => true, 'options' => [], 'help' => null, 'unit' => null, 'visible_if' => ['field' => 'schaden', 'op' => 'eq', 'value' => 'ja']],
        ]);
    }

    public function test_required_field_is_skipped_when_condition_hidden(): void {
        $user = User::factory()->user()->create();
        $template = $this->makeConditionalTemplateFor($user);

        // schaden = „nein" → Beschreibungsfeld unsichtbar → Pflicht entfällt.
        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
            'values' => ['schaden' => 'nein'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        app()->instance('currentOrganization', $user->organization);
        $submission = FormSubmission::query()->firstOrFail();

        $this->assertSame('nein', $submission->values['schaden']);
        // Unsichtbares Feld wird gar nicht gespeichert.
        $this->assertArrayNotHasKey('schadenbeschreibung', $submission->values);
    }

    public function test_required_field_is_enforced_when_condition_visible(): void {
        $user = User::factory()->user()->create();
        $template = $this->makeConditionalTemplateFor($user);

        // schaden = „ja" → Beschreibungsfeld sichtbar → Pflicht greift.
        $this->actingAs($user)->postJson(route('form-submissions.store'), [
            'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
            'values' => ['schaden' => 'ja'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.schadenbeschreibung']);

        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_condition_cycle_is_rejected_on_template_save(): void {
        $lead = User::factory()->teamleitung()->create();

        // Feld A sichtbar-wenn B, Feld B sichtbar-wenn A (Referenz per Label) → Zyklus.
        $this->actingAs($lead)->postJson(route('form-templates.store'), [
            'name' => 'Zyklische Bedingungen',
            'fields' => [
                ['label' => 'Feld A', 'type' => 'text', 'visible_if' => ['field' => 'Feld B', 'op' => 'filled', 'value' => '']],
                ['label' => 'Feld B', 'type' => 'text', 'visible_if' => ['field' => 'Feld A', 'op' => 'filled', 'value' => '']],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);

        $this->assertSame(0, FormTemplate::query()->count());
    }

    public function test_condition_reference_by_label_is_resolved_and_frozen_in_snapshot(): void {
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);

        // Editor referenziert das Bezugsfeld per Label „Schaden?" → Server löst
        // zum Key „schaden" auf.
        $this->actingAs($lead)->post(route('form-templates.store'), [
            'name' => 'Bedingtes Protokoll',
            'fields' => [
                ['label' => 'Schaden?', 'type' => 'select', 'options' => 'ja, nein'],
                ['label' => 'Schadensdetails', 'type' => 'text', 'visible_if' => ['field' => 'Schaden?', 'op' => 'eq', 'value' => 'ja']],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $template = FormTemplate::query()->firstOrFail();
        $fields = collect($template->fields)->keyBy('key');
        $this->assertSame('schaden', $fields['schadensdetails']['visible_if']['field']);

        $this->service()->activate($template, $lead);

        // schaden = „nein" → Beschreibung unsichtbar; Snapshot behält die Bedingung.
        $this->actingAs($lead)->post(route('form-submissions.store'), [
            'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
            'values' => ['schaden' => 'nein'],
        ])->assertRedirect();

        $submission = FormSubmission::query()->firstOrFail();
        $snapshotDetails = collect($submission->fields_snapshot)->firstWhere('key', 'schadensdetails');
        $this->assertSame('schaden', $snapshotDetails['visible_if']['field']);

        // Read-Only-Seite blendet das unsichtbare Feld aus.
        $this->actingAs($lead)
            ->get(route('form-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Schaden?')
            ->assertDontSee('Schadensdetails');
    }

    private function service(): \App\Services\Form\FormService {
        return app(\App\Services\Form\FormService::class);
    }

    private function submitFor(User $user): FormSubmission {
        $template = $this->makeActiveTemplateFor($user);
        $entry = $this->makeDiaryEntryFor($user, 'Wartung');

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_template_id' => Sqid::encode(FormTemplate::class, $template->id),
            'subject_kind' => 'diary',
            'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            'values' => ['bemerkung' => 'ok', 'messwert' => '10', 'datum' => '2026-06-01', 'zustand' => 'gut', 'geprueft' => '1'],
        ])->assertRedirect();

        app()->instance('currentOrganization', $user->organization);

        return FormSubmission::query()->firstOrFail();
    }

    public function test_submission_pdf_downloads(): void {
        $user = User::factory()->user()->create();
        $submission = $this->submitFor($user);

        $res = $this->actingAs($user)->get(route('form-submissions.pdf', $submission));

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertSame('%PDF', substr((string) $res->getContent(), 0, 4));
    }

    public function test_submission_pdf_is_org_scoped(): void {
        $user = User::factory()->user()->create();
        $submission = $this->submitFor($user);

        $stranger = User::factory()->user()->create(); // andere Organisation
        $this->actingAs($stranger)->get(route('form-submissions.pdf', $submission))->assertNotFound();
    }

    /**
     * Aktive Vorlage in der Organisation des Users.
     *
     * @param  list<array<string, mixed>>|null  $fields
     */
    private function makeActiveTemplateFor(User $creator, ?array $fields = null, ?string $name = null): FormTemplate {
        $attributes = [
            'organization_id' => $creator->organization_id,
            'created_by_user_id' => $creator->id,
        ];
        if ($fields !== null) {
            $attributes['fields'] = $fields;
        }
        if ($name !== null) {
            $attributes['name'] = $name;
        }

        return FormTemplate::factory()->active()->create($attributes);
    }

    private function makeDiaryEntryFor(User $user, string $title): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'title' => $title,
        ]);
    }
}
