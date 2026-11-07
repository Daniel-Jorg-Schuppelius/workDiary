<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceDispatchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ExternalInvoices;

use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\Finance\{ExternalInvoiceFormat, ExternalInvoiceStatus, ExternalPackageKind, ProviderSendState};
use App\Enums\Invoicing\ExternalDispatchRoute;
use App\Mail\ExternalInvoiceMail;
use App\Models\Document\DocumentDispatch;
use App\Models\Finance\{ExternalInvoice, ExternalInvoicePackage};
use App\Models\Invoicing\{InvoiceMailTemplate, InvoiceSupportingDocument};
use App\Models\Platform\User;
use App\Services\Invoicing\ActivityReportPdfRenderer;
use App\Services\Invoicing\EInvoice\{RecipientProfiles, SupportingDocumentService};
use App\Services\Peppol\{PeppolInvoiceDispatcher, PeppolParticipantService};
use App\Support\DocumentLocale;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Zustellung extern ausgestellter Rechnungen durch WorkDiary (Feature 196,
 * Nachtrag 07.11.2026). Rechnungshoheit und Versandweg sind getrennt: das
 * Fakturasystem führt die Rechnung, WorkDiary stellt sie mit Nachweisen zu.
 *
 * Reihenfolge: Vorprüfung des ganzen Wegs (Versandweg, Festschreibung,
 * Original, Zuordnung, Abgleich, Anbieterversand, Format und Kanal) → Nachweis
 * erzeugen und einfrieren → Versandfassung bauen und festschreiben → zustellen.
 * Eine Wiederholung sendet dieselbe Fassung; spätere Nachweise gehen als
 * Nachsendung mit Rechnungsbezug, nie als zweite Rechnung.
 */
final class ExternalInvoiceDispatchService {
    /** Ohne Rückmeldung gilt ein Zustellversuch danach als ungeklärt. */
    public const UNCLEAR_AFTER_MINUTES = 30;

    public function __construct(
        private readonly ExternalInvoiceService $invoices,
        private readonly ExternalInvoicePackager $packager,
        private readonly SupportingDocumentService $documents,
        private readonly ActivityReportPdfRenderer $activityReports,
        private readonly RecipientProfiles $profiles,
        private readonly PeppolInvoiceDispatcher $peppol,
    ) {}

    public function route(ExternalInvoice $invoice): ExternalDispatchRoute {
        return $this->profiles->dispatchRoute($invoice->customer, $invoice->organization);
    }

