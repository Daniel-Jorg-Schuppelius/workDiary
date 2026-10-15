<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Inquiries;

use App\Models\Customer\Customer;
use App\Models\Document\DocumentDispatch;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Mail\{MailReferenceMatcher, ParsedMessage};
use CommonToolkit\Helper\Data\EmailHelper;
use Illuminate\Database\Eloquent\Builder;

/**
 * Findet zu einer Mail die Rechnung oder den Beleg (Feature 166, MVP-1200):
 * zuerst der Nachrichtenfaden gegen den eigenen Versand, sonst Nummern aus
 * Betreff und Text gegen lokale Rechnungen und den Belegspiegel aller
 * Buchhaltungsprogramme. Bei gleicher Nummer gewinnt die lokale Rechnung.
 */
final class InvoiceInquiryMatcher {
    public function __construct(
        private readonly ExternalRevenue $external,
        private readonly MailReferenceMatcher $references,
    ) {}

    /**
     * Antwort auf eine von WorkDiary versandte Rechnung: In-Reply-To oder
     * References nennen die Message-ID eines Versandversuchs. Nur die eigene
     * Organisation; fremde Message-IDs laufen ins Leere.
     */
    public function threaded(Organization $organization, ParsedMessage $message): ?InvoiceInquiryTarget {
        $referenced = array_values(array_filter([$message->inReplyTo, ...$message->references]));
        if ($referenced === []) {
            return null;
        }
        // Der Versand speichert die ID mit spitzen Klammern, Clients zitieren sie mit oder ohne.
        $variants = [];
        foreach ($referenced as $id) {
            $bare = trim((string) $id, "<> \t");
            if ($bare === '') {
                continue;
            }
            $variants[] = $bare;
            $variants[] = '<' . $bare . '>';
        }
        if ($variants === []) {
            return null;
        }

        $dispatch = DocumentDispatch::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('invoice_id')
            ->whereIn('meta->message_id', array_values(array_unique($variants)))
            ->orderByDesc('id')
            ->first();
        $invoice = $dispatch?->invoice()->withoutGlobalScopes()->first();

        return $invoice instanceof Invoice ? InvoiceInquiryTarget::local($invoice) : null;
    }

    /**
     * Rechnungen und Belege zu den Nummern im Text (Betreff + Text).
     *
     * @return list<InvoiceInquiryTarget>
     */
    public function fromText(Organization $organization, string $text): array {
        return $this->byNumbers($organization, $this->references->tokens($text));
    }

    /**
     * Rechnungen (`number`/`external_number`) und Belege des Spiegels zu
     * diesen Nummern, je Nummer ein Ziel; lokal vor Spiegel.
     *
     * @param  list<string>  $numbers
     * @return list<InvoiceInquiryTarget>
     */
    public function byNumbers(Organization $organization, array $numbers): array {
        $numbers = array_values(array_unique(array_filter(array_map('trim', $numbers), static fn (string $n): bool => $n !== '')));
        if ($numbers === []) {
            return [];
        }

        /** @var array<string, InvoiceInquiryTarget> $targets */
        $targets = [];
        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(static fn (Builder $q) => $q->whereIn('number', $numbers)->orWhereIn('external_number', $numbers))
            ->orderBy('id')
            ->get();
        foreach ($invoices as $invoice) {
            $target = InvoiceInquiryTarget::local($invoice);
            foreach ([$invoice->number, $invoice->external_number] as $number) {
                if ($number !== null && $number !== '') {
                    $targets[mb_strtoupper((string) $number)] ??= $target;
                }
            }
        }

        foreach ($this->external->invoicesByNumber((int) $organization->id, $numbers) as $external) {
            $targets[mb_strtoupper($external->number)] ??= InvoiceInquiryTarget::external($external);
        }

        // Dasselbe Ziel kann unter zwei Nummern stehen (Rechnung mit Programmnummer).
        $unique = [];
        foreach ($targets as $target) {
            $id = $target->isLocal() ? 'local:' . $target->invoice?->getKey() : 'ext:' . $target->source() . ':' . $target->key();
            $unique[$id] ??= $target;
        }

        return array_values($unique);
    }

    /**
     * Gehört die Absenderadresse zum Vorgang? Anerkannt sind die Adresse des
     * Kunden, seiner Ansprechpartner und Portalkonten sowie Empfänger und CC
     * früherer Versandversuche der Rechnung (Sicherheitsaudit 2026-09-17,
     * ingress-1: Rechnungsnummern sind fortlaufend und erratbar).
     */
    public function senderBelongs(InvoiceInquiryTarget $target, string $fromEmail): bool {
        $sender = EmailHelper::normalize($fromEmail);
        if ($sender === '') {
            return false;
        }

        $known = [];
        $customer = $target->customerId !== null
            ? Customer::query()->withoutGlobalScopes()->whereKey($target->customerId)->first()
            : null;
        if ($customer !== null) {
            $known[] = (string) $customer->email;
            foreach ($customer->contact_persons ?? [] as $person) {
                $known[] = (string) ($person['email'] ?? '');
            }
            foreach (User::query()->withoutGlobalScopes()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->getKey())
                ->pluck('email') as $portalEmail) {
                $known[] = (string) $portalEmail;
            }
        }

        if ($target->invoice !== null) {
            foreach (DocumentDispatch::query()->withoutGlobalScopes()
                ->where('invoice_id', $target->invoice->getKey())
                ->get(['recipient', 'meta']) as $dispatch) {
                $known[] = (string) ($dispatch->recipient ?? '');
                foreach ((array) (((array) $dispatch->meta)['cc'] ?? []) as $cc) {
                    $known[] = (string) $cc;
                }
            }
        }

        foreach ($known as $candidate) {
            $candidate = EmailHelper::normalize($candidate);
            if ($candidate !== '' && hash_equals($candidate, $sender)) {
                return true;
            }
        }

        return false;
    }
}
