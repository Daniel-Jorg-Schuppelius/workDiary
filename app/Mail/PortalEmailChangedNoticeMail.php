<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalEmailChangedNoticeMail.php
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
 * Info an die ALTE Adresse nach vollzogener E-Mail-Änderung eines
 * Portalkontos (MVP-712) — damit ein unbefugter Wechsel auffällt.
 */
class PortalEmailChangedNoticeMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public User $portalUser, public string $oldEmail, public string $newEmail) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('Ihre E-Mail-Adresse im Kundenportal von :org wurde geändert', [
            'org' => $this->brandName(),
        ]));
    }

    public function content(): Content {
        return new Content(view: 'mail.portal-email-changed-notice', with: [
            'portalUser' => $this->portalUser,
            'oldEmail' => $this->oldEmail,
            'newEmail' => $this->newEmail,
            'brandName' => $this->brandName(),
        ]);
    }

    private function brandName(): string {
        return $this->portalUser->organization->name
            ?? app(BrandingService::class)->appName();
    }
}
