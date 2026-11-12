<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignatureCaptureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolEventType, ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolStatus, ProtocolType};
use App\Exceptions\ProtocolValidationException;
use App\Mail\ProtocolSignatureRequestMail;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Protocol\{ProtocolPdfRenderer, ProtocolService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Tests\TestCase;

/**
 * MVP-1572: Pflichtunterschrift je Protokolltyp, Unterschriftsbild als Anhang,
 * Papierabnahme, Signaturlink per E-Mail und das PDF des Abschlusses als Anhang.
 */
class ProtocolSignatureCaptureTest extends TestCase {
    use RefreshDatabase;

    private const string PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $admin;

    private DiaryEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->admin->organization_id]);
    }

    public function test_acceptance_is_not_signed_without_the_customer_signature(): void {
        $protocol = $this->protocol(ProtocolType::Acceptance);

        try {
            app(ProtocolService::class)->sign($protocol, $this->admin);
            $this->fail('Abschluss ohne Unterschrift des Kunden.');
        } catch (ProtocolValidationException $e) {
            $this->assertStringContainsString(ProtocolSignatureRole::Customer->label(), $e->errors()[0]);
        }

        // Auch der abgewählte Signaturblock schließt nicht ab: das Feld ist Pflicht.
        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['with_signature' => '0'])
            ->assertSessionHasErrors('signature.signer_name');
        // Die Unterschrift der eigenen Seite genügt einer Abnahme nicht; im Entwurf wird sie auch nicht vorab erfasst.
        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => $this->onscreen('contractor', 'Erika Muster')])
            ->assertSessionHasErrors(['validation' => __('protocol.validation.signature.reviewFirst')]);

        $this->assertSame(ProtocolStatus::Draft, $protocol->fresh()->status);
        $this->assertSame(0, $protocol->signatures()->count());
        $this->assertDatabaseMissing('protocol_events', ['protocol_id' => $protocol->id, 'event' => ProtocolEventType::Signed]);
    }

    public function test_sign_dialog_drops_the_optional_checkbox_for_mandatory_types(): void {
        $this->actingAs($this->admin)->get(route('protocols.transition-form', [$this->protocol(ProtocolType::Handover), 'sign']))
            ->assertOk()
            ->assertSee('<input type="hidden" name="with_signature" value="1">', false)
            ->assertSee('x-data="signaturePad"', false)
            ->assertSee('name="signature[scan]"', false)
            ->assertSee(ProtocolSignatureRole::Contractor->label());

        $this->actingAs($this->admin)->get(route('protocols.transition-form', [$this->protocol(ProtocolType::Service), 'sign']))
            ->assertOk()
            ->assertDontSee('<input type="hidden" name="with_signature" value="1">', false)
            ->assertSee('type="checkbox"', false);
    }

    public function test_handover_needs_both_sides_and_keeps_images_and_pdf_as_attachments(): void {
        $protocol = $this->protocol(ProtocolType::Handover);
        app(ProtocolService::class)->requestReview($protocol, $this->admin);

        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => $this->onscreen('contractor', 'Erika Muster')])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', __('protocol.flash.signature_captured', ['roles' => ProtocolSignatureRole::Customer->label()]));
        $this->assertSame(ProtocolStatus::InReview, $protocol->fresh()->status);

        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => $this->onscreen('customer', 'Max Beispiel')])
            ->assertSessionHasNoErrors();

        $protocol->refresh();
        $this->assertSame(ProtocolStatus::Signed, $protocol->status);
        $this->assertSame(2, $protocol->signatures()->count());

        foreach ($protocol->signatures()->with('signatureAttachment')->get() as $signature) {
            $image = $signature->signatureAttachment;
            $this->assertNotNull($image);
            $this->assertSame(Protocol::META_SIGNATURE, $image->meta_type);
            $this->assertSame('image/png', $image->mime);
            $this->assertSame($image->path, $signature->signature_image_path);
            Storage::disk('local')->assertExists($image->path);
            // Unterschriftsbilder entfallen nie einzeln, auch nicht über den Admin-Durchgriff.
            $this->actingAs($this->admin)->delete(route('attachments.destroy', $image))->assertForbidden();
        }

        $pdf = $protocol->attachments()->where('meta_type', Protocol::META_PDF)->firstOrFail();
        $this->assertStringContainsString('_s', $pdf->path);
        Storage::disk(ProtocolPdfRenderer::DISK)->assertExists($pdf->path);
        $this->assertSame(1, $protocol->attachments()->where('meta_type', Protocol::META_PDF)->count());
        $this->assertSame(2, $protocol->journal()->where('event', ProtocolEventType::SignatureAdded)->count());

        $this->actingAs($this->admin)->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee('Max Beispiel')
            ->assertSee(ProtocolSignatureMethod::Onscreen->label());
    }

    public function test_pdf_written_before_the_signature_is_not_reused(): void {
        $protocol = $this->protocol(ProtocolType::Service);
        $renderer = app(ProtocolPdfRenderer::class);
        $before = $renderer->render($protocol->refresh());

        app(ProtocolService::class)->sign($protocol, $this->admin, ['role' => 'contractor', 'signer_name' => 'Erika Muster', 'method' => 'onscreen', 'signature_image' => self::PNG]);

        $after = $renderer->render($protocol->refresh()->unsetRelations());
        $this->assertNotSame($before, $after);
        $this->assertSame($after, $renderer->render($protocol->refresh()->unsetRelations()));
    }

    public function test_onscreen_needs_a_drawn_image_and_a_client_path_is_never_stored(): void {
        $protocol = $this->protocol(ProtocolType::Acceptance);

        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => ['role' => 'customer', 'signer_name' => 'Max Beispiel', 'method' => 'onscreen', 'signature_image_path' => '../../.env']])
            ->assertSessionHasErrors('signature.image');
        // Portal und Link belegen sich über ihren eigenen Weg, nicht über den Dialog im Haus.
        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => ['role' => 'customer', 'signer_name' => 'Max Beispiel', 'method' => 'portal', 'image' => self::PNG]])
            ->assertSessionHasErrors('signature.method');

        $signature = app(ProtocolService::class)->addSignature($protocol, $this->admin, ['role' => 'witness', 'signer_name' => 'Zeugin', 'signature_image_path' => '../../.env']);
        $this->assertNull($signature->signature_image_path);
        $this->assertNull($signature->signature_attachment_id);
    }

    public function test_paper_acceptance_stores_the_scan_and_needs_the_original_confirmation(): void {
        $protocol = $this->protocol(ProtocolType::Acceptance);
        $paper = ['role' => 'customer', 'signer_name' => 'Max Beispiel', 'method' => 'paper'];

        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => $paper])
            ->assertSessionHasErrors(['signature.scan', 'signature.original_confirmed']);
        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => $paper + ['scan' => UploadedFile::fake()->image('abnahme.jpg', 400, 300), 'original_confirmed' => '0']])
            ->assertSessionHasErrors('signature.original_confirmed');

        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.transition', [$protocol, 'sign']), ['signature' => $paper + ['scan' => UploadedFile::fake()->image('abnahme.jpg', 400, 300), 'original_confirmed' => '1']])
            ->assertSessionHasNoErrors();

        $protocol->refresh();
        $this->assertSame(ProtocolStatus::Signed, $protocol->status);
        $signature = $protocol->signatures()->with('signatureAttachment')->firstOrFail();
        $this->assertSame(ProtocolSignatureMethod::Paper, $signature->method);
        $this->assertSame('image/jpeg', $signature->signatureAttachment?->mime);
        $this->assertSame($signature->signatureAttachment?->path, $signature->signature_image_path);
        Storage::disk('local')->assertExists((string) $signature->signature_image_path);
        $event = $protocol->journal()->where('event', ProtocolEventType::SignatureAdded)->firstOrFail();
        $this->assertTrue($event->payload['original_on_file']);
    }

    public function test_signature_link_is_mailed_unless_switched_off(): void {
        Mail::fake();
        $protocol = $this->protocol(ProtocolType::Acceptance);

        // Im Entwurf gibt es keinen Link: der Inhalt könnte sich unter der Unterschrift ändern.
        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.signature-tokens.store', $protocol), ['role' => 'customer', 'signer_email' => 'max@example.org'])
            ->assertSessionHasErrors('status');
        Mail::assertNothingQueued();
        app(ProtocolService::class)->requestReview($protocol, $this->admin);

        $this->actingAs($this->admin)->get(route('protocols.signature-tokens.create', $protocol))->assertOk()->assertSee('name="send_email"', false);
        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.signature-tokens.store', $protocol), ['role' => 'customer', 'signer_name' => 'Max Beispiel', 'signer_email' => 'max@example.org', 'send_email' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('protocol.signature.token_url');

        Mail::assertQueued(ProtocolSignatureRequestMail::class, static fn (ProtocolSignatureRequestMail $mail): bool => $mail->hasTo('max@example.org')
            && str_contains($mail->signUrl, '/sign/protocol/')
            && str_contains($mail->render(), $mail->signUrl));
        $this->assertSame(1, $protocol->journal()->where('event', ProtocolEventType::SignatureLinkEmailed)->count());

        $this->actingAs($this->admin)->from(route('protocols.show', $protocol))
            ->post(route('protocols.signature-tokens.store', $protocol), ['role' => 'customer', 'signer_email' => 'max@example.org', 'send_email' => '0'])
            ->assertSessionHasNoErrors();
        Mail::assertQueuedCount(1);
    }

    public function test_protocols_signed_before_the_rule_stay_as_they_are(): void {
        $legacy = Protocol::factory()->for($this->entry, 'subject')->signed()->create([
            'organization_id' => $this->admin->organization_id,
            'created_by_user_id' => $this->admin->id,
            'type' => ProtocolType::Acceptance->value,
        ]);

        $this->actingAs($this->admin)->get(route('protocols.show', $legacy))->assertOk()
            ->assertDontSee(ProtocolService::missingSignaturesMessage([ProtocolSignatureRole::Customer]));
        app(ProtocolService::class)->archive($legacy, $this->admin);
        $this->assertSame(ProtocolStatus::Archived, $legacy->fresh()->status);
    }

    private function protocol(ProtocolType $type): Protocol {
        return app(ProtocolService::class)->create($this->entry, $this->admin, ['title' => 'Protokoll ' . $type->value, 'type' => $type->value]);
    }

    /** @return array<string, string> */
    private function onscreen(string $role, string $name): array {
        return ['role' => $role, 'signer_name' => $name, 'method' => 'onscreen', 'image' => self::PNG];
    }
}
