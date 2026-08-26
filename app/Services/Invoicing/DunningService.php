<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Mail\DunningMail;
use App\Models\{CashEntry, DocumentDispatch, Invoice};
use App\Services\Finance\{BillingModeResolver, ReconciliationService};
use App\Support\Setting;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\{Auth, Mail};

/**
 * Vollzug einer Mahnung (Feature 127, MVP-691 — Vollscan H8): einzige
 * Schreibstelle für Einzelmahnung (Dialog) und Mahnlauf (Sammelmahnung).
 *
 * Stufen-Defaults (Karenz/Gebühr/Zahlungsfrist) kommen aus der
 * Org-Konfiguration (`invoicing.dunning.*`); ein Verzugszins > 0 wird
 * taggenau (act/365) auf den offenen Betrag seit Fälligkeit berechnet und im
 * Mahnschreiben/Audit AUSGEWIESEN — es entsteht KEINE Buchung und weiterhin
 * KEIN neuer Beleg (Mahnstatus ist Lifecycle).
 */
final class DunningService {
    public const MAX_LEVEL = 3;

    /** Zinsusance: taggenau auf Basis 365 Tage (act/365). */
    public const INTEREST_DAY_BASIS = 365;

    public function __construct(
        private readonly RetentionService $retentions,
        private readonly ReconciliationService $reconciliation,
        private readonly BillingModeResolver $billingMode,
    ) {}

    /**
     * Stufen-Defaults der Organisation (Karenz seit Fälligkeit bzw. seit der
     * letzten Mahnung, Gebühr in EUR, Zahlungsfrist in Tagen).
     *
     * @return array{grace_days: int, fee: float, pay_days: int}
     */
    public function stepConfig(int $level): array {
        $level = max(1, min(self::MAX_LEVEL, $level));
        $prefix = 'invoicing.dunning.level' . $level . '.';

        return [
            'grace_days' => max(0, (int) Setting::get($prefix . 'grace_days', 7)),
            'fee' => round(max(0.0, (float) Setting::get($prefix . 'fee', 0)), 2),
            'pay_days' => max(0, (int) Setting::get($prefix . 'pay_days', 14)),
        ];
    }

    /** Verzugszins in % p. a. (0 = aus; § 288 BGB als Anhalt, kein Feed). */
    public function interestRate(): float {
        return round(max(0.0, (float) Setting::get('invoicing.dunning.interest_rate', 0)), 2);
    }

    /**
     * Offener Betrag der Rechnung: Zahlbetrag (Summe − offener
     * Sicherheitseinbehalt, {@see RetentionService::payableAmountOf}) minus
     * bekannter Zahlungen aus Bankzuordnung UND Kasse — beide Quellen setzen
     * `partially_paid`, nur eine zu lesen hieße Teilzahlungen mitzumahnen.
     */
    public function openAmount(Invoice $invoice): Money {
        $cash = (float) CashEntry::query()
            ->where('invoice_id', $invoice->id)
            ->where('direction', CashEntry::DIRECTION_IN)
            ->sum('amount');
        $paid = round($this->reconciliation->allocatedSum($invoice) + $cash, 2);
        $open = round($this->retentions->payableAmountOf($invoice) - $paid, 2);

        return Money::ofFloat(max(0.0, $open), $invoice->currencyCode());
    }

    /**
     * Verzugszins-Ausweis: offener Betrag × Satz × Verzugstage / 365
     * (taggenau seit Fälligkeit). Null, wenn kein Satz konfiguriert, kein
     * Verzugstag oder nichts offen ist.
     *
     * @return array{rate: float, days: int, amount: float}|null
     */
    public function interest(Invoice $invoice, ?CarbonInterface $asOf = null): ?array {
        $rate = $this->interestRate();
        if ($rate <= 0.0 || $invoice->due_on === null) {
            return null;
        }

        $asOfDay = CarbonImmutable::parse(($asOf ?? CarbonImmutable::today())->toDateString());
        $days = (int) CarbonImmutable::parse($invoice->due_on->toDateString())->diffInDays($asOfDay, false);
        if ($days <= 0) {
            return null;
        }

        $open = $this->openAmount($invoice);
        if (! $open->isPositive()) {
            return null;
        }

        // Money-Arithmetik: erst multiplizieren, zuletzt teilen — so rundet
        // nur der Endbetrag auf Cent (deterministisch, HalfUp).
        $amount = $open->times($days)->percentage($rate)->dividedBy(self::INTEREST_DAY_BASIS);
        if (! $amount->isPositive()) {
            return null;
        }

        return ['rate' => $rate, 'days' => $days, 'amount' => $amount->toFloat()];
    }

    /** Mahnlauf gilt nur für lokal geführte Rechnungen (Rechnungshoheit, Feature 045). */
    public function isLocallyBilled(Invoice $invoice): bool {
        return ! $this->billingMode->effectiveFor($invoice->customer)->isExternal();
    }

