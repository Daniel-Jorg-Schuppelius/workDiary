<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePaymentLinkService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\OnlinePayment;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Invoicing\{Invoice, InvoicePaymentLink};
use App\Services\Invoicing\DunningService;
use App\Settings\SettingsRegistry;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use CommonToolkit\Helper\Data\{CryptoHelper, DataUrlHelper};
use Illuminate\Support\Str;

/**
 * Stabiler Zahlungslink je Rechnung (MVP-1067) für PDF, Mail und Portal. Er
 * zeigt auf workDiary, nicht auf den Anbieter: Erst beim Aufruf entsteht die
 * Bezahlseite über den dann offenen Betrag — ein gedruckter QR-Code bleibt so
 * nach Teilzahlungen richtig.
 */
class InvoicePaymentLinkService {
    public function __construct(
        private readonly OnlinePaymentProviderResolver $providers,
        private readonly DunningService $dunning,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * Zahlungslink — null, wenn die Rechnung nicht online zahlbar ist oder kein
     * Anbieter aktiv. Auf PDF und Mail nur mit `payments.online.on_documents`;
     * das Kundenportal zeigt ihn immer.
     */
    public function urlFor(Invoice $invoice, bool $onDocuments = true): ?string {
        $organization = $invoice->organization;
        if ($organization === null
            || ! $this->payable($invoice)
            || ($onDocuments && ! filter_var($this->settings->effective('payments.online.on_documents', $organization)->value, FILTER_VALIDATE_BOOL))
            || $this->providers->forOrganization($organization) === null) {
            return null;
        }

        return route('payments.show', $this->tokenFor($invoice));
    }

    /** @return array{url: string, qr: string}|null Link und QR-Code (SVG als Data-URI) für das Rechnungs-PDF. */
    public function documentLink(Invoice $invoice): ?array {
        $url = $this->urlFor($invoice);
        if ($url === null) {
            return null;
        }
        $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd())))->writeString($url);

        return ['url' => $url, 'qr' => (string) DataUrlHelper::encode($svg, 'image/svg+xml')];
    }

    public function payable(Invoice $invoice): bool {
        return in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)
            && ! $invoice->isCreditNote()
            && ! $invoice->isCancelled()
            && ! $invoice->isProforma()
            && $this->dunning->openAmount($invoice)->isPositive();
    }

    /** Rechnung zum Klartext-Token — die einzige Auflösung ohne Anmeldung. */
    public function resolve(string $token): ?Invoice {
        $link = InvoicePaymentLink::findByAccessToken($token);
        if ($link === null) {
            return null;
        }

        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('organization_id', $link->organization_id)
            ->find($link->invoice_id);

        return $invoice instanceof Invoice ? $invoice : null;
    }

    private function tokenFor(Invoice $invoice): string {
        $token = Str::random(40);
        $link = InvoicePaymentLink::query()->createOrFirst(
            ['invoice_id' => $invoice->id],
            ['organization_id' => $invoice->organization_id, 'token' => $token, 'token_hash' => CryptoHelper::hash($token)],
        );

        return $link->token;
    }
}
