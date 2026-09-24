<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMailService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Mail\DocumentMail;
use App\Models\Contracts\AuditsChanges;
use App\Models\Document\DocumentDispatch;
use App\Models\Invoicing\InvoiceMailTemplate;
use App\Modules\ModuleRegistry;
use App\Services\Document\Contracts\MailableDocumentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Auth, Mail};
use InvalidArgumentException;

/**
 * Generischer Belegversand per E-Mail (Feature 128, MVP-692): EIN Weg für
 * Angebot, Auftragsbestätigung, Bestellung, Lieferschein und die VOB/B-
 * Schreiben (Feature 062, MVP-728) — Vorlage
 * auflösen, Platzhalter rendern, PDF als Anhang queuen, Zustellversuch
 * in document_dispatches protokollieren, Audit `{kind}.mailed` am Beleg.
 *
 * Die Rechnung behält bewusst ihren eigenen Versandpfad
 * ({@see \App\Http\Controllers\Invoicing\InvoiceController::send()}): E-Rechnungs-
 * Formate, Ausstellungs-Preflight und markSent() gehören zur
 * Rechnungs-Domäne — beide Pfade schreiben aber dasselbe Dispatch-Log.
 *
 * Belegart-Wissen (PDF, Dateiname, Platzhalter, Empfänger) liefern die Module
 * über {@see MailableDocumentProvider}; dieser Dienst kennt kein Fachmodul.
 */
class DocumentMailService {
    /** @var array<string, MailableDocumentProvider>|null Belegart → Anbieter, lazy aus den Manifesten */
    private ?array $providers = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** Vom generischen Versand unterstützte Belegarten — was die Module über {@see MailableDocumentProvider} anbieten (MVP-863). */
    public function supportsKind(RenderDocumentKind $kind): bool {
        return isset($this->providers()[$kind->value]);
    }

    /**
     * Versendet den Beleg als PDF-Mail (queued) und protokolliert den
     * Zustellversuch. Empfänger sind vorvalidiert (Controller).
     *
     * @param  array{to: list<string>, cc?: list<string>, bcc?: list<string>}  $recipients
     */
    public function send(
        Model $document,
        RenderDocumentKind $kind,
        array $recipients,
        ?InvoiceMailTemplate $template = null,
        ?string $customText = null,
        bool $bccSender = false,
    ): DocumentDispatch {
        $this->assertSendable($document, $kind);
        if (! $document instanceof AuditsChanges) {
            throw new InvalidArgumentException('Beleg ohne Audit-Spur: ' . $document::class);
        }
        $provider = $this->providerFor($kind);

        $organizationId = (int) $document->getAttribute('organization_id');
        $template ??= InvoiceMailTemplate::defaultFor($organizationId, $kind);
        // Belegsprache je Kunde (Feature 034, MVP-721): Platzhalter wie die
        // Belegart-Bezeichnung in der Sprache des Empfängers.
        $rendered = \App\Support\DocumentLocale::within(
            $provider->localeCustomer($document, $kind),
            null,
            fn (): array => $template->render($this->variablesFor($document, $kind, $customText)),
        );

        $bcc = $recipients['bcc'] ?? [];
        if ($bccSender) {
            $senderAddr = (string) config('mail.from.address');
            if ($senderAddr !== '' && ! in_array($senderAddr, $bcc, true)) {
                $bcc[] = $senderAddr;
            }
        }

        // Dispatch VOR dem Queuen (Vollaudit 2026-07, M26) — Status,
        // Message-ID und Dateihash schreibt der Versandpfad nach.
        $dispatch = DocumentDispatch::query()->create([
            'organization_id' => $organizationId,
            'document_kind' => $kind->value,
            'document_id' => (int) $document->getKey(),
            'channel' => DocumentDispatch::CHANNEL_EMAIL,
            'format' => 'pdf',
            'status' => 'queued',
            'recipient' => implode(', ', $recipients['to']),
            'meta' => array_filter([
                'cc' => $recipients['cc'] ?? [],
                'template_id' => $template->id,
            ]),
            'created_by' => Auth::id(),
        ]);

        $mail = new DocumentMail($document, $kind->value, $rendered['subject'], $rendered['html'], $rendered['text'], (int) $dispatch->id, $provider->localeCustomer($document, $kind));
        $pending = Mail::to($recipients['to']);
        if (! empty($recipients['cc'])) {
            $pending->cc($recipients['cc']);
        }
        if ($bcc !== []) {
            $pending->bcc($bcc);
        }
        $pending->queue($mail);

        $document->audit($kind->value . '.mailed', [
            'to' => $recipients['to'],
            'dispatch_id' => $dispatch->id,
            'template_id' => $template->id,
        ]);

        $provider->afterSent($document, $kind);

        return $dispatch;
    }

    /** PDF-Bytes des Belegs — exakt der Renderer des Downloads. */
    public function pdfBytes(Model $document, RenderDocumentKind $kind): string {
        $this->assertSendable($document, $kind);

        return $this->providerFor($kind)->pdfBytes($document, $kind);
    }

    /** Anhang-Dateiname — deckungsgleich mit dem jeweiligen Download. */
    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string {
        return $this->providerFor($kind)->attachmentFilename($document, $kind);
    }

    /** Belegnummer für Dialogtitel. */
    public function documentNumber(Model $document, RenderDocumentKind $kind): string {
        return $this->providerFor($kind)->documentNumber($document, $kind);
    }

    /**
     * Platzhalter-Werte der Belegart ({@see InvoiceMailTemplate::availableVariables()}).
     *
     * @return array<string, string>
     */
    public function variablesFor(Model $document, RenderDocumentKind $kind, ?string $customText = null): array {
        $companyName = (string) (config('branding.app_name') ?: config('app.name', 'workDiary'));
        $common = [
            'company_name' => $companyName,
            'document_label' => $kind->label(),
            'custom_text' => (string) ($customText ?? ''),
        ];

        return $common + $this->providerFor($kind)->variables($document, $kind);
    }

    /** Empfänger-Vorbelegung: primäre E-Mail von Kunde bzw. Lieferant. */
    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string {
        return $this->providerFor($kind)->defaultRecipient($document, $kind);
    }

    /** Belegart unterstützt + Modellklasse passt — sonst InvalidArgumentException. */
    public function assertSendable(Model $document, RenderDocumentKind $kind): void {
        $expected = $this->supportsKind($kind) ? $this->providerFor($kind)->modelClass($kind) : null;
        if ($expected === null || $document::class !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'Belegart %s erwartet %s, %s übergeben.',
                $kind->value,
                (string) $expected,
                $document::class,
            ));
        }
    }

    private function providerFor(RenderDocumentKind $kind): MailableDocumentProvider {
        return $this->providers()[$kind->value]
            ?? throw new InvalidArgumentException('Belegart ohne generischen Versand: ' . $kind->value);
    }

    /** @return array<string, MailableDocumentProvider> */
    private function providers(): array {
        if ($this->providers === null) {
            $this->providers = [];
            foreach ($this->modules->extensions(MailableDocumentProvider::class) as $class) {
                /** @var MailableDocumentProvider $provider */
                $provider = app($class);
                foreach ($provider->kinds() as $kind) {
                    $this->providers[$kind->value] = $provider;
                }
            }
        }

        return $this->providers;
    }
}
