<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\OnlinePayment;

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Events\Invoicing\{InvoicePaymentReceived, InvoicePaymentReverted};
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\Organization;
use App\Plugins\Support\Payments\{OnlinePaymentRequest, OnlinePaymentSnapshot};
use App\Services\Invoicing\{DunningService, RetentionService};
use App\Services\Invoicing\OnlinePayment\Exceptions\OnlinePaymentException;
use App\Support\{DocumentLocale, OrganizationContext};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Log};
use Throwable;

/**
 * Online-Zahlung von Rechnungen (MVP-1067): Bezahlseite beim Anbieter über
 * den offenen Betrag anlegen, Stand beim Anbieter nachfragen und die Deckung
 * der Rechnung fortschreiben. Gezahlte Beträge zählen über
 * {@see OnlinePayment::settledSumFor()} in die Zahlungsdeckung
 * (`PaymentStatusProvider::allocatedSum()`) — Mahnwesen, Girocode und
 * Bankabgleich sehen sie dadurch ohne eigene Rechnung.
 */
class OnlinePaymentService {
    /** Eine Bezahlseite wird wiederverwendet, solange sie noch so lange gilt. */
    private const REUSE_MARGIN_MINUTES = 5;

    private const DEFAULT_CHECKOUT_MINUTES = 60;

    public function __construct(
        private readonly OnlinePaymentProviderResolver $providers,
        private readonly InvoicePaymentLinkService $links,
        private readonly DunningService $dunning,
        private readonly RetentionService $retentions,
    ) {}

    /**
     * Bezahlseite für den aktuell offenen Betrag.
     *
     * @throws OnlinePaymentException
     */
    public function checkoutUrl(Invoice $invoice, string $token): string {
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw new OnlinePaymentException(OnlinePaymentException::PAID);
        }
        if (! $this->links->payable($invoice)) {
            throw new OnlinePaymentException(OnlinePaymentException::NOT_PAYABLE);
        }
        $organization = $invoice->organization;
        $provider = $organization !== null ? $this->providers->forOrganization($organization) : null;
        if ($organization === null || $provider === null) {
            throw new OnlinePaymentException(OnlinePaymentException::UNAVAILABLE);
        }

        $open = $this->dunning->openAmount($invoice);
        $reusable = OnlinePayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('provider', $provider->onlinePaymentProviderId())
            ->where('status', OnlinePaymentStatus::Open->value)
            ->whereNotNull('checkout_url')
            ->where('checkout_expires_at', '>', now()->addMinutes(self::REUSE_MARGIN_MINUTES))
            ->latest('id')
            ->get()
            ->first(static fn (OnlinePayment $payment): bool => $payment->gross_amount->compareTo($open) === 0);
        if ($reusable instanceof OnlinePayment) {
            return (string) $reusable->checkout_url;
        }

        $payment = OnlinePayment::query()->create([
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'provider' => $provider->onlinePaymentProviderId(),
            'status' => OnlinePaymentStatus::Open,
            'currency' => $open->getCurrency()->value,
            'gross_amount' => $open,
            'refunded_amount' => Money::zero($open->getCurrency()),
        ]);

        try {
            $checkout = $provider->createCheckout($organization, new OnlinePaymentRequest(
                reference: (string) $payment->sqid,
                amount: $open,
                description: (string) __('payments.description', ['number' => (string) $invoice->number]),
                returnUrl: route('payments.done', $token),
                webhookUrl: route('payments.webhook', $provider->onlinePaymentProviderId()),
                locale: DocumentLocale::for($invoice->customer, $organization),
                customerEmail: $invoice->customer->email,
            ));
        } catch (Throwable $e) {
            $payment->update(['status' => OnlinePaymentStatus::Failed]);
            report($e);

            throw new OnlinePaymentException(OnlinePaymentException::UNAVAILABLE);
        }

        $payment->update([
            'provider_reference' => $checkout->providerReference,
            'checkout_url' => $checkout->checkoutUrl,
            'checkout_expires_at' => $checkout->expiresAt ?? now()->addMinutes(self::DEFAULT_CHECKOUT_MINUTES),
        ]);

