<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignatureIntegrityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Protocol\{ProtocolEventType, ProtocolSignatureRole, ProtocolStatus, ProtocolType};
use App\Exceptions\ProtocolValidationException;
use App\Models\Attachments\Attachment;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolSignature};
use App\Services\Notification\NotificationDispatcher;
use App\Services\Protocol\{ProtocolHasher, ProtocolPdfRenderer, ProtocolService, ProtocolSignatureTokenService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * MVP-1572 (Nachzug): Eine Unterschrift gilt für einen Inhaltsstand. Erfasst
 * wird sie nur am eingefrorenen Inhalt („in Prüfung“); geht das Protokoll in
 * den Entwurf zurück, verfallen Unterschriften und Links; ein Abschluss trägt
 * nur Unterschriften, deren Hash zum Inhalt passt.
 */
class ProtocolSignatureIntegrityTest extends TestCase {
    use RefreshDatabase;

    private const string PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $admin;

    private DiaryEntry $entry;

    private ProtocolService $service;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->admin->organization_id]);
        $this->service = app(ProtocolService::class);
    }

    public function test_signatures_are_captured_only_on_frozen_content_and_fall_with_the_return_to_draft(): void {
        $protocol = $this->protocol(ProtocolType::Handover);

        try {
            $this->service->captureSignature($protocol, $this->admin, $this->signature('contractor', 'Erika Muster'));
            $this->fail('Unterschrift im Entwurf erfasst.');
        } catch (ProtocolValidationException $e) {
            $this->assertSame(__('protocol.validation.signature.reviewFirst'), $e->errors()[0]);
        }

        $this->service->requestReview($protocol, $this->admin);
        $signature = $this->service->captureSignature($protocol, $this->admin, $this->signature('contractor', 'Erika Muster'));
        $image = (string) $signature->signature_image_path;
        Storage::disk('local')->assertExists($image);
        $token = app(ProtocolSignatureTokenService::class)->issue($protocol, $this->admin, ['role' => 'customer', 'signer_email' => 'max@example.org']);

        $this->service->returnToDraft($protocol, $this->admin, 'Messwert fehlt');

        $this->assertSame(0, $protocol->signatures()->count());
        Storage::disk('local')->assertMissing($image);
        $this->assertSame([ProtocolSignatureRole::Customer, ProtocolSignatureRole::Contractor], $protocol->missingSignatureRoles());
        $voided = $protocol->journal()->where('event', ProtocolEventType::SignatureVoided)->firstOrFail();
        $this->assertSame('Erika Muster', $voided->payload['signer_name']);
        $this->assertSame('returned_to_draft', $voided->payload['reason']);

        // Der Link von vorher ist verfallen und wird auf der öffentlichen Seite klar abgewiesen.
        $this->assertFalse($token['model']->fresh()->isUsable());
        $this->get(route('protocols.public-sign', ['token' => $token['token']]))->assertStatus(410);
        $this->post(route('protocols.public-sign.submit', ['token' => $token['token']]), ['signer_name' => 'Max Beispiel', 'signature' => self::PNG, 'accept' => '1'])->assertStatus(410);
        $this->assertSame(0, $protocol->signatures()->count());
    }

    public function test_a_finished_protocol_carries_only_signatures_whose_hash_matches_the_content(): void {
        $protocol = $this->protocol(ProtocolType::Acceptance);
        $item = $this->service->addItem($protocol, $this->admin, ['label' => 'Dichtheit', 'item_type' => 'text']);

        // Altbestand: Unterschrift im Entwurf, danach geänderter Inhalt.
        $legacy = $this->service->addSignature($protocol, $this->admin, $this->signature('customer', 'Max Beispiel'));
        $this->assertSame([], $this->service->staleSignatures($protocol));
        $this->service->fillItem($item, $this->admin, ['value_json' => ['value' => 'nachträglich geändert']]);
        $this->assertSame([$legacy->id], array_map(static fn (ProtocolSignature $s): int => $s->id, $this->service->staleSignatures($protocol)));

        try {
            $this->service->sign($protocol->refresh(), $this->admin);
            $this->fail('Abschluss mit Unterschrift auf altem Inhalt.');
        } catch (ProtocolValidationException $e) {
            $this->assertStringContainsString('Max Beispiel', $e->errors()[0]);
        }
        $this->assertSame(ProtocolStatus::Draft, $protocol->fresh()->status);

        // Mit dem Einreichen wird sie verworfen; die Pflichtrolle ist wieder offen.
        $this->service->requestReview($protocol->refresh(), $this->admin);
        $this->assertSame(0, $protocol->signatures()->count());
        $this->assertSame('content_changed', $protocol->journal()->where('event', ProtocolEventType::SignatureVoided)->firstOrFail()->payload['reason']);
        $this->assertSame([ProtocolSignatureRole::Customer], $protocol->missingSignatureRoles());

        $this->service->captureSignature($protocol, $this->admin, $this->signature('customer', 'Max Beispiel'));
        $signed = $this->service->sign($protocol->refresh(), $this->admin, $this->signature('contractor', 'Erika Muster'));

        $this->assertSame(ProtocolStatus::Signed, $signed->status);
        $this->assertSame([], $this->service->staleSignatures($signed));
        $hasher = app(ProtocolHasher::class);
        $fresh = Protocol::query()->findOrFail($signed->id);
        $this->assertCount(2, $fresh->signatures);
        foreach ($fresh->signatures as $signature) {
            $this->assertSame($signature->hash, $hasher->contentHash($fresh, $signature->signer_name, $signature->role->value, $signature->signed_at->toIso8601String()));
        }
    }

    public function test_link_issued_before_completion_cannot_be_redeemed_afterwards(): void {
        $protocol = $this->protocol(ProtocolType::Service);
        $this->service->requestReview($protocol, $this->admin);
        $tokens = app(ProtocolSignatureTokenService::class);
        $token = $tokens->issue($protocol, $this->admin, ['role' => 'customer', 'signer_email' => 'max@example.org']);

        $this->service->sign($protocol->refresh(), $this->admin);

        $this->assertFalse($token['model']->fresh()->isUsable());
        $this->assertSame('signed', $protocol->journal()->where('event', ProtocolEventType::SignatureLinkRevoked)->firstOrFail()->payload['reason']);
        $this->post(route('protocols.public-sign.submit', ['token' => $token['token']]), ['signer_name' => 'Max Beispiel', 'signature' => self::PNG, 'accept' => '1'])->assertStatus(410);
        $this->assertSame(0, $protocol->signatures()->count());

        // Auch ein noch laufender Link scheitert am Status — mit eigener Meldung.
        $token['model']->forceFill(['expires_at' => now()->addDay()])->save();
        $this->get(route('protocols.public-sign', ['token' => $token['token']]))->assertStatus(410)->assertSee(__('protocol.signature.notAwaiting'));
        $this->post(route('protocols.public-sign.submit', ['token' => $token['token']]), ['signer_name' => 'Max Beispiel', 'signature' => self::PNG, 'accept' => '1'])
            ->assertStatus(410)->assertSee(__('protocol.signature.notAwaiting'));
        $this->assertSame(0, $protocol->signatures()->count());

        $this->service->supersede($protocol->refresh(), $this->admin, 'Korrektur');
        $this->assertFalse($token['model']->fresh()->isUsable());
    }

    public function test_house_is_told_when_the_customer_signs_by_link(): void {
        $requester = User::factory()->admin()->create(['organization_id' => $this->admin->organization_id]);
        $handover = $this->protocol(ProtocolType::Handover);
        $acceptance = $this->protocol(ProtocolType::Acceptance);
        $tokens = app(ProtocolSignatureTokenService::class);
        $sent = [];

        foreach ([$handover, $acceptance] as $protocol) {
            $this->service->requestReview($protocol, $this->admin);
            $token = $tokens->issue($protocol, $requester, ['role' => 'customer', 'signer_email' => 'max@example.org']);
            $spy = $this->spy(NotificationDispatcher::class);

            $this->post(route('protocols.public-sign.submit', ['token' => $token['token']]), ['signer_name' => 'Max Beispiel', 'signature' => self::PNG, 'accept' => '1'])
                ->assertRedirect();
            // Nach der Entscheidung zeigt der Link die Bestätigung, nicht „abgelaufen“.
            $this->get(route('protocols.public-sign', ['token' => $token['token']]))->assertOk()->assertSee(__('protocol.signature.alreadyDecided'));

            $spy->shouldHaveReceived('notify')->withArgs(function (NotificationEvent $event, $subject, ?User $affected, array $payload) use ($protocol, $requester, &$sent): bool {
                if ($event !== NotificationEvent::ProtocolCustomerSigned) {
                    return false;
                }
                $sent[$protocol->type->value] = $payload['message_key'];

                return $subject->is($protocol) && $affected?->is($requester) && $payload['url'] === route('protocols.show', $protocol);
            })->once();
        }

        // Übergabe: die eigene Seite fehlt noch. Abnahme: kann abgeschlossen werden.
        $this->assertSame([
            'handover' => 'notification.message.protocol_customer_signed_pending',
            'acceptance' => 'notification.message.protocol_customer_signed_ready',
        ], $sent);
    }

    public function test_no_image_file_stays_behind_when_the_signature_row_fails(): void {
        $protocol = $this->protocol(ProtocolType::Service);
        ProtocolSignature::creating(static function (): never {
            throw new RuntimeException('Zeile scheitert.');
        });

        try {
            $this->service->sign($protocol, $this->admin, $this->signature('contractor', 'Erika Muster'));
            $this->fail('Unterschrift trotz Fehler gespeichert.');
        } catch (RuntimeException $e) {
            $this->assertSame('Zeile scheitert.', $e->getMessage());
        } finally {
            ProtocolSignature::flushEventListeners();
        }

        $this->assertSame([], Storage::disk('local')->allFiles('protocol-signatures'));
        $this->assertSame(0, Attachment::query()->where('meta_type', Protocol::META_SIGNATURE)->count());
        $this->assertSame(ProtocolStatus::Draft, $protocol->fresh()->status);
    }

    public function test_draft_pdfs_are_not_kept_and_the_signed_one_is_an_attachment(): void {
        $protocol = $this->protocol(ProtocolType::Service);
        $disk = Storage::disk(ProtocolPdfRenderer::DISK);

        $this->actingAs($this->admin)->get(route('protocols.pdf', $protocol))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame([], $disk->allFiles('protocols'));
        $this->assertSame(1, $protocol->journal()->where('event', ProtocolEventType::PdfDownloaded)->count());

        // Fassung von früher, als auch Entwürfe abgelegt wurden: der Abschluss räumt sie weg.
        $old = app(ProtocolPdfRenderer::class)->render($protocol->refresh());
        $disk->assertExists($old);
        $this->service->sign($protocol->refresh(), $this->admin, $this->signature('contractor', 'Erika Muster'));

        $disk->assertMissing($old);
        $pdf = $protocol->attachments()->where('meta_type', Protocol::META_PDF)->firstOrFail();
        $this->assertSame([$pdf->path], $disk->allFiles('protocols'));

        // Nachweis ohne Anhang (Altbestand): der nächste Abruf meldet die Datei als Anhang an.
        $pdf->newQuery()->whereKey($pdf->id)->delete();
        $this->actingAs($this->admin)->get(route('protocols.pdf', $protocol))->assertOk();
        $this->assertSame($pdf->path, $protocol->attachments()->where('meta_type', Protocol::META_PDF)->firstOrFail()->path);
    }

    public function test_signing_rereads_the_status_under_a_row_lock(): void {
        $protocol = $this->protocol(ProtocolType::Handover);
        $this->service->requestReview($protocol, $this->admin);
        $stale = Protocol::query()->findOrFail($protocol->id);

        // Zwischen Prüfung und Schreiben gibt jemand das Protokoll in den Entwurf zurück.
        $this->service->returnToDraft($protocol->refresh(), $this->admin, 'Messwert fehlt');

        try {
            $this->service->captureSignature($stale, $this->admin, $this->signature('contractor', 'Erika Muster'));
            $this->fail('Unterschrift an einem überholten Status erfasst.');
        } catch (\App\Exceptions\InvalidProtocolTransitionException) {
        }
        $this->assertSame(0, ProtocolSignature::query()->count());
        $this->assertSame(0, Attachment::query()->where('meta_type', Protocol::META_SIGNATURE)->count(), 'Das Bild rollt mit der Zeile zurück.');

        // Ohne Pflichtunterschrift: der Abschluss scheitert erst an der Sperre.
        $service = $this->protocol(ProtocolType::Service);
        $stale = Protocol::query()->findOrFail($service->id);
        Protocol::query()->whereKey($service->id)->update(['status' => ProtocolStatus::Archived->value]);
        try {
            $this->service->sign($stale, $this->admin);
            $this->fail('Abschluss eines schon archivierten Protokolls.');
        } catch (\App\Exceptions\InvalidProtocolTransitionException) {
        }
        $this->assertSame(ProtocolStatus::Archived, $service->fresh()->status);
    }

    private function protocol(ProtocolType $type): Protocol {
        return $this->service->create($this->entry, $this->admin, ['title' => 'Protokoll ' . $type->value, 'type' => $type->value]);
    }

    /** @return array<string, string> */
    private function signature(string $role, string $name): array {
        return ['role' => $role, 'signer_name' => $name, 'method' => 'onscreen', 'signature_image' => self::PNG];
    }
}