    /**
     * Sperrgründe und Hinweise für einen Versand; ohne Sperrgrund darf er raus.
     *
     * @param  array{provider_not_sent?: bool, redelivery?: bool}  $confirm
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function preflight(ExternalInvoice $invoice, string $channel, ExternalPackageKind $kind, array $confirm = []): array {
        $errors = [];
        $warnings = [];
        $invoice->loadMissing(['customer', 'organization']);
        $profile = $this->profiles->for($invoice->customer, $invoice->organization);

        if ($this->route($invoice) !== ExternalDispatchRoute::WorkDiary) {
            $errors[] = __('finance.external_invoice.error.route_provider');
        }
        if (! in_array($channel, [DocumentDispatch::CHANNEL_EMAIL, DocumentDispatch::CHANNEL_PEPPOL], true)) {
            $errors[] = __('finance.external_invoice.error.channel');
        }
        $errors = [...$errors, ...$this->statusErrors($invoice)];
        $packages = $invoice->packages()->get();
        $delivered = $packages->first(fn (ExternalInvoicePackage $p): bool => $p->kind === ExternalPackageKind::Initial && $p->isDelivered());
        $pending = $packages->first(fn (ExternalInvoicePackage $p): bool => $this->isPending($p));
        if ($pending !== null) {
            $errors[] = $this->isUnclear($pending) ? __('finance.external_invoice.error.unclear') : __('finance.external_invoice.error.in_flight');
        }

        if ($kind === ExternalPackageKind::Resend) {
            if ($delivered === null) {
                $errors[] = __('finance.external_invoice.error.resend_without_initial');
            }
            if ($channel !== DocumentDispatch::CHANNEL_EMAIL) {
                $errors[] = __('finance.external_invoice.error.resend_channel');
            }
            if ($profile->xmlOnlyMail()) {
                $errors[] = __('finance.external_invoice.error.resend_public_sector');
            }
            if (! $invoice->supportingDocuments()->whereNull('frozen_at')->where('is_embedded', true)->exists()) {
                $errors[] = __('finance.external_invoice.error.resend_nothing');
            }

            return ['errors' => array_values(array_unique(array_map('strval', $errors))), 'warnings' => []];
        }

        if ($delivered !== null) {
            $errors[] = __('finance.external_invoice.error.already_delivered');
        }
        if (! $invoice->positionLinks()->exists()) {
            $errors[] = __('finance.external_invoice.error.no_sources');
        } elseif (! $this->invoices->isReconciled($invoice)) {
            $errors[] = __('finance.external_invoice.error.deviation');
        }

        if ($invoice->provider_send_state === ProviderSendState::Sent && empty($confirm['redelivery'])) {
            $errors[] = __('finance.external_invoice.error.provider_sent');
        }
        if ($invoice->provider_send_state === ProviderSendState::Unknown
            && ! (bool) $this->profiles->setting('einvoice.provider_send_disabled', $invoice->organization)
            && empty($confirm['provider_not_sent'])) {
            $errors[] = __('finance.external_invoice.error.provider_unknown');
        }

        $format = $invoice->original_format;
        $original = $this->invoices->originalContent($invoice);
        if ($invoice->original_sha256 === null) {
            $errors[] = __('finance.external_invoice.error.no_original');
        } elseif ($original === null) {
            $errors[] = __('finance.external_invoice.error.original_missing');
        } elseif ($format === null) {
            $errors[] = __('finance.external_invoice.blocked.format_unknown');
        } else {
            $blocker = $this->packager->blocker($format, $original);
            if ($blocker !== null) {
                $errors[] = $blocker;
            }
            if ($channel === DocumentDispatch::CHANNEL_PEPPOL) {
                if (! $format->peppolCapable()) {
                    $errors[] = __('finance.external_invoice.error.peppol_format');
                } elseif ($invoice->customer === null || PeppolParticipantService::forCustomer($invoice->customer) === null) {
                    $errors[] = __('finance.external_invoice.error.peppol_participant');
                }
            }
            if ($channel === DocumentDispatch::CHANNEL_EMAIL && $profile->xmlOnlyMail() && ! $format->isXml()) {
                $errors[] = __('finance.external_invoice.error.xml_only');
            }
            if ($profile->buyerReferenceRequired() && $format->isElectronic() && $blocker === null && $this->packager->buyerReference($format, $original) === null) {
                $errors[] = __('finance.external_invoice.error.buyer_reference');
            }
            if ($format === ExternalInvoiceFormat::Pdf && $invoice->supportingDocuments()->whereNotNull('external_url')->exists()) {
                $warnings[] = __('finance.external_invoice.warning.links_in_mail');
            }
        }

        if ($this->profiles->attachesActivityReport($invoice->customer, $invoice->organization) && ! $this->activityReports->hasEntries($invoice)) {
            $warnings[] = __('finance.external_invoice.warning.no_activity_report');
        }

        return [
            'errors' => array_values(array_unique(array_map('strval', $errors))),
            'warnings' => array_values(array_unique(array_map('strval', $warnings))),
        ];
    }

    /**
     * Versandfassung bauen und zustellen.
     *
     * @param  array{to: list<string>, cc?: list<string>, bcc?: list<string>, template_id?: int|null, custom_text?: string|null}  $mail
     * @param  array{provider_not_sent?: bool, redelivery?: bool}  $confirm
     *
     * @throws ValidationException mit konkreten Sperrgründen
     */
    public function dispatch(ExternalInvoice $invoice, string $channel, ExternalPackageKind $kind, array $mail, User $actor, array $confirm = []): DocumentDispatch {
        $package = DB::transaction(function () use ($invoice, $channel, $kind, $mail, $actor, $confirm): ExternalInvoicePackage {
            // Sperre je Rechnung: zwei Klicks bauen nicht zwei Fassungen.
            $locked = ExternalInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $check = $this->preflight($locked, $channel, $kind, $confirm);
            if ($check['errors'] !== []) {
                throw ValidationException::withMessages(['dispatch' => $check['errors']]);
            }

            if ($kind === ExternalPackageKind::Initial) {
                $this->ensureActivityReport($locked, $actor);
            }
            /** @var list<InvoiceSupportingDocument> $documents */
            $documents = $kind === ExternalPackageKind::Initial
                ? $locked->supportingDocuments()->get()->all()
                : $locked->supportingDocuments()->whereNull('frozen_at')->get()->all();
            $this->documents->freeze($locked);
            $documents = array_map(static fn (InvoiceSupportingDocument $d): InvoiceSupportingDocument => $d->refresh(), $documents);

            $recipient = $channel === DocumentDispatch::CHANNEL_PEPPOL
                ? (string) ($locked->customer === null ? '' : PeppolParticipantService::forCustomer($locked->customer)?->canonical())
                : implode(', ', $mail['to']);
            $original = (string) $this->invoices->originalContent($locked);
            $package = $this->packager->package(
                $locked,
                $original,
                $kind,
                $channel,
                $recipient,
                $documents,
                [
                    'cc' => $mail['cc'] ?? [],
                    'bcc' => $mail['bcc'] ?? [],
                    'template_id' => $mail['template_id'] ?? null,
                    'custom_text' => $mail['custom_text'] ?? null,
                    'confirm' => array_keys(array_filter($confirm)),
                ],
                $this->profiles->limitBytes($locked->customer, $locked->organization),
                $actor,
            );
            $locked->audit('externalInvoice.packaged', ['package_id' => $package->id, 'kind' => $kind->value, 'sha256' => $package->sha256]);

            return $package;
        });

        return $this->deliver($package, $actor);
    }