        return $checkout->checkoutUrl;
    }

    /** Webhook: nur der Anstoß — der Stand kommt aus der Nachfrage beim Anbieter. */
    public function handleWebhook(string $providerId, Request $request): void {
        $reference = $this->providers->byId($providerId)?->webhookReference($request);
        if ($reference === null || $reference === '') {
            return;
        }

        // TENANT-BYPASS: Webhook ohne Anmeldung; gefunden über Anbieter und Referenz, geprüft beim Anbieter.
        $payment = OnlinePayment::query()->withoutGlobalScopes()
            ->where('provider', $providerId)
            ->where('provider_reference', $reference)
            ->first();
        if ($payment instanceof OnlinePayment) {
            $this->refresh($payment);
        }
    }

    /** Offene Bezahlseiten der Rechnung nachfragen — die Rückkehr vom Anbieter wartet nicht auf den Webhook. */
    public function refreshOpen(Invoice $invoice): void {
        OnlinePayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', OnlinePaymentStatus::Open->value)
            ->whereNotNull('provider_reference')
            ->latest('id')
            ->limit(3)
            ->get()
            ->each(fn (OnlinePayment $payment) => $this->refresh($payment));
    }

    public function refresh(OnlinePayment $payment): void {
        $provider = $this->providers->byId($payment->provider);
        $organization = Organization::query()->withoutGlobalScopes()->find($payment->organization_id);
        if ($provider === null || ! $organization instanceof Organization || $payment->provider_reference === null) {
            return;
        }

        OrganizationContext::run($organization, function () use ($provider, $organization, $payment): void {
            try {
                $snapshot = $provider->fetchPayment($organization, (string) $payment->provider_reference);
            } catch (Throwable $e) {
                report($e);

                return;
            }

            // Betrag und Währung müssen zum Auftrag passen — sonst wird nichts gebucht.
            if ($snapshot->amount->getCurrency()->value !== $payment->currency || $snapshot->amount->compareTo($payment->gross_amount) !== 0) {
                Log::warning('Online-Zahlung: Betrag des Anbieters weicht vom Auftrag ab.', [
                    'online_payment_id' => $payment->id,
                    'provider' => $payment->provider,
                ]);

                return;
            }

            $this->apply($payment, $snapshot);
        });
    }

    private function apply(OnlinePayment $payment, OnlinePaymentSnapshot $snapshot): void {
        $changed = DB::transaction(function () use ($payment, $snapshot): bool {
            $locked = OnlinePayment::query()->lockForUpdate()->findOrFail($payment->id);
            $before = [$locked->status, $locked->refunded_amount->getAmount()];

            if ($snapshot->status !== $locked->status && $locked->status->canTransitionTo($snapshot->status)) {
                $locked->status = $snapshot->status;
            }
            if (in_array($locked->status, [OnlinePaymentStatus::Paid, OnlinePaymentStatus::Refunded], true)) {
                $locked->paid_at ??= Carbon::make($snapshot->paidAt) ?? now();
                $locked->fee_amount ??= $snapshot->fee;
                $locked->method ??= $snapshot->method;
                $refunded = $locked->status === OnlinePaymentStatus::Refunded
                    ? $locked->gross_amount
                    : ($snapshot->refunded ?? $locked->refunded_amount);
                $locked->refunded_amount = $refunded->compareTo($locked->gross_amount) > 0 ? $locked->gross_amount : $refunded;
            }
            $locked->save();

            return $before !== [$locked->status, $locked->refunded_amount->getAmount()];
        });

        if ($changed) {
            $payment->refresh();
            $invoice = Invoice::query()->find($payment->invoice_id);
            if ($invoice instanceof Invoice) {
                $this->syncInvoice($invoice, $payment);
            }
        }
    }

    /** Deckung fortschreiben wie der Bankabgleich: bezahlt, teilbezahlt oder zurück auf offen. */
    private function syncInvoice(Invoice $invoice, OnlinePayment $payment): void {
        $paid = $this->dunning->paidAmount($invoice)->toFloat();
        $payable = $this->retentions->payableAmountOf($invoice);

        if ($paid + 0.005 >= $payable) {
            if ($invoice->status !== Invoice::STATUS_PAID) {
                $invoice->status = Invoice::STATUS_PAID;
                $invoice->paid_on = $payment->paid_at?->copy()->startOfDay();
                // Mit Ereignissen speichern: der Statuswechsel ist die Naht für invoice.paid und Provision.
                $invoice->save();
            }
            InvoicePaymentReceived::dispatch($invoice);

            return;
        }

        if ($invoice->status === Invoice::STATUS_PAID || ($invoice->status === Invoice::STATUS_PARTIALLY_PAID && $paid <= 0.005)) {
            $invoice->status = $paid > 0.005 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_ISSUED;
            $invoice->paid_on = null;
            $invoice->save();
            InvoicePaymentReverted::dispatch($invoice);

            return;
        }

        if ($invoice->status === Invoice::STATUS_ISSUED && $paid > 0.005) {
            $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
            $invoice->save();
            InvoicePaymentReceived::dispatch($invoice);
        }
    }
}
