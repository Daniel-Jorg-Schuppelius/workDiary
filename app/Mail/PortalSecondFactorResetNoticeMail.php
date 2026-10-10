<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalSecondFactorResetNoticeMail.php
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

/**
 * Hinweis an den Portalkunden, dass der Auftragnehmer seinen zweiten Faktor
 * entfernt hat (MVP-1100) — damit ein unbefugtes Zurücksetzen auffällt.
 */
class PortalSecondFactorResetNoticeMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public User $portalUser) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('customer_portal.second_factor.mail_subject', ['org' => $this->brandName()]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.portal-second-factor-reset-notice', with: [
            'portalUser' => $this->portalUser,
            'brandName' => $this->brandName(),
            'loginUrl' => route('customer.login'),
        ]);
    }

    private function brandName(): string {
        return $this->portalUser->organization->name
            ?? app(BrandingService::class)->appName();
    }
}