    /**
     * Frühester Termin der nächsten Mahnstufe: Stufe 1 = Fälligkeit + Karenz,
     * Stufen 2/3 = letzte Mahnung + Karenz. Null ohne Fälligkeit oder wenn
     * die Höchststufe erreicht ist.
     */
    public function nextStepDueOn(Invoice $invoice): ?CarbonImmutable {
        if ($invoice->due_on === null || (int) $invoice->dunning_level >= self::MAX_LEVEL) {
            return null;
        }

        $grace = $this->stepConfig((int) $invoice->dunning_level + 1)['grace_days'];
        $base = (int) $invoice->dunning_level === 0 || $invoice->dunned_at === null
            ? CarbonImmutable::parse($invoice->due_on->toDateString())
            : CarbonImmutable::parse($invoice->dunned_at->toDateString());

        return $base->addDays($grace);
    }

    /** Reif für die nächste Stufe (überfällig, Karenz erreicht, nicht gesperrt, Stufe < 3)? */
    public function isReadyForNextStep(Invoice $invoice, ?CarbonInterface $today = null): bool {
        if (! $invoice->isOverdue() || $invoice->isDunningBlocked()) {
            return false;
        }
        $dueOn = $this->nextStepDueOn($invoice);

        return $dueOn !== null
            && $dueOn->lessThanOrEqualTo(CarbonImmutable::parse(($today ?? CarbonImmutable::today())->toDateString()));
    }

    /**
     * Mahnstufe vollziehen: Stufe erhöhen, Audit schreiben, optional
     * Mahnschreiben + Original-Rechnung per Mail (mit Zustellnachweis).
     *
     * `apply_defaults` füllt fehlende Gebühr/Zahlungsfrist aus der
     * Org-Konfiguration (Mahnlauf); der Einzeldialog übergibt seine Werte
     * explizit — eine manuell eingetragene Gebühr bleibt Override.
     *
     * @param array{fee?: float|null, pay_until?: CarbonImmutable|null, note?: string|null,
     *              send_mail?: bool, email?: string|null, apply_defaults?: bool} $options
     * @return array{level: int, interest: array{rate: float, days: int, amount: float}|null, mailed: bool}
     */
    public function dunInvoice(Invoice $invoice, array $options = []): array {
        if (! $invoice->isOverdue()) {
            throw new DunningException(DunningException::REASON_NOT_OVERDUE, (string) __('Nur überfällige Rechnungen können gemahnt werden.'));
        }
        if ((int) $invoice->dunning_level >= self::MAX_LEVEL) {
            throw new DunningException(DunningException::REASON_MAX_LEVEL, (string) __('Höchste Mahnstufe bereits erreicht.'));
        }
        if ($invoice->isDunningBlocked()) {
            throw new DunningException(DunningException::REASON_BLOCKED, (string) __('finance.dunning.error_blocked'));
        }

        $sendMail = (bool) ($options['send_mail'] ?? false);
        $email = trim((string) ($options['email'] ?? ''));
        if ($sendMail && $email === '') {
            throw new DunningException(DunningException::REASON_NO_EMAIL, (string) __('finance.dunning.error_no_email'));
        }

        $newLevel = (int) $invoice->dunning_level + 1;
        $step = $this->stepConfig($newLevel);
        $applyDefaults = (bool) ($options['apply_defaults'] ?? false);

        $fee = isset($options['fee']) ? round((float) $options['fee'], 2) : null;
        if ($fee !== null && $fee <= 0.0) {
            $fee = null;
        }
        if ($fee === null && $applyDefaults && $step['fee'] > 0.0) {
            $fee = $step['fee'];
        }

        $payUntil = $options['pay_until'] ?? null;
        if ($payUntil === null && $applyDefaults) {
            $payUntil = CarbonImmutable::today()->addDays($step['pay_days']);
        }

        $note = trim((string) ($options['note'] ?? ''));
        $note = $note !== '' ? $note : null;
        $interest = $this->interest($invoice);

        $invoice->update(['dunning_level' => $newLevel, 'dunned_at' => now()]);
        $audit = ['level' => $newLevel, 'mailed' => $sendMail, 'fee' => $fee, 'pay_until' => $payUntil?->toDateString()];
        if ($interest !== null) {
            $audit['interest'] = $interest;
        }
        $invoice->audit('invoice.dunned', $audit);

        // Mahn-Mailversand (MVP-163): eigener Zustellversuch — die Rechnung
        // bleibt unverändert (kein neuer Beleg). Dispatch VOR dem Queuen
        // anlegen (Vollaudit 2026-07, M26): Listener schreibt sent/failed.
        if ($sendMail) {
            $meta = ['kind' => 'dunning', 'level' => $newLevel, 'fee' => $fee, 'pay_until' => $payUntil?->toDateString()];
            if ($interest !== null) {
                $meta['interest'] = $interest;
            }
            $dispatch = DocumentDispatch::query()->create([
                'organization_id' => $invoice->organization_id,
                'invoice_id' => $invoice->id,
                'document_kind' => \App\Enums\DocumentDesign\RenderDocumentKind::Dunning->value,
                'document_id' => $invoice->id,
                'channel' => DocumentDispatch::CHANNEL_EMAIL,
                'format' => 'pdf',
                'status' => 'queued',
                'recipient' => $email,
                'sha256' => null,
                'meta' => $meta,
                'created_by' => Auth::id(),
            ]);
            Mail::to($email)->queue(new DunningMail($invoice, $newLevel, $note, (int) $dispatch->id, $fee, $payUntil, $interest));
        }

        return ['level' => $newLevel, 'interest' => $interest, 'mailed' => $sendMail];
    }
}
