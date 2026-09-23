<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSigningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractKind, ContractPartnerType, ContractStatus, ContractTermKind, EvidenceReviewStatus, IndexationMethod, SignatureLinkPurpose, SignatureParty, SignatureRequestStatus, SigningRevisionStatus};
use App\Enums\User\Permission;
use App\Models\Contract\{Contract, ContractSignatureLink, ContractSigningRevision};
use App\Models\Customer\Customer;
use App\Models\Document\{Document, DocumentVersion};
use App\Models\Platform\{Organization, User};
use App\Services\Contract\{ContractService, ContractSigningService};
use App\Services\Document\DocumentService;
use App\Support\MorphMap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Kundenvereinbarungen AVV/NDA (Feature 157, MVP-822): Fassungen mit
 * gebundenen Dateiversionen, Einmal-Links, Browser-Unterzeichnung, PDF-Upload
 * mit Prüfung, Gegenzeichnung, atomarer Abschluss, Rückzug, Ablösung,
 * Nachweis/Paket, Rechtegrenzen, Portal und Löschschutz — die zwölf
 * Abnahmekriterien des Feature-Docs.
 */
final class ContractSigningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    private User $admin;

    private Customer $customer;

    private Contract $contract;

    private DocumentVersion $contractVersion;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Müller GmbH', 'email' => 'mueller@example.org']);
        $this->contract = $this->makeAgreement();
        $this->contractVersion = $this->attachPdf($this->contract, 'AVV 2026-09');
    }

    // ── 1/2: Anlage je Kunde, Kundenpanel, Mandanten-/Rechtegrenzen ────────

    public function test_agreement_requires_customer_and_shows_in_customer_file(): void {
        $this->actingAs($this->admin)->post(route('contracts.store'), [
            'title' => 'NDA ohne Kunde',
            'kind' => ContractKind::NonDisclosure->value,
            'partner_type' => ContractPartnerType::Other->value,
            'partner_name' => 'Irgendwer',
            'term_kind' => ContractTermKind::OpenEnded->value,
            'starts_on' => '2026-09-01',
            'indexation_method' => IndexationMethod::None->value,
            'currency' => 'EUR',
            'value_period' => 'once',
        ])->assertSessionHasErrors('customer_id');

        $this->actingAs($this->admin)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee($this->contract->number)
            ->assertSee((string) __('contract-signing.customer_panel.title'));

        $this->actingAs($this->admin)->get(route('contracts.create', ['customer' => $this->customer->sqid, 'agreement' => 1]))
            ->assertOk()
            ->assertSee((string) __('contract-signing.dialog.new_agreement'))
            ->assertDontSee(ContractKind::Rent->label());
    }

    public function test_other_tenant_and_users_without_signing_rights_are_blocked(): void {
        $revision = $this->readyRevision();
        $request = $revision->customerRequest()->firstOrFail();

        // Factories verstellen den Spatie-Team-Kontext — vor der Rechtevergabe zurücksetzen.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $reader = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $reader->givePermissionTo([Permission::ContractViewAny->value, Permission::ContractView->value]);
        $this->actingAs($reader)->get(route('contracts.show', $this->contract))->assertOk();
        $this->actingAs($reader)->post(route('contracts.signing.requests.link', $request))->assertForbidden();
        $this->actingAs($reader)->post(route('contracts.signing.prepare', $revision))->assertForbidden();

        $otherOrg = Organization::factory()->create();
        $stranger = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($stranger)->get(route('contracts.signing.file', ['revision' => $revision, 'item' => 0]))->assertNotFound();
        $this->actingAs($stranger)->post(route('contracts.signing.requests.link', $request))->assertNotFound();
    }

    // ── 3/4/6/9: Bereitstellung, Link, Browser-Unterzeichnung, Gegenzeichnung, Nachweis ──

    public function test_full_signing_via_link_and_countersignature_produces_certificate_and_package(): void {
        $revision = $this->readyRevision();
        $this->assertSame(SigningRevisionStatus::Ready, $revision->status);
        $this->assertNotNull($revision->manifest_hash);
        $this->assertSame(64, strlen((string) $revision->manifestItems()->first()?->sha256));

        // Spätere DMS-Version ändert weder Ansicht noch Nachweis (3).
        app(DocumentService::class)->addVersionFromContents($this->contractVersion->document()->firstOrFail(), $this->admin, self::PDF . "% v2\n", 'avv-v2.pdf', 'application/pdf');

        $token = $this->issueLink($revision);
        $this->get(route('agreements.public-sign', ['token' => $token]))
            ->assertOk()
            ->assertSee($this->contract->title)
            ->assertSee('avv.pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('agreements.public-sign.file', ['token' => $token, 'item' => 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // Ohne Erklärung keine Unterschrift (10).
        $this->post(route('agreements.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Erika Müller', 'signature_method' => 'typed', 'typed_name' => 'Erika Müller',
        ])->assertSessionHasErrors(['declaration_accepted', 'authority_confirmed']);
        // Gezeichnet ohne Bild ebenfalls nicht.
        $this->post(route('agreements.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Erika Müller', 'signature_method' => 'drawn', 'declaration_accepted' => '1', 'authority_confirmed' => '1',
        ])->assertSessionHasErrors('signature');

        $this->post(route('agreements.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Erika Müller', 'signer_function' => 'Geschäftsführerin',
            'signature_method' => 'typed', 'typed_name' => 'Erika Müller',
            'declaration_accepted' => '1', 'authority_confirmed' => '1',
        ])->assertRedirect(route('agreements.public-thanks'));

        $revision->refresh();
        $this->assertSame(SigningRevisionStatus::PartiallySigned, $revision->status);
        $this->assertSame(SignatureRequestStatus::Signed, $revision->customerRequest()->firstOrFail()->status);
        // Link verbraucht (4).
        $this->get(route('agreements.public-sign', ['token' => $token]))->assertStatus(410);

        // Gegenzeichnung wird nicht übersprungen (6): Aktivierung noch blockiert.
        try {
            app(ContractService::class)->activate($this->contract->fresh(), $this->admin);
            $this->fail('Aktivierung ohne vollständige Unterzeichnung muss scheitern.');
        } catch (RuntimeException $e) {
            $this->assertSame((string) __('contract-signing.error.activate_unsigned'), $e->getMessage());
        }

        $orgRequest = $revision->organizationRequest()->firstOrFail();
        $this->actingAs($this->admin)->get(route('contracts.signing.requests.countersign', $orgRequest))->assertOk();
        $this->actingAs($this->admin)->post(route('contracts.signing.requests.countersign.store', $orgRequest), [
            'signer_name' => 'Daniel Schuppelius', 'signature_method' => 'drawn',
            'signature' => 'data:image/png;base64,' . base64_encode($this->png()),
            'declaration_accepted' => '1', 'authority_confirmed' => '1',
        ])->assertRedirect(route('contracts.show', $this->contract));

        $revision->refresh();
        $this->assertSame(SigningRevisionStatus::Signed, $revision->status);
        $this->assertNotNull($revision->completed_at);
        $this->assertCount(2, $revision->evidences);
        $drawn = $revision->evidences->firstWhere('party', SignatureParty::Organization);
        $this->assertTrue($drawn->hasFile());
        Storage::disk('local')->assertExists((string) $drawn->path);

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => MorphMap::stableKey(Contract::class), 'auditable_id' => $this->contract->id, 'event' => 'contract.signing.completed']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $this->contract->id, 'event' => 'contract.signing.signed']);
        // Review-Wiedervorlage über die Obligationen.
        $this->assertDatabaseHas('contract_obligations', ['contract_id' => $this->contract->id, 'kind' => 'review']);

        // Nachweis und Paket (9).
        $this->actingAs($this->admin)->get(route('contracts.signing.certificate', $revision))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->admin)->get(route('contracts.signing.package', $revision))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->actingAs($this->admin)->get(route('contracts.show', $this->contract))->assertOk()->assertSee((string) __('contract-signing.revision_status.signed'));

        // Jetzt ist die Aktivierung möglich.
        $this->actingAs($this->admin)->post(route('contracts.activate', $this->contract))->assertRedirect();
        $this->assertSame(ContractStatus::Active, $this->contract->fresh()->status);
    }

    // ── 5: Upload beider Wege, Prüfung, Erfasser ≠ Unterzeichner ───────────

    public function test_uploaded_pdf_counts_only_after_confirmed_review(): void {
        $revision = $this->readyRevision();
        $token = $this->issueLink($revision);

        $this->post(route('agreements.public-sign.upload', ['token' => $token]), [
            'evidence_file' => UploadedFile::fake()->createWithContent('unterschrieben.pdf', "%PDF-1.4\n/Encrypt 5 0 R\n%%EOF"),
            'signer_name' => 'Erika Müller',
        ])->assertStatus(410)->assertSee((string) __('contract-signing.error.pdf_encrypted'));

        $this->post(route('agreements.public-sign.upload', ['token' => $token]), [
            'evidence_file' => UploadedFile::fake()->createWithContent('unterschrieben.pdf', self::PDF),
            'signer_name' => 'Erika Müller',
            'stated_signed_on' => now()->subDay()->toDateString(),
        ])->assertRedirect(route('agreements.public-thanks'));

        $request = $revision->customerRequest()->firstOrFail();
        $this->assertSame(SignatureRequestStatus::EvidenceReceived, $request->status);
        $this->assertSame(SigningRevisionStatus::Ready, $revision->fresh()->status);
        $evidence = $request->evidences()->firstOrFail();
        $this->assertSame(EvidenceReviewStatus::Pending, $evidence->review_status);
        $this->assertNull($evidence->recorded_by_user_id);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $this->contract->id, 'event' => 'contract.signing.evidenceReceived']);

        // Ablehnung braucht Begründung, dann neuer Nachweis intern nachgetragen.
        $this->actingAs($this->admin)->post(route('contracts.signing.evidences.review', $evidence), ['review_decision' => 'reject'])
            ->assertSessionHasErrors('review_note');
        $this->actingAs($this->admin)->post(route('contracts.signing.evidences.review', $evidence), ['review_decision' => 'reject', 'review_note' => 'Seite 3 fehlt'])
            ->assertRedirect();
        $this->assertSame(EvidenceReviewStatus::Rejected, $evidence->fresh()->review_status);
        $this->assertSame(SignatureRequestStatus::EvidenceRejected, $request->fresh()->status);

        $this->actingAs($this->admin)->post(route('contracts.signing.requests.upload.store', $request), [
            'evidence_file' => UploadedFile::fake()->createWithContent('post.pdf', self::PDF),
            'signer_name' => 'Erika Müller',
        ])->assertRedirect(route('contracts.show', $this->contract));
        $second = $request->evidences()->reorder('id', 'desc')->firstOrFail();
        $this->assertSame($this->admin->id, $second->recorded_by_user_id);
        $this->assertSame('Erika Müller', $second->signer_name);

        $this->actingAs($this->admin)->post(route('contracts.signing.evidences.review', $second), ['review_decision' => 'accept'])->assertRedirect();
        $this->assertSame(SignatureRequestStatus::Signed, $request->fresh()->status);
        $this->assertSame(SigningRevisionStatus::PartiallySigned, $revision->fresh()->status);
        // Ein zweiter Prüfdurchgang ist unmöglich (append-only).
        $this->actingAs($this->admin)->post(route('contracts.signing.evidences.review', $second), ['review_decision' => 'accept'])
            ->assertSessionHasErrors('revision');
    }

    // ── 4/8: Token-Lebenszyklus, Rückzug, Revision ─────────────────────────

    public function test_expired_revoked_and_unknown_links_are_rejected_and_withdrawal_keeps_signatures(): void {
        $revision = $this->readyRevision();
        $this->get(route('agreements.public-sign', ['token' => str_repeat('x', 48)]))->assertStatus(410);

        $first = $this->issueLink($revision);
        // Neuer Link sperrt den bisherigen.
        $second = $this->issueLink($revision);
        $this->get(route('agreements.public-sign', ['token' => $first]))->assertStatus(410);
        $this->get(route('agreements.public-sign', ['token' => $second]))->assertOk();

        ContractSignatureLink::query()->latest('id')->firstOrFail()->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->get(route('agreements.public-sign', ['token' => $second]))->assertStatus(410);

        $third = $this->issueLink($revision);
        $link = ContractSignatureLink::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('contracts.signing.links.revoke', $link))->assertRedirect();
        $this->get(route('agreements.public-sign', ['token' => $third]))->assertStatus(410);

        // Kundenseite unterzeichnet, dann Rückzug: Link gesperrt, Signatur bleibt.
        $fourth = $this->issueLink($revision);
        $this->signTyped($fourth);
        $this->assertSame(SigningRevisionStatus::PartiallySigned, $revision->fresh()->status);
        $orgToken = app(ContractSigningService::class)->issueSignLink($revision->organizationRequest()->firstOrFail(), $this->admin);
        $this->actingAs($this->admin)->post(route('contracts.signing.withdraw', $revision), ['reason' => 'Anlage falsch'])->assertRedirect();
        $revision->refresh();
        $this->assertSame(SigningRevisionStatus::Withdrawn, $revision->status);
        $this->assertNotNull($orgToken['link']->fresh()->revoked_at);
        $this->assertCount(1, $revision->evidences);

        // Entwurf ersetzt keinen Vorgänger; neue Fassung ist wieder möglich.
        $next = $this->draftRevision();
        $this->assertSame(2, $next->revision_no);
        $this->actingAs($this->admin)->post(route('contracts.signing.supersede', $next), ['predecessor_id' => $revision->sqid, 'effective_on' => now()->toDateString()])
            ->assertSessionHasErrors('revision');
    }

    public function test_second_signed_revision_supersedes_predecessor_explicitly(): void {
        $first = $this->signedRevision();
        $second = $this->signedRevision();
        $this->assertSame(2, $second->revision_no);
        $this->assertSame(SigningRevisionStatus::Signed, $first->fresh()->status, 'Ein Nachfolger ersetzt nie automatisch.');

        $this->actingAs($this->admin)->post(route('contracts.signing.supersede', $second), ['predecessor_id' => $first->sqid, 'effective_on' => '2026-10-01'])->assertRedirect();
        $this->assertSame(SigningRevisionStatus::Superseded, $first->fresh()->status);
        $this->assertSame($second->id, $first->fresh()->superseded_by_id);
        $this->assertSame('2026-10-01', $second->fresh()->effective_on?->toDateString());
        // Vorgänger bleibt abrufbar.
        $this->actingAs($this->admin)->get(route('contracts.signing.certificate', $first))->assertOk();
    }

    // ── 7: Konkurrenz, Manifest-Integrität ──────────────────────────────────

    public function test_changed_file_blocks_delivery_and_completion(): void {
        $revision = $this->readyRevision();
        $token = $this->issueLink($revision);
        Storage::disk('local')->put($this->contractVersion->path, self::PDF . "% manipuliert\n");

        $this->get(route('agreements.public-sign', ['token' => $token]))->assertStatus(409);
        $this->post(route('agreements.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Erika Müller', 'signature_method' => 'typed', 'typed_name' => 'Erika Müller',
            'declaration_accepted' => '1', 'authority_confirmed' => '1',
        ])->assertStatus(410);
        $this->assertSame(SigningRevisionStatus::Ready, $revision->fresh()->status);
        $this->assertCount(0, $revision->evidences);
    }

    public function test_double_submission_does_not_fulfil_a_request_twice(): void {
        $revision = $this->readyRevision();
        $token = $this->issueLink($revision);
        $this->signTyped($token);
        // Zweiter Versuch über einen frischen Link derselben Anforderung: Anforderung ist erfüllt.
        try {
            app(ContractSigningService::class)->issueSignLink($revision->customerRequest()->firstOrFail(), $this->admin);
            $this->fail('Erfüllte Anforderung darf keinen Link mehr bekommen.');
        } catch (RuntimeException $e) {
            $this->assertSame((string) __('contract-signing.error.request_fulfilled'), $e->getMessage());
        }
        $this->assertCount(1, $revision->fresh()->evidences);
    }

    // ── 9: Abruflink und Portal ─────────────────────────────────────────────

    public function test_download_link_and_portal_serve_only_released_results(): void {
        $revision = $this->signedRevision();

        $this->actingAs($this->admin)->post(route('contracts.signing.download-link', $revision))->assertRedirect()->assertSessionHas('signing_link');
        $url = (string) session('signing_link');
        $this->get($url)->assertOk()->assertSee((string) __('contract-signing.action.package'));
        $token = basename($url);
        $this->get(route('agreements.public-download.package', ['token' => $token]))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->get(route('agreements.public-download.certificate', ['token' => $token]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        // Ein Signaturtoken ist kein Abruflink.
        $this->get(route('agreements.public-sign', ['token' => $token]))->assertStatus(410);

        // Versand per Mail.
        $this->actingAs($this->admin)->post(route('contracts.signing.download-link', $revision), ['signer_email' => 'mueller@example.org'])->assertRedirect();
        Mail::assertSent(\App\Mail\AgreementLinkMail::class, fn ($mail) => $mail->hasTo('mueller@example.org') && $mail->purpose === SignatureLinkPurpose::Download);

        // Portal: Capability + Freigabe.
        $this->allowPortal($this->customer, ['agreements']);
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->actingAs($portalUser, 'customer')->get(route('customer.agreements.index'))
            ->assertOk()->assertSee($this->contract->title)->assertSee((string) __('contract-signing.portal.not_released'));
        $this->actingAs($portalUser, 'customer')->get(route('customer.agreements.package', ['revision' => $revision->sqid]))->assertNotFound();

        $this->actingAs($this->admin)->post(route('contracts.signing.portal-release', $revision))->assertRedirect();
        $this->actingAs($portalUser, 'customer')->get(route('customer.agreements.package', ['revision' => $revision->sqid]))->assertOk();
        $this->actingAs($portalUser, 'customer')->get(route('customer.agreements.certificate', ['revision' => $revision->sqid]))->assertOk();

        // Anderer Kunde derselben Organisation sieht nichts.
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($other, ['agreements']);
        $otherUser = User::factory()->kunde((int) $other->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->actingAs($otherUser, 'customer')->get(route('customer.agreements.index'))->assertOk()->assertDontSee($this->contract->title);
        $this->actingAs($otherUser, 'customer')->get(route('customer.agreements.package', ['revision' => $revision->sqid]))->assertNotFound();

        // Ohne Capability 404 — frische Instanz, die Kundenrelation ist am User gecacht.
        $this->allowPortal($this->customer, ['documents']);
        $this->actingAs($portalUser->fresh(), 'customer')->get(route('customer.agreements.index'))->assertNotFound();
    }

    // ── 11: Löschschutz ─────────────────────────────────────────────────────

    public function test_bound_document_versions_survive_delete_paths(): void {
        $revision = $this->readyRevision();
        $document = $this->contractVersion->document()->firstOrFail();

        try {
            app(DocumentService::class)->delete($document, $this->admin);
            $this->fail('Gebundenes Dokument darf nicht gelöscht werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);

        $this->expectException(QueryException::class);
        DB::table('document_versions')->where('id', $this->contractVersion->id)->delete();
        $this->assertSame(SigningRevisionStatus::Ready, $revision->fresh()->status);
    }

    // ── Helfer ──────────────────────────────────────────────────────────────

    private function makeAgreement(): Contract {
        return app(ContractService::class)->create($this->organization, $this->admin, [
            'title' => 'Auftragsverarbeitung Müller',
            'kind' => ContractKind::DataProcessing->value,
            'partner_type' => ContractPartnerType::Customer->value,
            'customer_id' => $this->customer->id,
            'term_kind' => ContractTermKind::OpenEnded->value,
            'starts_on' => '2026-09-01',
            'indexation_method' => IndexationMethod::None->value,
            'currency' => 'EUR',
            'value_period' => 'once',
            'responsible_user_id' => $this->admin->id,
        ]);
    }

    private function attachPdf(Contract $contract, string $title): DocumentVersion {
        $document = app(DocumentService::class)->createFromContents($contract, $this->admin, ['title' => $title, 'document_type' => 'contract'], self::PDF, 'avv.pdf', 'application/pdf');
        /** @var Document $document */
        return $document->currentVersion()->firstOrFail();
    }

    private function draftRevision(): ContractSigningRevision {
        return app(ContractSigningService::class)->createRevision($this->contract->fresh(), $this->admin, [
            'contract_version_id' => $this->contractVersion->id,
            'attachment_version_ids' => [],
            'controller_party' => SignatureParty::Customer->value,
            'declaration_text' => 'Ich stimme dem Vertrag samt Anlagen zu.',
            'review_on' => now()->addYear()->toDateString(),
            'customer' => ['signer_name' => 'Erika Müller', 'signer_function' => 'GF', 'signer_email' => 'mueller@example.org'],
            'organization' => ['required' => true, 'signer_name' => 'Daniel Schuppelius', 'signer_function' => 'Inhaber', 'signer_email' => null, 'waiver_reason' => null],
        ]);
    }

    private function readyRevision(): ContractSigningRevision {
        $revision = $this->draftRevision();
        $this->actingAs($this->admin)->post(route('contracts.signing.prepare', $revision))->assertRedirect();

        return $revision->fresh(['manifestItems', 'requests']) ?? $revision;
    }

    private function signedRevision(): ContractSigningRevision {
        $revision = $this->readyRevision();
        $this->signTyped($this->issueLink($revision));
        app(ContractSigningService::class)->countersign($revision->organizationRequest()->firstOrFail(), $this->admin, [
            'signer_name' => 'Daniel Schuppelius', 'signer_function' => null, 'signature_method' => 'typed', 'signature' => null,
            'declaration_accepted' => true, 'authority_confirmed' => true,
        ]);

        return $revision->fresh() ?? $revision;
    }

    private function issueLink(ContractSigningRevision $revision): string {
        return app(ContractSigningService::class)->issueSignLink($revision->customerRequest()->firstOrFail(), $this->admin)['token'];
    }

    private function signTyped(string $token): void {
        $this->post(route('agreements.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Erika Müller', 'signature_method' => 'typed', 'typed_name' => 'Erika Müller',
            'declaration_accepted' => '1', 'authority_confirmed' => '1',
        ])->assertRedirect(route('agreements.public-thanks'));
    }

    /** Kleinstes gültiges PNG (1×1, transparent). */
    private function png(): string {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
    }
}
