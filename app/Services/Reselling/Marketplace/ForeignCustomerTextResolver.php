<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomerTextResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Models\Customer;
use Carbon\CarbonImmutable;

/**
 * Ordnet noch offene Marketplace-Firmen über die Rechnungstexte der Partner
 * zu: Nennt eine Rechnung an Kontakt K im Titel, in der Einleitung, im
 * Schlusstext oder in einer Position den Namen der Firma, dann wird die Firma
 * über K abgerechnet (Fremdkunde). Genau ein Kontakt → Zuordnung; mehrere →
 * Kandidaten für die manuelle Nacharbeit. Geprüft werden nur die Kontakte des
 * Partner-Pools (bereits zugeordnete Kontakte, Kunden mit Fremdkunden,
 * gespeicherte Partner-Zuordnungen), nie der ganze Lexoffice-Bestand.
 */
final class ForeignCustomerTextResolver {
    /**
     * @param  array<string, ContactMapping>  $mappings  Firmen-Schlüssel → Zuordnung (wird ergänzt)
     * @param  array<string, MarketplaceCompany>  $companies  Firmen-Schlüssel → Firma
     * @param  list<string>  $partnerContactIds
     * @param  array<string, Customer|null>  $customersByContact  Kontakt → Kunde (für Namen), optional
     * @return array<string, ContactMapping>
     */
    public function resolve(array $mappings, array $companies, InvoiceLinePool $pool, array $partnerContactIds, CarbonImmutable $from, CarbonImmutable $to, array $customersByContact = []): array {
        $open = array_filter($companies, static fn(MarketplaceCompany $company, string|int $key): bool => ! isset($mappings[$key]) || ! $mappings[$key]->isResolved(), ARRAY_FILTER_USE_BOTH);
        if ($open === [] || $partnerContactIds === []) {
            return $mappings;
        }

        /** @var array<string, array{text: string, recipient: string}> $texts Kontakt → gesammelte Belegtexte */
        $texts = [];
        foreach (array_unique($partnerContactIds) as $contactId) {
            $collected = [];
            $recipient = '';
            foreach ($pool->tryLinesFor($contactId, $from, $to) as $line) {
                $collected[] = ProductNameMatcher::normalize($line->fullText());
                if ($recipient === '' && $line->recipient !== '') {
                    $recipient = $line->recipient;
                }
            }
            if ($collected !== []) {
                $texts[$contactId] = ['text' => ' ' . implode(' | ', array_unique($collected)) . ' ', 'recipient' => $recipient];
            }
        }

        foreach ($open as $key => $company) {
            $wanted = $company->normalizedName();
            if (mb_strlen($wanted) < 4) {
                continue; // zu kurz für eine belastbare Textsuche
            }

            $hits = [];
            foreach ($texts as $contactId => $entry) {
                if (str_contains($entry['text'], ' ' . $wanted . ' ')) {
                    $hits[$contactId] = $entry['recipient'];
                }
            }

            if (count($hits) === 1) {
                $contactId = (string) array_key_first($hits);
                $customer = $customersByContact[$contactId] ?? null;
                $partnerName = $customer instanceof Customer ? $customer->name : ($hits[$contactId] !== '' ? $hits[$contactId] : $contactId);
                $mappings[$key] = new ContactMapping($company, $customer instanceof Customer ? $customer : null, [$contactId], ContactMapping::SOURCE_INVOICE_TEXT, [], 'über ' . $partnerName, $partnerName);

                continue;
            }

            if (count($hits) > 1) {
                $existing = $mappings[$key] ?? new ContactMapping($company, null, [], ContactMapping::SOURCE_NONE);
                $candidates = $existing->candidates;
                foreach ($hits as $contactId => $recipient) {
                    $customer = $customersByContact[$contactId] ?? null;
                    $candidates[] = 'Rechnungstext bei ' . ($customer instanceof Customer ? $customer->name : ($recipient !== '' ? $recipient : $contactId));
                }
                $mappings[$key] = new ContactMapping($company, $existing->customer, [], ContactMapping::SOURCE_NONE, array_values(array_unique($candidates)), $existing->detail, $existing->billedVia);
            }
        }

        return $mappings;
    }
}
