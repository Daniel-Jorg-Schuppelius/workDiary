<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalAssetsAndPhotosTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\Protocol\ProtocolVisibility;
use App\Models\{Asset, Attachment, AttachmentConfirmation, Customer, CustomerQuery, DiaryEntry, Protocol, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Ränge 50/54/55: Portal-Objektakte, Auftragsdetail (Fotos/Material/PDF)
 * und Foto-Bestätigung — Kunden-Scoping, Sichtbarkeitsschnitt, signierte
 * PDF-Links.
 */
class CustomerPortalAssetsAndPhotosTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // Portal-Bereichsfreigaben (MVP-511): Bestandstests laufen im Kompat-Vollumfang.
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
    }

    /**
     * Vollaudit 2026-07 (H9): Nur freigegebene Kommunikationsnotizen
     * (visibility=customer) erscheinen im Portal — interne nie.
     */
    public function test_portal_shows_only_published_communication_notes(): void {
        $diary = $this->ownDiary();
        \App\Models\CommunicationNote::factory()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => DiaryEntry::class,
            'notable_id' => $diary->id,
            'subject' => 'Freigegebene Rückmeldung',
            'visibility' => \App\Enums\Communication\CommunicationVisibility::Customer->value,
        ]);
        \App\Models\CommunicationNote::factory()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => DiaryEntry::class,
            'notable_id' => $diary->id,
            'subject' => 'Interne Einschätzung',
            'visibility' => \App\Enums\Communication\CommunicationVisibility::Internal->value,
        ]);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.diary.show', $diary))
            ->assertOk()
            ->assertSee('Freigegebene Rückmeldung')
            ->assertDontSee('Interne Einschätzung');
    }

    private function ownDiary(): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    private function photo(DiaryEntry $diary, bool $visible): Attachment {
        return Attachment::factory()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $diary->getMorphClass(),
            'attachable_id' => $diary->id,
            'original_name' => $visible ? 'freigegeben.jpg' : 'intern.jpg',
            'customer_visible' => $visible,
        ]);
    }

    public function test_diary_show_is_scoped_and_filters_visibility(): void {
        $diary = $this->ownDiary();
        $this->photo($diary, true);
        $this->photo($diary, false);

        Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => $diary->getMorphClass(),
            'subject_id' => $diary->id,
            'created_by_user_id' => $this->portalUser->id,
            'title' => 'Internes Protokoll',
            'visibility' => ProtocolVisibility::Internal->value,
        ]);

        $response = $this->actingAs($this->portalUser, 'customer')->get(route('customer.diary.show', $diary));

        $response->assertOk();
        $response->assertSee('freigegeben.jpg');
        $response->assertDontSee('intern.jpg');
        $response->assertDontSee('Internes Protokoll');

        // Fremder Auftrag (anderer Kunde derselben Org): 403.
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.diary.show', $foreign))->assertForbidden();
    }

    public function test_pdf_needs_valid_signature(): void {
        $diary = $this->ownDiary();

        // Ohne Signatur: 403.
        $this->get('/customer-portal/diary/' . $diary->getRouteKey() . '/pdf')->assertForbidden();

        // Der signierte Link von der Detailseite funktioniert (24 h).
        $page = $this->actingAs($this->portalUser, 'customer')->get(route('customer.diary.show', $diary));
        $page->assertOk();
        preg_match('/href="([^"]*diary[^"]*pdf[^"]*)"/', (string) $page->getContent(), $m);
        $link = $m[1] ?? '';
        $this->assertNotSame('', $link, 'PDF-Link fehlt auf der Detailseite.');

        $pdf = $this->get(html_entity_decode($link));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
    }

    public function test_photo_confirmation_is_once_and_complaint_raises_query(): void {
        $diary = $this->ownDiary();
        $photo = $this->photo($diary, true);
        $internal = $this->photo($diary, false);

        // Bestätigen — einmalig (DB-Unique + firstOrCreate).
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.diary.photos.confirm', [$diary, $photo]))
            ->assertRedirect();
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.diary.photos.confirm', [$diary, $photo]))
            ->assertRedirect();
        $this->assertSame(1, AttachmentConfirmation::query()->where('attachment_id', $photo->id)->count());

        // Nicht-freigegebene Fotos sind nicht bestätigbar (404).
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.diary.photos.confirm', [$diary, $internal]))
            ->assertNotFound();

        // Beanstandung erzeugt eine Kundenrückfrage am Auftrag.
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.diary.photos.complain', [$diary, $photo]), ['note' => 'Falsche Stelle fotografiert'])
            ->assertRedirect();
        $query = CustomerQuery::query()->firstOrFail();
        $this->assertSame($diary->getMorphClass(), $query->subject_type);
        $this->assertStringContainsString('Falsche Stelle fotografiert', (string) $query->question);
    }

    public function test_assets_portal_is_scoped_and_hides_internal_protocols(): void {
        $own = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Heizungsanlage Nord',
        ]);
        Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => $own->getMorphClass(),
            'subject_id' => $own->id,
            'created_by_user_id' => $this->portalUser->id,
            'title' => 'Kundensichtbares Prüfprotokoll',
            'visibility' => ProtocolVisibility::Customer->value,
        ]);
        Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => $own->getMorphClass(),
            'subject_id' => $own->id,
            'created_by_user_id' => $this->portalUser->id,
            'title' => 'Interner Defektbericht',
            'visibility' => ProtocolVisibility::Internal->value,
        ]);

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'name' => 'Fremdanlage',
        ]);

        $index = $this->actingAs($this->portalUser, 'customer')->get(route('customer.assets.index'));
        $index->assertOk();
        $index->assertSee('Heizungsanlage Nord');
        $index->assertDontSee('Fremdanlage');

        $show = $this->actingAs($this->portalUser, 'customer')->get(route('customer.assets.show', $own));
        $show->assertOk();
        $show->assertSee('Kundensichtbares Prüfprotokoll');
        $show->assertDontSee('Interner Defektbericht');

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.assets.show', $foreign))->assertForbidden();
    }
}
