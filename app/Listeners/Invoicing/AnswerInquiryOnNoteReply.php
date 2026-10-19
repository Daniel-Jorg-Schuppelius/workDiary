<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnswerInquiryOnNoteReply.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Invoicing;

use App\Events\Communication\CommunicationNoteReplied;
use App\Listeners\ModuleListener;
use App\Models\Invoicing\InvoiceInquiry;
use App\Models\Platform\User;
use App\Services\Invoicing\Inquiries\InvoiceInquiryService;

/** Die Antwort auf die Mail einer offenen Rechnungsnachfrage beantwortet die Nachfrage (Feature 166). */
final class AnswerInquiryOnNoteReply extends ModuleListener {
    public function __construct(private readonly InvoiceInquiryService $inquiries) {}

    protected function module(): string {
        return 'invoicing';
    }

    public function handle(CommunicationNoteReplied $event): void {
        if (! $this->shouldHandle($event->organizationId)) {
            return;
        }
        $inquiry = InvoiceInquiry::query()->withoutGlobalScopes()
            ->where('organization_id', $event->organizationId)
            ->where('communication_note_id', $event->noteId)
            ->first();
        $actor = User::query()->withoutGlobalScopes()->find($event->userId);
        if (! $inquiry instanceof InvoiceInquiry || ! $inquiry->isOpen() || ! $actor instanceof User) {
            return;
        }

        $this->inquiries->answer($inquiry, $actor);
    }
}
