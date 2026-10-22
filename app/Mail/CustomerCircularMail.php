<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\Communication\CustomerCircular;
use App\Models\Customer\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Geschäftsmitteilung an einen Bestandskunden (Feature 119, MVP-608).
 *
 * Enthält bewusst **kein** Tracking: keine Zählpixel, keine umgeschriebenen
 * Links, keine Abmelde-Automatik. Der Text ist der Text.
 */
class CustomerCircularMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerCircular $circular,
        public Customer $customer,
        public string $body,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) $this->circular->subject);
    }

    /**
     * Anhänge des Rundschreibens (MVP-1285).
     *
     * @return list<\Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array {
        return array_values($this->circular->attachments
            ->map(static fn (\App\Models\Attachments\Attachment $file): \Illuminate\Mail\Mailables\Attachment => \Illuminate\Mail\Mailables\Attachment::fromStorageDisk((string) $file->disk, (string) $file->path)
                ->as((string) $file->original_name)
                ->withMime((string) $file->mime))
            ->all());
    }

    public function content(): Content {
        return new Content(markdown: 'mail.customer-circular', with: [
            'circular' => $this->circular,
            'customer' => $this->customer,
            'body' => $this->body,
        ]);
    }
}
