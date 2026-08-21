<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRunService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Sepa;

use App\Enums\Document\DocumentType;
use App\Enums\Finance\{PaymentRunKind, PaymentRunStatus};
use App\Models\Finance\{BankAccount, PaymentRun, PaymentRunItem, SepaMandate};
use App\Models\{IncomingEInvoice, User};
use App\Services\Document\DocumentService;
use App\Services\Finance\FinancialFormatsSupport;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Zahllauf-Domäne (Feature 120, MVP-609).
 *
 * Drei Schritte, drei Zustände: zusammenstellen (Entwurf), freigeben
 * (Vier-Augen-Prinzip), exportieren (Datei). Der Export ist idempotent — die
 * einmal erzeugte Datei bleibt die Wahrheit, ein zweiter Abruf liefert
 * dieselbe zurück und nicht eine neue mit anderer Message-ID.
 */
class PaymentRunService {
    public function __construct(
        private readonly PaymentProposalService $proposals,
        private readonly DocumentService $documents,
        private readonly SepaFileBuilder $files,
    ) {}

    /**
     * Zahllauf aus ausgewählten Eingangsrechnungen erzeugen.
     *
     * @param list<int> $incomingIds
     */
    public function createFromProposals(
        BankAccount $account,
        User $actor,
        array $incomingIds,
        ?CarbonImmutable $executionDate = null,
        ?string $label = null,
    ): PaymentRun {
        if ($incomingIds === []) {
            throw new RuntimeException((string) __('sepa.error.no_positions'));
        }

        return DB::transaction(function () use ($account, $actor, $incomingIds, $executionDate, $label): PaymentRun {
            $run = PaymentRun::query()->create([
                'organization_id' => $account->organization_id,
                'bank_account_id' => $account->id,
                'kind' => PaymentRunKind::CreditTransfer->value,
                'status' => PaymentRunStatus::Draft->value,
                'label' => $label,
                'execution_date' => $executionDate ?? CarbonImmutable::today(),
                'created_by' => $actor->id,
            ]);

            $invoices = IncomingEInvoice::query()
                ->whereIn('id', $incomingIds)
                ->whereNull('paid_in_run_id')
                ->get();

            foreach ($invoices as $invoice) {
                $proposal = $this->proposals->proposalFor($invoice);
                if ($proposal['blocked'] !== null) {
                    // Eine Position ohne IBAN würde die ganze Datei unbrauchbar
                    // machen — lieber sie gar nicht erst aufnehmen.
                    continue;
                }

                PaymentRunItem::query()->create([
                    'organization_id' => $run->organization_id,
                    'payment_run_id' => $run->id,
                    'incoming_einvoice_id' => $invoice->id,
                    'supplier_id' => $proposal['supplier']?->id,
                    'party_name' => mb_substr((string) ($invoice->seller_name ?? '—'), 0, 70),
                    'iban' => $proposal['iban'],
                    'bic' => $proposal['bic'],
                    'amount' => $proposal['amount'],
                    'gross_amount' => $proposal['gross'],
                    'discount_percent' => $proposal['discount_percent'],
                    'reference' => $this->reference($invoice),
                    'end_to_end_id' => $this->endToEndId($invoice),
                ]);

                $invoice->forceFill(['paid_in_run_id' => $run->id])->save();
            }

            return $this->recalculate($run);
        });
    }

    /** Lastschrifteinzug aus aktiven Mandaten (pain.008). */
    public function createDirectDebit(
        BankAccount $account,
        User $actor,
        SepaMandate $mandate,
        float $amount,
        string $reference,
        ?CarbonImmutable $executionDate = null,
    ): PaymentRun {
        if (! $mandate->isUsable()) {
            throw new RuntimeException((string) __('sepa.error.mandate_unusable'));
        }
        if ($amount <= 0) {
            throw new RuntimeException((string) __('sepa.error.zero_amount'));
        }

        return DB::transaction(function () use ($account, $actor, $mandate, $amount, $reference, $executionDate): PaymentRun {
            $run = PaymentRun::query()->create([
                'organization_id' => $account->organization_id,
                'bank_account_id' => $account->id,
                'kind' => PaymentRunKind::DirectDebit->value,
                'status' => PaymentRunStatus::Draft->value,
                'execution_date' => $executionDate ?? $this->earliestCollection($mandate),
                'created_by' => $actor->id,
            ]);

            PaymentRunItem::query()->create([
                'organization_id' => $run->organization_id,
                'payment_run_id' => $run->id,
                'customer_id' => $mandate->customer_id,
                'sepa_mandate_id' => $mandate->id,
                'party_name' => mb_substr((string) ($mandate->customer->name ?? '—'), 0, 70),
                'iban' => $mandate->iban,
                'bic' => $mandate->bic,
                'amount' => round($amount, 2),
                'reference' => mb_substr($reference, 0, 140),
                'end_to_end_id' => mb_substr($mandate->reference, 0, 35),
            ]);

            return $this->recalculate($run);
        });
    }

    /**
     * Vorlauffrist: Erstlastschrift fünf, Folgelastschrift zwei Bankarbeitstage.
     * Bewusst konservativ — eine zu früh datierte Datei weist die Bank zurück.
     */
    public function earliestCollection(SepaMandate $mandate, ?CarbonImmutable $today = null): CarbonImmutable {
        $today = $today ?? CarbonImmutable::today();

        return $today->addWeekdays($mandate->isFirstCollection() ? 5 : 2);
    }

    /** Position aus dem Entwurf nehmen — die Rechnung wird wieder zahlbar. */
    public function removeItem(PaymentRunItem $item): PaymentRun {
        $run = $item->run;
        if ($run === null || ! $run->isDraft()) {
            throw new RuntimeException((string) __('sepa.error.not_draft'));
        }

        DB::transaction(function () use ($item): void {
            $item->incomingEInvoice?->forceFill(['paid_in_run_id' => null])->save();
            $item->delete();
        });

        return $this->recalculate($run->refresh());
    }