    /** Fehlgeschlagene Zustellung mit denselben Bytes wiederholen. */
    public function retry(ExternalInvoicePackage $package, User $actor): DocumentDispatch {
        if ($this->isPending($package)) {
            throw ValidationException::withMessages(['dispatch' => __($this->isUnclear($package) ? 'finance.external_invoice.error.unclear' : 'finance.external_invoice.error.in_flight')]);
        }
        if ($package->isDelivered()) {
            throw ValidationException::withMessages(['dispatch' => __('finance.external_invoice.error.already_delivered')]);
        }

        return $this->deliver($package, $actor);
    }

    /** Bewusste erneute Zustellung einer bereits zugestellten Fassung (gleiche Bytes). */
    public function redeliver(ExternalInvoicePackage $package, User $actor): DocumentDispatch {
        if ($this->isPending($package)) {
            throw ValidationException::withMessages(['dispatch' => __('finance.external_invoice.error.in_flight')]);
        }

        return $this->deliver($package, $actor, ['redelivery' => true]);
    }

    /** Ungeklärten Zustellausgang nach Abgleich mit dem Empfänger festhalten. */
    public function resolve(ExternalInvoicePackage $package, bool $delivered, User $actor): void {
        $latest = $package->latestDispatch();
        if ($latest === null || ! $this->isUnclear($package)) {
            throw ValidationException::withMessages(['dispatch' => __('finance.external_invoice.error.not_unclear')]);
        }
        $latest->forceFill([
            'status' => $delivered ? DocumentDispatchStatus::Sent : DocumentDispatchStatus::Failed,
            'meta' => [...(array) $latest->meta, 'resolved_by' => $actor->id, 'resolved_at' => now()->toIso8601String(), 'resolution' => $delivered ? 'delivered' : 'not_delivered'],
        ])->save();
        $package->externalInvoice->audit('externalInvoice.deliveryResolved', ['package_id' => $package->id, 'delivered' => $delivered]);
    }

    /** Läuft ein Versuch noch oder ist sein Ausgang ungeklärt? */
    public function isPending(ExternalInvoicePackage $package): bool {
        return $package->latestDispatch()?->status === DocumentDispatchStatus::Queued;
    }

    public function isUnclear(ExternalInvoicePackage $package): bool {
        $latest = $package->latestDispatch();
        if ($latest === null || $latest->status !== DocumentDispatchStatus::Queued) {
            return false;
        }

        return (($latest->meta ?? [])['outcome'] ?? null) === ExternalInvoicePackage::OUTCOME_UNCLEAR
            || $latest->created_at?->lt(now()->subMinutes(self::UNCLEAR_AFTER_MINUTES)) === true;
    }

    /** @return list<string> */
    private function statusErrors(ExternalInvoice $invoice): array {
        return match ($invoice->status) {
            ExternalInvoiceStatus::Draft => [(string) __('finance.external_invoice.error.not_finalized')],
            ExternalInvoiceStatus::Voided => [(string) __('finance.external_invoice.error.voided')],
            ExternalInvoiceStatus::Unknown => [(string) __('finance.external_invoice.error.status_unknown')],
            default => [],
        };
    }

    private function ensureActivityReport(ExternalInvoice $invoice, User $actor): void {
        $documents = $invoice->supportingDocuments()->get();
        $hasOpenGenerated = $documents->contains(static fn (InvoiceSupportingDocument $d): bool => $d->kind->isGenerated() && ! $d->isFrozen());
        if ($hasOpenGenerated || ($documents->isEmpty() && $this->profiles->attachesActivityReport($invoice->customer, $invoice->organization))) {
            $this->documents->generateActivityReport($invoice, $actor);
        }
    }

