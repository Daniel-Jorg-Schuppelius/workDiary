<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentDispatchStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Listeners\RecordInvoiceMailDelivery;
use App\Models\Customer\Customer;
use App\Models\Document\DocumentDispatch;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Symfony\Component\Mailer\{Envelope, SentMessage as SymfonySentMessage};
use Symfony\Component\Mime\{Address, Email};
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): der Zustellversuch führt
 * seinen technischen Stand als Enum. Die Versandhistorie verkettete ihn
 * bisher in den Übersetzungsschlüssel und wählte den Ton per Zeichenkette.
 */
final class DocumentDispatchStatusEnumCastTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    private Quote $quote;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->quote = Quote::factory()->create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
        ]);
    }

    private function dispatch(DocumentDispatchStatus $status): DocumentDispatch {
        return DocumentDispatch::query()->create([
            'organization_id' => $this->org->id,
            'document_kind' => RenderDocumentKind::Quote->value,
            'document_id' => $this->quote->id,
            'channel' => DocumentDispatch::CHANNEL_EMAIL,
            'status' => $status,
            'recipient' => $status->value . '@kunde.example',
        ]);
    }

    public function test_dispatch_history_shows_label_and_tone_per_status(): void {
        foreach (DocumentDispatchStatus::cases() as $status) {
            $this->dispatch($status);
        }

        $html = (string) $this->actingAs($this->user)->get(route('quotes.show', $this->quote))->assertOk()->getContent();

        foreach ([['success', DocumentDispatchStatus::Sent], ['error', DocumentDispatchStatus::Failed], ['ghost', DocumentDispatchStatus::Queued]] as [$tone, $status]) {
            $this->assertSame(1, preg_match('/badge-' . $tone . '"[^>]*>\s*' . preg_quote(e($status->label()), '/') . '\s*</u', $html), "Kein Abzeichen „{$status->value}“ im Ton {$tone}.");
        }
    }

    public function test_sent_mail_and_failed_job_move_a_queued_dispatch(): void {
        $sent = $this->dispatch(DocumentDispatchStatus::Queued);
        $email = (new Email)->from('absender@example.test')->to('kunde@example.test')->subject('Angebot')->text('Text');
        $email->getHeaders()->addTextHeader(RecordInvoiceMailDelivery::HEADER, (string) $sent->id);
        (new RecordInvoiceMailDelivery)->handle(new MessageSent(new SentMessage(new SymfonySentMessage($email, new Envelope(new Address('absender@example.test'), [new Address('kunde@example.test')])))));

        $this->assertSame(DocumentDispatchStatus::Sent, $sent->fresh()->status);
        $this->assertDatabaseHas('document_dispatches', ['id' => $sent->id, 'status' => 'sent']);
        $this->assertDatabaseHas('document_dispatches', ['id' => $this->dispatch(DocumentDispatchStatus::Failed)->id, 'status' => 'failed']);
    }
}
