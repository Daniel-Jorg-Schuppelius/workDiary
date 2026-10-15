<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryMailIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mail;

use App\Enums\Invoicing\InvoiceInquiryMatchKind;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Services\Invoicing\Inquiries\{InvoiceInquiryMatcher, InvoiceInquiryService, InvoiceInquiryTarget};
use App\Services\Mail\Contracts\{MailIntakeAnnotator, MailIntakeHandler};
use App\Services\Mail\ParsedMessage;
use App\Services\Platform\IntakeActor;

/**
 * Rechnungsnachfrage aus dem Mail-Eingang (Feature 166, MVP-1200): Antwort
 * auf den eigenen Versand oder genau eine bekannte Rechnungs-/Belegnummer
 * von einer Adresse des Vorgangs wird automatisch erfasst. Alles andere
 * bleibt ein Inbox-Klärfall, den der Annotator mit den Kandidaten markiert.
 */
final class InvoiceInquiryMailIntakeHandler implements MailIntakeAnnotator, MailIntakeHandler {
    public const RESULT = 'invoice_inquiry';

    public const REVIEW_MULTIPLE = 'multiple';

    public const REVIEW_SENDER_UNKNOWN = 'sender_unknown';

    public const REVIEW_NO_CUSTOMER = 'no_customer';

    public function __construct(
        private readonly InvoiceInquiryMatcher $matcher,
        private readonly InvoiceInquiryService $inquiries,
    ) {}

    /** Nach dem Ticket-Faden (40): eine Antwort in einen Ticketverlauf bleibt dort. */
    public function priority(): int {
        return 50;
    }

    public function handle(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string {
        if ($message->isAutoSubmitted) {
            return null; // Abwesenheitsnotizen sind keine Nachfragen
        }
        if ($this->inquiries->exists((int) $organization->id, $message->messageId)) {
            return 'skipped';
        }

        $resolution = $this->resolve($organization, $message);
        if ($resolution['target'] === null) {
            return null;
        }

        $actor = IntakeActor::resolve((int) $organization->id, $connection->created_by);
        $inquiry = $this->inquiries->record($organization, $resolution['target'], $message, $resolution['kind'], $actor);

        return $inquiry === null ? 'skipped' : self::RESULT;
    }

    public function annotate(Organization $organization, EmailConnection $connection, ParsedMessage $message): array {
        if ($message->isAutoSubmitted) {
            return [];
        }
        $resolution = $this->resolve($organization, $message);
        if ($resolution['candidates'] === []) {
            return [];
        }

        return [
            'invoice_candidates' => array_map(static fn (InvoiceInquiryTarget $target): array => $target->toArray(), $resolution['candidates']),
            'invoice_review' => $resolution['review'],
        ];
    }

    /**
     * @return array{target: ?InvoiceInquiryTarget, kind: InvoiceInquiryMatchKind, candidates: list<InvoiceInquiryTarget>, review: ?string}
     */
    private function resolve(Organization $organization, ParsedMessage $message): array {
        $threaded = $this->matcher->threaded($organization, $message);
        if ($threaded !== null) {
            // Die Message-ID des Versands kennt nur der Empfänger — der Faden ist der Nachweis.
            return ['target' => $threaded, 'kind' => InvoiceInquiryMatchKind::Thread, 'candidates' => [$threaded], 'review' => null];
        }

        $candidates = $this->matcher->fromText($organization, $message->subject . "\n" . $message->body);
        $kind = InvoiceInquiryMatchKind::Number;
        if ($candidates === []) {
            return ['target' => null, 'kind' => $kind, 'candidates' => [], 'review' => null];
        }
        if (count($candidates) > 1) {
            return ['target' => null, 'kind' => $kind, 'candidates' => $candidates, 'review' => self::REVIEW_MULTIPLE];
        }

        $candidate = $candidates[0];
        if ($candidate->customerId === null) {
            return ['target' => null, 'kind' => $kind, 'candidates' => $candidates, 'review' => self::REVIEW_NO_CUSTOMER];
        }
        if (! $this->matcher->senderBelongs($candidate, $message->fromEmail)) {
            return ['target' => null, 'kind' => $kind, 'candidates' => $candidates, 'review' => self::REVIEW_SENDER_UNKNOWN];
        }

        return ['target' => $candidate, 'kind' => $kind, 'candidates' => $candidates, 'review' => null];
    }
}