    /** @param  array<string, mixed>  $extra */
    private function deliver(ExternalInvoicePackage $package, User $actor, array $extra = []): DocumentDispatch {
        $invoice = $package->externalInvoice()->with(['customer', 'organization'])->firstOrFail();

        if ($package->channel === DocumentDispatch::CHANNEL_PEPPOL) {
            $file = $this->packager->files($package)[0];
            if ($invoice->customer === null) {
                throw ValidationException::withMessages(['dispatch' => __('finance.external_invoice.error.peppol_participant')]);
            }
            try {
                $dispatch = $this->peppol->sendUbl((int) $invoice->organization_id, $invoice->customer, $file['content'], [
                    'document_kind' => ExternalInvoicePackage::DOCUMENT_KIND,
                    'document_id' => $package->id,
                    'format' => $package->format->value,
                    'limit' => $this->profiles->limitBytes($invoice->customer, $invoice->organization),
                ]);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['dispatch' => $e->getMessage()]);
            }
        } else {
            $dispatch = $this->mail($package, $invoice, $actor, $extra);
        }

        $invoice->audit('externalInvoice.sent', [
            'package_id' => $package->id,
            'dispatch_id' => $dispatch->id,
            'channel' => $package->channel,
            'recipient' => $package->recipient,
            'sha256' => $package->sha256,
            ...$extra,
        ]);

        return $dispatch;
    }

    /** @param  array<string, mixed>  $extra */
    private function mail(ExternalInvoicePackage $package, ExternalInvoice $invoice, User $actor, array $extra): DocumentDispatch {
        $envelope = (array) ($package->report['mail'] ?? []);
        $template = isset($envelope['template_id'])
            ? InvoiceMailTemplate::query()->whereKey($envelope['template_id'])->first()
            : null;
        $template ??= InvoiceMailTemplate::defaultFor((int) $invoice->organization_id);
        $customText = $envelope['custom_text'] ?? ($package->kind === ExternalPackageKind::Resend
            ? DocumentLocale::within($invoice->customer, $invoice->organization, static fn (): string => (string) __('finance.external_invoice.mail.resend_text', ['number' => $invoice->displayNumber()]))
            : null);
        $rendered = DocumentLocale::within($invoice->customer, $invoice->organization, fn (): array => $template->render($this->variables($invoice, is_string($customText) ? $customText : null)));
        $to = array_values(array_filter(array_map('trim', explode(',', $package->recipient))));

        $dispatch = DocumentDispatch::query()->create([
            'organization_id' => $invoice->organization_id,
            'document_kind' => ExternalInvoicePackage::DOCUMENT_KIND,
            'document_id' => $package->id,
            'channel' => DocumentDispatch::CHANNEL_EMAIL,
            'format' => $package->format->value,
            'status' => DocumentDispatchStatus::Queued,
            'recipient' => $package->recipient,
            'sha256' => $package->sha256,
            'meta' => array_filter(['cc' => $envelope['cc'] ?? [], 'template_id' => $template->id, 'package_kind' => $package->kind->value, ...$extra]),
            'created_by' => $actor->id,
        ]);

        $pending = Mail::to($to);
        if (! empty($envelope['cc'])) {
            $pending->cc($envelope['cc']);
        }
        if (! empty($envelope['bcc'])) {
            $pending->bcc($envelope['bcc']);
        }
        $pending->queue(new ExternalInvoiceMail($package, $rendered['subject'], $rendered['html'], $rendered['text'], (int) $dispatch->id, $invoice->customer));

        return $dispatch;
    }

    /** @return array<string, string> dieselben Platzhalter wie beim Rechnungsversand */
    private function variables(ExternalInvoice $invoice, ?string $customText): array {
        $number = $invoice->displayNumber();
        $date = $invoice->issued_on?->format('d.m.Y') ?? '';
        $label = (string) __($invoice->isCreditNote() ? 'invoicing.activity_report.credit_note' : 'invoicing.activity_report.invoice');

        return [
            'customer_name' => (string) ($invoice->customer->name ?? ''),
            'customer_email' => (string) ($invoice->customer->email ?? ''),
            'invoice_number' => $number,
            'invoice_date' => $date,
            'document_number' => $number,
            'document_date' => $date,
            'due_date' => '',
            'total' => $invoice->gross_amount !== null ? \CommonToolkit\ValueObjects\Decimal::of((string) $invoice->gross_amount, 2)->format() : '',
            'currency' => $invoice->currency,
            'company_name' => (string) (config('branding.app_name') ?: config('app.name', 'workDiary')),
            'document_label' => $label,
            'custom_text' => (string) ($customText ?? ''),
            'payment_link' => '',
        ];
    }
}
