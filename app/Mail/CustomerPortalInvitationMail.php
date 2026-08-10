<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalInvitationMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Einladung zu einem Kundenportal-Zugang (MVP-510). Der Link trägt den
 * einmaligen Klartext-Token zur Passwortvergabe; es werden nie Passwörter
 * oder andere Zugangsdaten versendet. Absender/Anrede nutzen den
 * Organisationsnamen aus dem Branding.
 */
class CustomerPortalInvitationMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public User $portalUser, public string $acceptUrl) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('Ihr Zugang zum Kundenportal von :org', [
            'org' => $this->brandName(),
        ]));
    }

    public function content(): Content {
        return new Content(view: 'mail.customer-portal-invitation', with: [
            'portalUser' => $this->portalUser,
            'acceptUrl' => $this->acceptUrl,
            'brandName' => $this->brandName(),
        ]);
    }

    private function brandName(): string {
        return $this->portalUser->organization->name
            ?? app(BrandingService::class)->appName();
    }
}
