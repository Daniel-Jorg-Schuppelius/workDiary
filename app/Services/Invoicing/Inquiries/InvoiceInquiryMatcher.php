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
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{Invoice, InvoiceInquiry};
use App\Models\Platform\Organization;
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Mail\{KnownSenders, MailIntakeService, ParsedMessage};
use App\Support\ReferenceTokens;
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
    ) {}

    /**
     * Antwort auf eine von WorkDiary versandte Rechnung: In-Reply-To oder
     * References nennen die Message-ID eines Versandversuchs, einer früheren
     * Nachfrage oder einer eigenen Antwort darauf. Nur die eigene
     * Organisation; fremde Message-IDs laufen ins Leere.
     */
    public function threaded(Organization $organization, ParsedMessage $message): ?InvoiceInquiryTarget {
        $variants = $message->referencedIds();
        if ($variants === []) {
            return null;
        }

        $dispatch = DocumentDispatch::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('invoice_id')
            ->whereIn('meta->message_id', $variants)
            ->orderByDesc('id')
            ->first();
        $invoice = $dispatch?->invoice()->withoutGlobalScopes()->first();
        if ($invoice instanceof Invoice) {
            return InvoiceInquiryTarget::local($invoice);
        }

        return $this->threadedInquiry($organization, $variants);
    }

    /**
     * Antwort auf eine frühere Nachfrage oder auf eine eigene Antwort aus der
     * Notiz (lk-20): die References nennen die Nachfrage selbst, oder
     * In-Reply-To nennt die Message-ID der Antwort, deren Versandverweis auf
     * die Nachfrage zeigt.
     *
     * @param  list<string>  $variants
     */
    private function threadedInquiry(Organization $organization, array $variants): ?InvoiceInquiryTarget {
        $candidates = $variants;
        $replies = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', MailIntakeService::PLUGIN_ID)
            ->where('external_type', MailIntakeService::EXTERNAL_TYPE)
            ->whereIn('external_id', $variants)
            ->get(['payload']);
        foreach ($replies as $reply) {
            $bare = ParsedMessage::bareMessageId((string) (((array) $reply->payload)['in_reply_to'] ?? ''));
            if ($bare !== null) {
                array_push($candidates, $bare, '<' . $bare . '>');
            }
        }

        $inquiry = InvoiceInquiry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('message_id', array_values(array_unique($candidates)))
            ->orderByDesc('id')
            ->first();
        if (! $inquiry instanceof InvoiceInquiry) {
            return null;
        }
        if ($inquiry->invoice_id !== null) {
            $invoice = Invoice::query()->withoutGlobalScopes()->find($inquiry->invoice_id);

            return $invoice instanceof Invoice ? InvoiceInquiryTarget::local($invoice) : null;
        }
        foreach ($this->byNumbers($organization, [(string) $inquiry->invoice_number]) as $target) {
            if (! $target->isLocal() && $target->source() === $inquiry->external_source && $target->key() === $inquiry->external_key) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Rechnungen und Belege zu den Nummern im Text (Betreff + Text).
     *
     * @return list<InvoiceInquiryTarget>
     */
    public function fromText(Organization $organization, string $text): array {
        return $this->byNumbers($organization, ReferenceTokens::fromText($text));
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
        $customer = $target->customerId !== null
            ? Customer::query()->withoutGlobalScopes()->whereKey($target->customerId)->first()
            : null;
        $known = $customer?->knownEmailAddresses() ?? [];

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

        return KnownSenders::contains($known, $fromEmail);
    }
}
