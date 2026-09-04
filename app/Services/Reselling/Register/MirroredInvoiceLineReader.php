<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredInvoiceLineReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\{Customer, ExternalReference, LexofficeVoucher, LexofficeVoucherLine};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Contracts\InvoiceLineSource;
use App\Services\Reselling\Marketplace\{InvoiceLine, MarketplaceCompany, NameTokenMatcher};
use Carbon\CarbonImmutable;

/**
 * Rechnungspositionen aus dem Belegspiegel (Feature 152, MVP-760) statt live
 * aus der Lexoffice-API — dieselbe Naht `InvoiceLineSource`, kein Ratenlimit,
 * reproduzierbar. Kontaktsuche über den Kundenstamm und seine Lexoffice-
 * Verknüpfungen.
 */
final class MirroredInvoiceLineReader implements InvoiceLineSource {
    private const EXCLUDED_STATUS = ['draft', 'voided'];

    public function __construct(private readonly int $organizationId) {}

    public function verifyAccess(): void {
        // Der Spiegel braucht keinen API-Zugang.
    }

    /** Gibt es überhaupt gespiegelte Positionen? Sonst muss die API lesen. */
    public function hasLines(): bool {
        return LexofficeVoucherLine::query()->withoutGlobalScopes()->where('organization_id', $this->organizationId)->exists();
    }

    /** Rechnungen, deren Positionen noch fehlen (Lücke im Spiegel). */
    public function pendingCount(): int {
        return LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->where('voucher_type', 'invoice')
            ->where('archived', false)
            ->whereNull('lines_synced_at')
            ->count();
    }

    public function linesForContact(string $externalContactId, CarbonImmutable $from, CarbonImmutable $to): array {
        $vouchers = LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->where('contact_external_id', $externalContactId)
            ->where('voucher_type', 'invoice')
            ->where('archived', false)
            ->whereNotIn('voucher_status', self::EXCLUDED_STATUS)
            ->where('voucher_date', '>=', $from->toDateString())
            ->where('voucher_date', '<', $to->addDay()->toDateString())
            ->whereNotNull('lines_synced_at')
            ->with(['lines' => static fn($q) => $q->orderBy('position')])
            ->orderBy('voucher_date')
            ->get();

        $lines = [];
        foreach ($vouchers as $voucher) {
            $date = $voucher->voucher_date === null ? null : CarbonImmutable::instance($voucher->voucher_date);
            if ($date === null) {
                continue;
            }
            foreach ($voucher->lines as $line) {
                $lines[] = new InvoiceLine(
                    voucherId: (string) $voucher->external_id,
                    voucherNumber: (string) ($voucher->voucher_number ?? ''),
                    voucherDate: $date,
                    voucherType: 'invoice',
                    contactId: $externalContactId,
                    position: $line->position,
                    name: $line->name,
                    description: (string) $line->description,
                    quantity: (float) $line->quantity,
                    unitNet: $line->unit_net->withScale(2),
                    voucherText: (string) ($voucher->voucher_text ?? ''),
                    recipient: (string) ($voucher->recipient_name ?? ''),
                    articleId: (string) ($line->external_article_id ?? ''),
                );
            }
        }

        return $lines;
    }

    public function findContactsByName(string $name): array {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            return [];
        }
        $wanted = MarketplaceCompany::normalizeName($name);
        $customers = Customer::query()->withoutGlobalScopes()->where('organization_id', $this->organizationId)->get(['id', 'name', 'company'])
            ->filter(static function (Customer $customer) use ($wanted, $name): bool {
                foreach ([$customer->name, (string) $customer->company] as $candidate) {
                    if ($candidate !== '' && (MarketplaceCompany::normalizeName($candidate) === $wanted || NameTokenMatcher::matches($candidate, $name))) {
                        return true;
                    }
                }

                return false;
            });

        return $this->contactsFor($customers);
    }

    public function findContactsByNumber(string $number): array {
        $number = trim($number);
        if ($number === '') {
            return [];
        }
        $customers = Customer::query()->withoutGlobalScopes()->where('organization_id', $this->organizationId)->where('number', $number)->get(['id', 'name', 'company']);

        return $this->contactsFor($customers);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     * @return list<array{id: string, name: string}>
     */
    private function contactsFor($customers): array {
        if ($customers->isEmpty()) {
            return [];
        }
        $byCustomer = $customers->keyBy('id');
        $references = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', (new Customer)->getMorphClass())
            ->whereIn('referenceable_id', $byCustomer->keys()->all())
            ->get(['referenceable_id', 'external_id']);
        $out = [];
        foreach ($references as $reference) {
            $customer = $byCustomer->get((int) $reference->referenceable_id);
            if ($customer === null) {
                continue;
            }
            $out[] = ['id' => (string) $reference->external_id, 'name' => (string) $customer->name];
        }

        return $out;
    }
}
