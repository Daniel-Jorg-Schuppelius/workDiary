<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Sales\OrderConfirmation;
use App\Services\DocumentDesign\{DocumentDesignRenderer, IssuerIdentity};
use App\Services\UI\BrandingService;

/**
 * PDF der Auftragsbestätigung (MVP-650; seit Phase 145, MVP-1162, eigener
 * Beleg mit Nummer). Das Design erbt über die Fallback-Art das Rechnungs- bzw.
 * CI-Basisprofil; beim Bestätigen wird der Stand als Render-Snapshot
 * eingefroren.
 */
class OrderConfirmationPdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** PDF-Bytes der Auftragsbestätigung (A4). */
    public function output(OrderConfirmation $confirmation): string {
        $confirmation->loadMissing(['items', 'customer', 'organization', 'quote']);

        // Belegsprache je Kunde (Feature 034, MVP-721): nur Darstellung.
        return \App\Support\DocumentLocale::within($confirmation->customer, $confirmation->organization, fn (): string => $this->design->renderPdf(
            RenderDocumentKind::OrderConfirmation,
            'quotes.order-confirmation-pdf',
            $this->viewData($confirmation),
            $confirmation->organization,
            payload: $this->design->payloadForDocument($confirmation, RenderDocumentKind::OrderConfirmation),
        ));
    }

    /** @return array<string, mixed> */
    public function viewData(OrderConfirmation $confirmation): array {
        $confirmation->loadMissing(['items', 'customer', 'organization', 'quote']);

        return [
            'confirmation' => $confirmation,
            'orgLegal' => app(BrandingService::class)->legalFor($confirmation->organization),
            'design' => $this->design->contextForDocument($confirmation, RenderDocumentKind::OrderConfirmation, $this->design->payloadForDocument($confirmation, RenderDocumentKind::OrderConfirmation)),
            // Bestätigt: Aussteller und Ansprechpartner des Bestätigungstages (MVP-1624).
            'issuer' => is_array($confirmation->issuer_snapshot['issuer'] ?? null) ? $confirmation->issuer_snapshot['issuer'] : IssuerIdentity::for($confirmation->organization),
            'contactPerson' => is_array($confirmation->issuer_snapshot) && array_key_exists('contact', $confirmation->issuer_snapshot)
                ? (is_array($confirmation->issuer_snapshot['contact']) ? $confirmation->issuer_snapshot['contact'] : null)
                : self::contactPerson($confirmation),
        ];
    }

    /**
     * Ansprechpartner der Auftragsbestätigung: die Person, die sie angelegt hat.
     *
     * @return array{name: string, phone: string, email: string}|null
     */
    public static function contactPerson(OrderConfirmation $confirmation): ?array {
        $person = $confirmation->creator;

        return $person === null ? null : ['name' => (string) $person->name, 'phone' => (string) ($person->phone ?? ''), 'email' => (string) ($person->email ?? '')];
    }
}
