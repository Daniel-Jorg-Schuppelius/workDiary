<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalEmailChangeConfirmMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Bestätigungslink für die E-Mail-Änderung eines Portalkontos (MVP-712) —
 * geht an die NEUE Adresse; erst der Klick wechselt die Adresse.
 */
class PortalEmailChangeConfirmMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public User $portalUser, public string $newEmail, public string $confirmUrl) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('Neue E-Mail-Adresse für das Kundenportal von :org bestätigen', [
            'org' => $this->brandName(),
        ]));
    }

    public function content(): Content {
        return new Content(view: 'mail.portal-email-change-confirm', with: [
            'portalUser' => $this->portalUser,
            'newEmail' => $this->newEmail,
            'confirmUrl' => $this->confirmUrl,
            'brandName' => $this->brandName(),
            'ttlHours' => \App\Services\CustomerPortal\PortalEmailChangeService::TTL_HOURS,
        ]);
    }

    private function brandName(): string {
        return $this->portalUser->organization->name
            ?? app(BrandingService::class)->appName();
    }
}
