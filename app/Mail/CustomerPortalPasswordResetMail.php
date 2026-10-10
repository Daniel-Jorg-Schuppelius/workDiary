<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalPasswordResetMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Platform\User;
use App\Services\UI\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/** „Passwort vergessen“ im Kundenportal (MVP-1096): befristeter, einmaliger Link. */
class CustomerPortalPasswordResetMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public User $portalUser, public string $resetUrl, public int $validMinutes) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('customer_portal.password.mail_subject', [
            'org' => $this->brandName(),
        ]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.customer-portal-password-reset', with: [
            'portalUser' => $this->portalUser,
            'resetUrl' => $this->resetUrl,
            'validMinutes' => $this->validMinutes,
            'brandName' => $this->brandName(),
        ]);
    }

    private function brandName(): string {
        return $this->portalUser->organization->name
            ?? app(BrandingService::class)->appName();
    }
}
