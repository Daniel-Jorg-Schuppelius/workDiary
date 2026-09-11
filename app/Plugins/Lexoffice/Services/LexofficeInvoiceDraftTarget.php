<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceDraftTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Finance\BillingMode;
use App\Models\{Customer, Organization, User};
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeDraftInvoiceService};
use App\Services\Finance\BillingModeResolver;
use App\Services\Invoicing\TaxResolver;
use App\Services\Reselling\Draft\{DraftResult, InvoiceDraftTarget};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Lexoffice als Entwurfsziel des Reselling-Registers (Feature 152, MVP-764 /
 * Review 2026-09-11): Rechnungsentwurf per `POST /invoices` ohne `finalize`
 * an den verknüpften Kontakt des Empfängers ({@see LexofficeContactMap}).
 * Entwürfe werden nicht gespiegelt — kein Bezug, nur der Stempel; die
 * fertige Rechnung holt der Belegspiegel, der Vorschlagslauf ordnet sie zu.
 * Gilt für Empfänger mit Lexoffice-Rechnungshoheit.
 */
final class LexofficeInvoiceDraftTarget implements InvoiceDraftTarget {
    public const KEY = 'lexoffice';

    public function __construct(
        private readonly TaxResolver $taxes,
        private readonly BillingModeResolver $billingModes,
        private readonly ?LexofficeDraftInvoiceService $service = null,
    ) {}

    public function key(): string {
        return self::KEY;
    }

    public function supports(Customer $recipient): bool {
        return $this->billingModes->effectiveFor($recipient) === BillingMode::Lexoffice;
    }

    public function ensureAvailable(Organization $organization, Customer $recipient): void {
        $this->contactFor($organization, $recipient);
    }

    /**
     * Erster verknüpfter Kontakt des Empfängers — setzt aktives Plugin mit API-Schlüssel voraus.
     *
     * @throws RuntimeException Plugin inaktiv / kein Kontakt
     */
    private function contactFor(Organization $organization, Customer $recipient): string {
        $config = LexofficeConfig::resolve($organization->id);
        if ($config['enabled'] !== true || ! is_string($config['api_key']) || $config['api_key'] === '') {
            throw new RuntimeException((string) __('resale.draft.error.lexoffice'));
        }
        $contacts = LexofficeContactMap::forCustomer($recipient)->byCustomer($recipient->id);
        if ($contacts === []) {
            throw new RuntimeException((string) __('resale.link.no_contacts'));
        }

        return $contacts[0];
    }

    public function draft(Organization $organization, Customer $recipient, array $lines, ?User $user, CarbonImmutable $reference): DraftResult {
        $contact = $this->contactFor($organization, $recipient);
        $config = LexofficeConfig::resolve($organization->id);
        $items = [];
        $net = 0.0;
        foreach ($lines as $entry) {
            $items[] = $entry['line'];
            $net += $entry['line']['quantity'] * $entry['line']['unit_net'];
        }

        $tax = $this->taxes->resolve($organization, $recipient);
        $service = $this->service ?? new LexofficeDraftInvoiceService((string) $config['api_key'], (string) $config['base_url']);
        $draftId = $service->createDraft(
            $contact,
            $items,
            (string) __('resale.draft.title'),
            (string) __('resale.draft.introduction', ['count' => count($items)]),
            (string) ($tax['note'] ?? ''),
            (float) $tax['rate'],
            $recipient->currency->value,
            (string) $config['defaults']['default_tax_type'],
        );

        return new DraftResult(
            reference: $draftId,
            label: trim((string) __('resale.draft.note', ['id' => $draftId, 'date' => Tz::now()->format('d.m.Y'), 'user' => $user !== null ? $user->name : ''])),
            url: null,
            morphClass: null,
            morphIds: [],
            lines: count($items),
            net: round($net, 2),
        );
    }
}
