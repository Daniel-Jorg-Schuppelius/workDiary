<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierQuestionnaireMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Platform\Organization;
use App\Models\Supplier\SupplierQuestionnaireRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/** Einladung zur Lieferanten-Selbstauskunft (MVP-937); der Link steht nur in der Mail. */
class SupplierQuestionnaireMail extends Mailable implements ShouldQueue {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $requestId, public readonly string $token) {
        $this->afterCommit();
    }

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('supplier_questionnaire.mail.subject', ['org' => $this->organization()->name]));
    }

    public function content(): Content {
        $request = SupplierQuestionnaireRequest::query()->withoutGlobalScopes()->with('questionnaire')->findOrFail($this->requestId);
        $text = (string) __('supplier_questionnaire.mail.body', [
            'org' => $this->organization()->name,
            'name' => (string) $request->questionnaire?->name,
            'url' => route('supplier-questionnaire.public', $this->token),
            'until' => $request->expires_at->format('d.m.Y'),
        ]);

        return new Content(view: 'mail.document', text: 'mail.document-text', with: ['html' => nl2br(e($text)), 'text' => $text]);
    }

    private function organization(): Organization {
        $request = SupplierQuestionnaireRequest::query()->withoutGlobalScopes()->findOrFail($this->requestId);

        return Organization::query()->withoutGlobalScopes()->findOrFail($request->organization_id);
    }
}