    /**
     * Kürzung/Teilzahlung: Der Zahlbetrag darf unter den Rechnungsbetrag, aber
     * nie darüber — und nur mit Grund, sonst steht später eine Differenz da,
     * die niemand erklären kann.
     */
    public function adjustItem(PaymentRunItem $item, float $amount, ?string $reason): PaymentRunItem {
        $run = $item->run;
        if ($run === null || ! $run->isDraft()) {
            throw new RuntimeException((string) __('sepa.error.not_draft'));
        }
        $gross = (float) ($item->gross_amount ?? $item->amount);
        if ($amount <= 0 || $amount > $gross) {
            throw new RuntimeException((string) __('sepa.error.invalid_amount'));
        }
        if ($amount < $gross && trim((string) $reason) === '') {
            throw new RuntimeException((string) __('sepa.error.reason_required'));
        }

        $item->forceFill([
            'amount' => round($amount, 2),
            'deduction_reason' => $amount < $gross ? trim((string) $reason) : null,
        ])->save();

        $this->recalculate($run->refresh());

        return $item->refresh();
    }

    /** Freigabe als eigener Schritt — wer zusammenstellt, gibt nicht zwingend frei. */
    public function release(PaymentRun $run, User $actor): PaymentRun {
        if (! $run->isDraft()) {
            throw new RuntimeException((string) __('sepa.error.not_draft'));
        }
        if ($run->items()->count() === 0) {
            throw new RuntimeException((string) __('sepa.error.no_positions'));
        }

        $run->forceFill([
            'status' => PaymentRunStatus::Released->value,
            'released_by' => $actor->id,
            'released_at' => CarbonImmutable::now(),
        ])->save();

        $run->audit('paymentRun.released', ['positions' => $run->items()->count(), 'total' => (string) $run->total]);

        return $run->refresh();
    }

    /**
     * Datei erzeugen und archivieren. Beim zweiten Aufruf kommt die
     * archivierte Datei zurück — GoBD: eine Datei, ein Hash, ein Vorgang.
     */
    public function export(PaymentRun $run, User $actor): string {
        FinancialFormatsSupport::ensureAvailable();

        if ($run->isExported()) {
            $archived = $this->archivedContents($run);
            if ($archived !== null) {
                return $archived;
            }
        }
        if (! $run->isReleased() && ! $run->isExported()) {
            throw new RuntimeException((string) __('sepa.error.not_released'));
        }

        $messageId = $run->message_id ?? $this->messageId($run);
        $xml = $this->files->build($run, $messageId);

        $document = $this->documents->createFromContents(
            $run->bankAccount,
            $actor,
            [
                'title' => $this->fileName($run, $messageId),
                'document_type' => DocumentType::Other->value,
                'description' => (string) __('sepa.document_description', ['id' => $messageId]),
                'confidential' => true,
            ],
            $xml,
            $this->fileName($run, $messageId) . '.xml',
            'application/xml',
        );

        $run->forceFill([
            'status' => PaymentRunStatus::Exported->value,
            'message_id' => $messageId,
            'exported_at' => CarbonImmutable::now(),
            'document_id' => $document->id,
            'file_sha256' => CryptoHelper::hash($xml),
        ])->save();

        $run->audit('paymentRun.exported', ['message_id' => $messageId, 'sha256' => $run->file_sha256]);

        return $xml;
    }

    /** Storno vor der Freigabe: gibt alle Rechnungen wieder frei. */
    public function cancel(PaymentRun $run): PaymentRun {
        if ($run->isExported()) {
            throw new RuntimeException((string) __('sepa.error.exported_final'));
        }

        DB::transaction(function () use ($run): void {
            foreach ($run->items as $item) {
                $item->incomingEInvoice?->forceFill(['paid_in_run_id' => null])->save();
            }
            $run->forceFill(['status' => PaymentRunStatus::Cancelled->value])->save();
        });

        return $run->refresh();
    }

    public function recalculate(PaymentRun $run): PaymentRun {
        $run->forceFill(['total' => round((float) $run->items()->sum('amount'), 2)])->save();

        return $run->refresh();
    }

    private function archivedContents(PaymentRun $run): ?string {
        $version = $run->document?->currentVersion;
        if ($version === null) {
            return null;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk((string) $version->disk);

        return $disk->exists((string) $version->path) ? (string) $disk->get((string) $version->path) : null;
    }

    private function messageId(PaymentRun $run): string {
        $prefix = $run->kind === PaymentRunKind::DirectDebit ? 'DD' : 'CT';

        return mb_substr(sprintf('%s-%s-%d', $prefix, CarbonImmutable::now()->format('YmdHis'), $run->id), 0, 35);
    }

    private function fileName(PaymentRun $run, string $messageId): string {
        return sprintf(
            '%s_%s',
            $run->kind === PaymentRunKind::DirectDebit ? 'pain008' : 'pain001',
            $messageId,
        );
    }

    /** Verwendungszweck: Rechnungsnummer zuerst — daran erkennt sie der Empfänger. */
    private function reference(IncomingEInvoice $invoice): string {
        $number = trim((string) ($invoice->invoice_number ?? ''));

        return mb_substr($number !== '' ? (string) __('sepa.reference', ['number' => $number]) : (string) __('sepa.reference_unknown'), 0, 140);
    }

    private function endToEndId(IncomingEInvoice $invoice): string {
        $number = trim((string) ($invoice->invoice_number ?? ''));

        return mb_substr($number !== '' ? $number : 'NOTPROVIDED', 0, 35);
    }
}
