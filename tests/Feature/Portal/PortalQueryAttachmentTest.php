<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalQueryAttachmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\{Attachment, Customer, CustomerQuery, DiaryEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, Storage, URL};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-712 (Vollscan G7): Rückfragen mit Anhängen — Upload-Policy wie das
 * Portal-Ticket, Download nur für den eigenen Kunden, intern sichtbar,
 * nach dem Absenden unveränderlich.
 */
final class PortalQueryAttachmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private DiaryEntry $diary;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        $this->diary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    /** @param list<UploadedFile> $files */
    private function raise(array $files, string $question = 'Bitte prüfen Sie das Foto.'): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->portalUser, 'customer')->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => $this->diary->sqid,
            'question' => $question,
            'files' => $files,
        ]);
    }

    public function test_store_attaches_customer_visible_files_and_audits(): void {
        $this->raise([
            UploadedFile::fake()->image('foto.jpg'),
            UploadedFile::fake()->create('protokoll.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('customer.queries.index'));

        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertCount(2, $query->attachments);
        $this->assertTrue($query->attachments->every(fn (Attachment $a): bool => (bool) $a->customer_visible));
        $this->assertSame((int) $this->organization->id, (int) $query->attachments->first()?->organization_id);
        Storage::disk('local')->assertExists((string) $query->attachments->first()?->path);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $query->getMorphClass(),
            'auditable_id' => $query->id,
            'event' => 'portal.query.attachments_added',
        ]);
    }

    public function test_store_without_files_still_works(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.queries.store'), [
            'subject_type' => 'diary',
            'subject' => $this->diary->sqid,
            'question' => 'Ohne Anhang',
        ])->assertRedirect(route('customer.queries.index'));

        $this->assertSame(0, CustomerQuery::query()->withoutGlobalScopes()->firstOrFail()->attachments()->count());
    }

    public function test_store_rejects_more_than_five_files(): void {
        $files = array_map(static fn (int $i): UploadedFile => UploadedFile::fake()->image("f$i.png"), range(1, 6));

        $this->raise($files)->assertSessionHasErrors('files');
        $this->assertSame(0, CustomerQuery::query()->withoutGlobalScopes()->count());
    }

    public function test_store_rejects_disallowed_file_type(): void {
        $this->raise([UploadedFile::fake()->create('script.exe', 5, 'application/x-msdownload')])
            ->assertSessionHasErrors('files');
        $this->assertSame(0, CustomerQuery::query()->withoutGlobalScopes()->count());
    }

    public function test_portal_list_links_own_attachment_and_download_serves_it(): void {
        $this->raise([UploadedFile::fake()->create('anleitung.pdf', 10, 'application/pdf')]);
        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();
        $attachment = $query->attachments()->firstOrFail();

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.queries.index'))
            ->assertOk()
            ->assertSee(route('customer.queries.attachments.download', [$query, $attachment]));

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.queries.attachments.download', [$query, $attachment]))
            ->assertOk()
            ->assertDownload('anleitung.pdf');
    }

    public function test_download_is_404_for_foreign_customer(): void {
        $this->raise([UploadedFile::fake()->create('geheim.pdf', 10, 'application/pdf')]);
        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();
        $attachment = $query->attachments()->firstOrFail();

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($otherCustomer);
        $stranger = User::factory()->kunde((int) $otherCustomer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger, 'customer')
            ->get(route('customer.queries.attachments.download', [$query, $attachment]))
            ->assertNotFound();
        $this->actingAs($stranger, 'customer')
            ->get(route('customer.queries.index'))
            ->assertOk()
            ->assertDontSee('geheim.pdf');
    }

    public function test_download_rejects_attachment_not_belonging_to_query_or_not_visible(): void {
        $this->raise([UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $this->raise([UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')], 'Zweite Frage');
        [$first, $second] = CustomerQuery::query()->withoutGlobalScopes()->orderBy('id')->get()->all();
        $foreignAttachment = $second->attachments()->firstOrFail();

        // Paar-Bindung: Anhang der zweiten Rückfrage am Parameter der ersten → 404.
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.queries.attachments.download', [$first, $foreignAttachment]))
            ->assertNotFound();

        // Intern zurückgezogene Sichtbarkeit → 404, auch am richtigen Vorgang.
        $own = $first->attachments()->firstOrFail();
        $own->forceFill(['customer_visible' => false])->save();
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.queries.attachments.download', [$first, $own]))
            ->assertNotFound();
    }

    public function test_internal_inbox_shows_attachment_and_deletion_is_blocked(): void {
        $this->raise([UploadedFile::fake()->create('nachweis.pdf', 10, 'application/pdf')]);
        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();
        $attachment = $query->attachments()->firstOrFail();

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('customer-queries.index'))
            ->assertOk()
            ->assertSee('nachweis.pdf');

        $this->actingAs($admin)
            ->get(URL::signedRoute('attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('nachweis.pdf');

        // Unveränderlich: auch Admins löschen Rückfrage-Anhänge nicht.
        $this->actingAs($admin)
            ->delete(route('attachments.destroy', $attachment))
            ->assertForbidden();
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    public function test_guest_cannot_download_query_attachments(): void {
        $this->raise([UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $query = CustomerQuery::query()->withoutGlobalScopes()->firstOrFail();
        $attachment = $query->attachments()->firstOrFail();

        Auth::guard('customer')->logout();
        $this->flushSession();
        $this->get(route('customer.queries.attachments.download', [$query, $attachment]))
            ->assertRedirect(route('customer.login'));
    }
}
