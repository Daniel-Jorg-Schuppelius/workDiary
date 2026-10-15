<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Inquiries;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Invoicing\{InvoiceInquiryMatchKind, InvoiceInquiryStatus};
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Invoicing\InvoiceInquiry;
use App\Models\Platform\{Organization, User};
use App\Services\Communication\CommunicationNoteService;
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Mail\ParsedMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Einzige Schreibstelle für Rechnungsnachfragen (Feature 166, MVP-1200):
 * legt den Datensatz an, hängt die Korrespondenz als Kommunikationsnotiz an
 * den Kunden und schreibt den Eintrag in die Rechnungshistorie. Eine
 * Nachricht ist je Organisation genau einmal erfasst.
 */
final class InvoiceInquiryService {
    use AssertsStatusTransition;

    public function __construct(
        private readonly CommunicationNoteService $notes,
    ) {}

    public function exists(int $organizationId, string $messageId): bool {
        return InvoiceInquiry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('message_id', $messageId)
            ->exists();
    }

    /**
     * Legt die Nachfrage an; null, wenn die Nachricht schon erfasst ist.
     * Ohne übergebene Notiz entsteht eine E-Mail-Notiz beim Kunden, sofern
     * Kunde und Akteur bekannt sind (der automatische Eingang hat keine
     * angemeldete Person).
     */
    public function record(
        Organization $organization,
        InvoiceInquiryTarget $target,
        ParsedMessage $message,
        InvoiceInquiryMatchKind $kind,
        ?User $actor,
        ?CommunicationNote $note = null,
    ): ?InvoiceInquiry {
        if ($this->exists((int) $organization->id, $message->messageId)) {
            return null;
        }

        $customer = $target->customerId !== null
            ? Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereKey($target->customerId)->first()
            : null;

        if ($note === null && $customer !== null && $actor !== null) {
            $note = $this->notes->create($customer, $actor, [
                'type' => CommunicationNoteType::Email->value,
                'direction' => CommunicationDirection::Inbound->value,
                'subject' => $message->subject !== '' ? Str::limit($message->subject, 250, '') : (string) __('invoicing.inquiries.default_subject', ['number' => $target->number]),
                'body' => $message->body,
                'occurred_at' => $message->receivedAt->toIso8601String(),
            ]);
        }

        try {
            $inquiry = InvoiceInquiry::query()->create([
                'organization_id' => $organization->id,
                'invoice_id' => $target->invoice?->getKey(),
                'external_source' => $target->source(),
                'external_key' => $target->key(),
                'invoice_number' => Str::limit($target->number, 64, ''),
                'customer_id' => $customer?->getKey(),
                'message_id' => Str::limit($message->messageId, 255, ''),
                'in_reply_to' => $message->inReplyTo !== null ? Str::limit($message->inReplyTo, 255, '') : null,
                'from_email' => Str::limit($message->fromEmail, 255, ''),
                'from_name' => $message->fromName !== '' ? Str::limit($message->fromName, 160, '') : null,
                'subject' => $message->subject !== '' ? Str::limit($message->subject, 255, '') : null,
                'received_at' => $message->receivedAt,
                'match_kind' => $kind,
                'communication_note_id' => $note?->getKey(),
                'status' => InvoiceInquiryStatus::Open,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // paralleler Abruf derselben Nachricht
        }

        // Rechnungshistorie: der Audit-Eintrag an der Rechnung ist der Eintrag „Nachfrage zur Rechnung“.
        $target->invoice?->audit('invoice.inquiryRecorded', [
            'inquiry_id' => $inquiry->getKey(),
            'from' => $message->fromEmail,
            'subject' => $inquiry->subject,
            'received_at' => $message->receivedAt->toIso8601String(),
            'match_kind' => $kind->value,
        ]);

        return $inquiry;
    }

    public function answer(InvoiceInquiry $inquiry, User $actor): void {
        $this->assertStatusTransition($inquiry->status, InvoiceInquiryStatus::Answered);
        $inquiry->forceFill([
            'status' => InvoiceInquiryStatus::Answered,
            'answered_by' => $actor->getKey(),
            'answered_at' => now(),
        ])->save();
        $inquiry->invoice?->audit('invoice.inquiryAnswered', ['inquiry_id' => $inquiry->getKey(), 'from' => $inquiry->from_email]);
    }

    public function reopen(InvoiceInquiry $inquiry, User $actor): void {
        $this->assertStatusTransition($inquiry->status, InvoiceInquiryStatus::Open);
        $inquiry->forceFill([
            'status' => InvoiceInquiryStatus::Open,
            'answered_by' => null,
            'answered_at' => null,
        ])->save();
        $inquiry->invoice?->audit('invoice.inquiryReopened', ['inquiry_id' => $inquiry->getKey(), 'by' => (int) $actor->getKey()]);
    }
}
