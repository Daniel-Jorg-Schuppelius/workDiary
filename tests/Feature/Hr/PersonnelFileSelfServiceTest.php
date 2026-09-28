<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileSelfServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Enums\Hr\{HrDocumentCategory, PersonnelFileSubmissionStatus};
use App\Enums\Notification\NotificationEvent;
use App\Models\Document\Document;
use App\Models\Hr\{PersonnelFileAcknowledgement, PersonnelFileSubmission};
use App\Models\Platform\{Organization, User};
use App\Services\Document\DocumentService;
use App\Services\Hr\{PersonnelFilePermissions, PersonnelFileService};
use App\Services\Privacy\SubjectData\PersonnelFileSection;
use App\Services\Privacy\UserAnonymizationService;
use App\Support\NotificationText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** MVP-987: Lesebestätigung und Einreichung zur Personalakte. */
final class PersonnelFileSelfServiceTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $hr;

    private User $member;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->member = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Erika Beispiel']);
        $this->hr = User::factory()->user()->create(['organization_id' => $this->org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->hr->assignRole(PersonnelFilePermissions::ROLE_PERSONALAKTE);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_read_confirmation_is_requested_given_once_per_version_and_shown_to_hr(): void {
        $this->actingAs($this->hr)->post(route('org.members.personnel-file.store', $this->member), [
            'title' => 'Betriebsvereinbarung Homeoffice', 'hr_category' => HrDocumentCategory::Other->value, 'is_ack_required' => '1',
            'file' => UploadedFile::fake()->create('bv.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $document = Document::query()->personnelFilesOf($this->member)->firstOrFail();
        $this->assertTrue($document->is_ack_required);

        $this->actingAs($this->member)->get(route('account.personnel-file'))->assertOk()
            ->assertSee(__('hr.personnel_file.ack.open'))->assertSee(route('account.personnel-file.acknowledge', $document));
        $this->actingAs($this->hr)->post(route('account.personnel-file.acknowledge', $document))->assertSessionHasErrors('document');

        $this->actingAs($this->member)->post(route('account.personnel-file.acknowledge', $document))->assertRedirect();
        $this->actingAs($this->member)->post(route('account.personnel-file.acknowledge', $document))->assertRedirect();
        $this->assertSame(1, PersonnelFileAcknowledgement::query()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'hrFile.acknowledged', 'auditable_id' => $document->id]);
        $this->actingAs($this->hr)->get(route('org.members.personnel-file.index', $this->member))->assertOk()
            ->assertSee(__('hr.personnel_file.ack.done', ['date' => now()->fdate()]));

        // Neue Version: erneut offen.
        app(DocumentService::class)->addVersion($document->refresh(), $this->hr, UploadedFile::fake()->create('bv-v2.pdf', 20, 'application/pdf'));
        $this->actingAs($this->member)->get(route('account.personnel-file'))->assertOk()->assertSee(__('hr.personnel_file.ack.open'));
    }

    public function test_submission_is_accepted_into_the_file_or_rejected_with_reason(): void {
        $this->actingAs($this->member)->get(route('account.personnel-file.submit-form'))->assertOk();
        $this->actingAs($this->member)->post(route('account.personnel-file.submit'), [
            'title' => 'Ersthelfer-Bescheinigung', 'hr_category' => HrDocumentCategory::Training->value, 'note' => 'Kurs vom März',
            'file' => UploadedFile::fake()->createWithContent('ersthelfer.pdf', '%PDF-1.4 Ersthelfer'),
        ])->assertRedirect(route('account.personnel-file'));
        $this->actingAs($this->member)->post(route('account.personnel-file.submit'), [
            'title' => 'Urlaubsfoto', 'hr_category' => HrDocumentCategory::Other->value,
            'file' => UploadedFile::fake()->createWithContent('foto.pdf', '%PDF-1.4 Foto'),
        ])->assertRedirect();
        [$first, $second] = PersonnelFileSubmission::query()->orderBy('id')->get()->all();
        $this->assertSame(PersonnelFileSubmissionStatus::Submitted, $first->status);
        Storage::disk('local')->assertExists((string) $first->path);

        // Die Person selbst entscheidet nicht; der Akten-Kreis sieht die Liste.
        $this->actingAs($this->member)->get(route('personnel-file.submissions.index'))->assertForbidden();
        $this->actingAs($this->member)->post(route('personnel-file.submissions.accept', $first), ['title' => 'X', 'hr_category' => 'other'])->assertForbidden();
        $this->actingAs($this->hr)->get(route('personnel-file.submissions.index'))->assertOk()->assertSee('Ersthelfer-Bescheinigung');
        $this->actingAs($this->hr)->get(route('org.members.personnel-file.index', $this->member))->assertOk()->assertSee(route('personnel-file.submissions.accept-form', $first));
        $this->actingAs($this->hr)->get(route('personnel-file.submissions.download', $first))->assertOk();

        $path = (string) $first->path;
        $this->actingAs($this->hr)->post(route('personnel-file.submissions.accept', $first), [
            'title' => 'Ersthelfer 2026', 'hr_category' => HrDocumentCategory::Training->value, 'is_ack_required' => '0',
        ])->assertRedirect(route('org.members.personnel-file.index', $this->member));
        $first->refresh();
        $this->assertSame(PersonnelFileSubmissionStatus::Accepted, $first->status);
        $this->assertNull($first->path);
        Storage::disk('local')->assertMissing($path);
        $document = Document::query()->personnelFilesOf($this->member)->sole();
        $this->assertSame('Ersthelfer 2026', $document->title);
        $this->assertSame($first->document_id, $document->id);
        $this->assertSame('%PDF-1.4 Ersthelfer', Storage::disk($document->currentVersion->disk)->get($document->currentVersion->path));

        $this->actingAs($this->hr)->post(route('personnel-file.submissions.reject', $second), ['review_note' => 'Kein Personalbezug'])->assertRedirect();
        $this->assertSame(PersonnelFileSubmissionStatus::Rejected, $second->refresh()->status);
        $this->assertNull($second->path);
        $this->actingAs($this->hr)->post(route('personnel-file.submissions.reject', $second), ['review_note' => 'Nochmal'])->assertSessionHasErrors('status');
        $this->actingAs($this->member)->get(route('account.personnel-file'))->assertOk()->assertSee('Kein Personalbezug');
    }

    public function test_nobody_decides_on_their_own_submission(): void {
        $this->actingAs($this->hr)->post(route('account.personnel-file.submit'), [
            'title' => 'Eigene Bescheinigung', 'hr_category' => HrDocumentCategory::Certificate->value,
            'file' => UploadedFile::fake()->createWithContent('eigen.pdf', '%PDF-1.4 eigen'),
        ])->assertRedirect();
        $own = PersonnelFileSubmission::query()->sole();

        $this->actingAs($this->hr)->get(route('personnel-file.submissions.index'))->assertOk()->assertDontSee('Eigene Bescheinigung');
        $this->actingAs($this->hr)->post(route('personnel-file.submissions.accept', $own), ['title' => 'Eigene', 'hr_category' => 'certificate'])->assertForbidden();
    }

    public function test_person_and_circle_are_notified_without_names_leaving_the_circle(): void {
        $direct = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $direct->givePermissionTo(PersonnelFilePermissions::VIEW_ANY);
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->member)->post(route('account.personnel-file.submit'), [
            'title' => 'Ersthelfer-Bescheinigung', 'hr_category' => HrDocumentCategory::Training->value,
            'file' => UploadedFile::fake()->createWithContent('ersthelfer.pdf', '%PDF-1.4 Ersthelfer'),
        ])->assertRedirect();
        $this->assertSame([NotificationEvent::HrFileSubmissionReceived->value], $this->events($this->hr));
        $this->assertSame([NotificationEvent::HrFileSubmissionReceived->value], $this->events($direct), 'Direktvergabe zählt zum Kreis');
        $this->assertSame([], $this->events($admin), 'Admins gehören nicht automatisch zum Kreis');
        $this->assertSame([], $this->events($this->member));
        $data = (array) $this->hr->notifications()->firstOrFail()->data;
        $this->assertStringNotContainsString('Erika', json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Ersthelfer', json_encode($data, JSON_THROW_ON_ERROR));

        $submission = PersonnelFileSubmission::query()->sole();
        $this->actingAs($this->hr)->post(route('personnel-file.submissions.accept', $submission), [
            'title' => 'Ersthelfer 2026', 'hr_category' => HrDocumentCategory::Training->value, 'is_ack_required' => '1',
        ])->assertRedirect();
        $this->assertEqualsCanonicalizing([NotificationEvent::HrFileAckRequested->value, NotificationEvent::HrFileSubmissionDecided->value], $this->events($this->member));

        $document = Document::query()->personnelFilesOf($this->member)->sole();
        $this->actingAs($this->hr)->post(route('documents.versions.store', $document), ['file' => UploadedFile::fake()->create('v2.pdf', 20, 'application/pdf')])->assertRedirect();
        $this->assertCount(2, array_keys($this->events($this->member), NotificationEvent::HrFileAckRequested->value), 'jede Fassung braucht eine eigene Bestätigung');

        $rejected = app(PersonnelFileService::class)->submit($this->member, ['title' => 'Urlaubsfoto', 'hr_category' => HrDocumentCategory::Other->value], UploadedFile::fake()->createWithContent('foto.pdf', '%PDF-1.4 Foto'));
        app(PersonnelFileService::class)->reject($rejected, $this->hr, 'Kein Personalbezug');
        $decision = $this->member->notifications()->get()->map(fn ($n): array => (array) $n->data)
            ->firstWhere('title_key', 'hr.personnel_file.notification.submission_rejected_title');
        $this->assertSame(__('hr.personnel_file.notification.submission_rejected_message', ['reason' => 'Kein Personalbezug']), NotificationText::message((array) $decision));
    }

    /** @return list<string> */
    private function events(User $user): array {
        return array_values($user->notifications()->get()->map(fn ($n): string => (string) (((array) $n->data)['event'] ?? ''))->all());
    }

    public function test_submissions_appear_in_the_subject_access_and_go_with_anonymisation(): void {
        $submission = app(PersonnelFileService::class)->submit($this->member, ['title' => 'Führerschein', 'hr_category' => HrDocumentCategory::IdDocument->value], UploadedFile::fake()->createWithContent('fs.pdf', '%PDF-1.4 fs'));
        $path = (string) $submission->path;

        $lists = app(PersonnelFileSection::class)->build($this->member)['lists'];
        $this->assertSame('Führerschein', $lists[__('hr.personnel_file.submission.title')][0]['title']);

        $this->member->forceFill(['deactivated_at' => now()->subYear(), 'left_at' => now()->subYear()->toDateString()])->save();
        app(UserAnonymizationService::class)->anonymize($this->member, $this->hr);

        $this->assertSame(0, PersonnelFileSubmission::query()->withoutGlobalScopes()->count());
        Storage::disk('local')->assertMissing($path);
    }
}
