<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalDocumentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Models\{Customer, DiaryEntry, Document, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Welle D — Dokument-Spiegelung ins Kundenportal: das Portal zeigt und liefert
 * NUR fürs Kundenportal freigegebene Dokumente des eigenen Kunden. Leak-Tests
 * (intern/fremder Kunde/nicht freigegeben/fremde Org sind nie sichtbar oder
 * ladbar) und sicherer Download.
 */
class CustomerPortalDocumentsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    private User $internalUser;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->internalUser = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
    }

    /**
     * Erzeugt ein Dokument samt Datei-Version. `$visible` steuert die
     * Kundenfreigabe, `$documentable` den Bezug (Kunde/Auftrag/…).
     */
    private function makeDocument(string $title, bool $visible, ?string $documentableType, ?int $documentableId, ?int $organizationId = null): Document {
        $orgId = $organizationId ?? (int) $this->organization->id;

        /** @var Document $document */
        $document = Document::factory()->create([
            'organization_id' => $orgId,
            'created_by_user_id' => $this->internalUser->id,
            'title' => $title,
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'customer_visible' => $visible,
            'customer_released_at' => $visible ? now() : null,
            'customer_released_by' => $visible ? $this->internalUser->id : null,
        ]);

        $path = 'documents/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 ' . $title);
        $version = $document->versions()->create([
            'version_no' => 1,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::slug($title) . '.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'uploaded_by_user_id' => $this->internalUser->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $document->refresh();
    }

    private function releasedOwnCustomerDoc(string $title = 'Freigegebener Kundenvertrag'): Document {
        return $this->makeDocument($title, true, Customer::class, (int) $this->customer->id);
    }

    public function test_portal_lists_only_released_own_documents(): void {
        $ownReleased = $this->releasedOwnCustomerDoc('Freigegebener Kundenvertrag');

        // Freigegebenes Dokument an einem eigenen Auftrag (Auftragsbezug).
        $ownDiary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $diaryDoc = $this->makeDocument('Freigegebenes Auftragsdokument', true, DiaryEntry::class, (int) $ownDiary->id);

        // Nicht freigegebenes Dokument des eigenen Kunden → intern, unsichtbar.
        $this->makeDocument('Internes Kundendokument', false, Customer::class, (int) $this->customer->id);

        // Freigegebenes Dokument eines FREMDEN Kunden derselben Org.
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->makeDocument('Fremdes Kundendokument', true, Customer::class, (int) $otherCustomer->id);

        $response = $this->actingAs($this->portalUser, 'customer')->get(route('customer.documents.index'));

        $response->assertOk();
        $response->assertSee('Freigegebener Kundenvertrag');
        $response->assertSee('Freigegebenes Auftragsdokument');
        $response->assertDontSee('Internes Kundendokument');
        $response->assertDontSee('Fremdes Kundendokument');

        unset($ownReleased, $diaryDoc);
    }

    public function test_download_serves_only_released_own_document(): void {
        $own = $this->releasedOwnCustomerDoc();
        $version = $own->currentVersion;
        $this->assertNotNull($version);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.documents.download', $own))
            ->assertOk()
            ->assertDownload($version->original_name);
    }

    public function test_download_rejects_internal_document_of_own_customer(): void {
        $internal = $this->makeDocument('Internes Kundendokument', false, Customer::class, (int) $this->customer->id);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.documents.download', $internal))
            ->assertNotFound();
    }

    public function test_download_rejects_foreign_customer_document(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = $this->makeDocument('Fremdes Kundendokument', true, Customer::class, (int) $otherCustomer->id);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.documents.download', $foreign))
            ->assertNotFound();
    }

    public function test_download_rejects_released_document_without_customer_link(): void {
        // Sicherheitsnetz: selbst ein (fehlerhaft) freigegebenes Dokument OHNE
        // Kundenbezug darf im Portal nicht ladbar sein.
        $free = $this->makeDocument('Freies freigegebenes Dokument', true, null, null);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.documents.download', $free))
            ->assertNotFound();
    }

    public function test_portal_documents_are_organization_isolated(): void {
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = $this->makeDocument('Fremd-Org-Dokument', true, Customer::class, (int) $otherCustomer->id, (int) $otherOrg->id);

        $index = $this->actingAs($this->portalUser, 'customer')->get(route('customer.documents.index'));
        $index->assertOk();
        $index->assertDontSee('Fremd-Org-Dokument');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.documents.download', $foreign))
            ->assertNotFound();
    }

    public function test_guest_cannot_access_portal_documents(): void {
        $this->get(route('customer.documents.index'))->assertRedirect(route('customer.login'));
    }
}
