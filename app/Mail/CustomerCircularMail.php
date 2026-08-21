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
use App\Models\Customer;
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

    public function content(): Content {
        return new Content(view: 'mail.customer-circular', with: [
            'circular' => $this->circular,
            'customer' => $this->customer,
            'body' => $this->body,
        ]);
    }
}
