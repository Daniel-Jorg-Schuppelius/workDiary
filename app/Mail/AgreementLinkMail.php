<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgreementLinkMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Contract\SignatureLinkPurpose;
use App\Models\Contract\ContractSigningRevision;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Signatur- oder Abruflink zu einer Kundenvereinbarung (Feature 157). Der
 * Klartext-Link steht nur in dieser Mail; gespeichert wird sein Hash.
 */
class AgreementLinkMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public ContractSigningRevision $revision,
        public SignatureLinkPurpose $purpose,
        public string $url,
        public CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope {
        $contract = $this->revision->contract;

        return new Envelope(subject: (string) __('contract-signing.mail.' . $this->purpose->value . '.subject', [
            'kind' => $contract?->kind->label() ?? '',
            'organization' => $contract?->organization->name ?? config('app.name'),
        ]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.agreement-link', with: [
            'revision' => $this->revision,
            'contract' => $this->revision->contract,
            'purpose' => $this->purpose,
            'url' => $this->url,
            'expiresAt' => $this->expiresAt,
        ]);
    }
}
