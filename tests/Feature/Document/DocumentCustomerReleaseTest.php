<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentCustomerReleaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Document;

use App\Models\{Customer, Document, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Welle D — Dokument-Spiegelung ins Kundenportal: interne Kundenfreigabe
 * (freigeben/zurückziehen) inkl. Recht, Freigebbarkeits-Validierung und
 * Auditierung über den etablierten Audit-Weg.
 */
class DocumentCustomerReleaseTest extends TestCase {
    use RefreshDatabase;

    private function customerDoc(User $creator, Customer $customer): Document {
        return Document::factory()->create([
            'organization_id' => $creator->organization_id,
            'created_by_user_id' => $creator->id,
            'documentable_type' => Customer::class,
            'documentable_id' => $customer->id,
        ]);
    }

    public function test_author_can_release_customer_linked_document_and_it_is_audited(): void {
        $author = User::factory()->user()->create();
        $customer = Customer::factory()->create(['organization_id' => $author->organization_id]);
        $document = $this->customerDoc($author, $customer);

        $this->actingAs($author)
            ->from(route('documents.show', $document))
            ->post(route('documents.customer-release', $document))
            ->assertRedirect();

        $document->refresh();
        $this->assertTrue($document->customer_visible);
        $this->assertNotNull($document->customer_released_at);
        $this->assertSame($author->id, $document->customer_released_by);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.released_to_customer',
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_release_is_rejected_for_document_without_customer_link(): void {
        $author = User::factory()->user()->create();
        // Freies Dokument ohne Bezug → keinem Portal-Kunden zuordenbar.
        $document = Document::factory()->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $this->actingAs($author)
            ->from(route('documents.index'))
            ->post(route('documents.customer-release', $document))
            ->assertRedirect()
            ->assertSessionHas('error');

        $document->refresh();
        $this->assertFalse($document->customer_visible);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'document.released_to_customer',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_project_linked_document_without_customer_is_not_releasable(): void {
        $author = User::factory()->user()->create();
        // Internes Projekt (customer_id = null) → nicht freigebbar.
        $project = Project::factory()->create([
            'organization_id' => $author->organization_id,
            'customer_id' => null,
        ]);
        $document = Document::factory()->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
        ]);

        $this->actingAs($author)
            ->from(route('documents.index'))
            ->post(route('documents.customer-release', $document))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($document->refresh()->customer_visible);
    }

    public function test_revoke_clears_release_and_is_audited(): void {
        $author = User::factory()->user()->create();
        $customer = Customer::factory()->create(['organization_id' => $author->organization_id]);
        $document = Document::factory()->releasedToCustomer()->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
            'documentable_type' => Customer::class,
            'documentable_id' => $customer->id,
            'customer_released_by' => $author->id,
        ]);

        $this->actingAs($author)
            ->from(route('documents.show', $document))
            ->post(route('documents.customer-revoke', $document))
            ->assertRedirect();

        $document->refresh();
        $this->assertFalse($document->customer_visible);
        $this->assertNull($document->customer_released_at);
        $this->assertNull($document->customer_released_by);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.revoked_from_customer',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_plain_user_cannot_release_foreign_document(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $customer = Customer::factory()->create(['organization_id' => $author->organization_id]);
        $document = $this->customerDoc($author, $customer);

        $this->actingAs($other)
            ->post(route('documents.customer-release', $document))
            ->assertForbidden();

        $this->assertFalse($document->refresh()->customer_visible);
    }

    public function test_teamleitung_can_release_foreign_document(): void {
        $author = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        $customer = Customer::factory()->create(['organization_id' => $author->organization_id]);
        $document = $this->customerDoc($author, $customer);

        $this->actingAs($lead)
            ->from(route('documents.show', $document))
            ->post(route('documents.customer-release', $document))
            ->assertRedirect();

        $this->assertTrue($document->refresh()->customer_visible);
    }
}
