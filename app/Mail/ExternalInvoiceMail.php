<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\TracksDocumentDispatch;
use App\Models\Customer\Customer;
use App\Models\Finance\ExternalInvoicePackage;
use App\Services\Finance\ExternalInvoices\ExternalInvoicePackager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Zustellung einer extern ausgestellten Rechnung mit Nachweisen (Feature 196):
 * die Anhänge sind genau die Dateien der Versandfassung, eine Wiederholung
 * sendet dieselben Bytes. Betreff und Text kommen gerendert aus der Vorlage.
 */
class ExternalInvoiceMail extends Mailable implements ShouldQueue {
    use Queueable, SerializesModels;
    use TracksDocumentDispatch;

    public function __construct(
        public ExternalInvoicePackage $package,
        public string $renderedSubject,
        public string $renderedHtml,
        public string $renderedText,
        public ?int $dispatchId = null,
        ?Customer $localeCustomer = null,
    ) {
        $this->locale(\App\Support\DocumentLocale::for($localeCustomer));
    }

    public function envelope(): Envelope {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content {
        return new Content(
            view: 'mail.document',
            text: 'mail.document-text',
            with: [
                'html' => $this->renderedHtml,
                'text' => $this->renderedText,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array {
        $attachments = [];
        foreach (app(ExternalInvoicePackager::class)->files($this->package) as $file) {
            $content = $file['content'];
            $attachments[] = Attachment::fromData(static fn (): string => $content, $file['name'])->withMime($file['mime']);
        }

        return $attachments;
    }
}
