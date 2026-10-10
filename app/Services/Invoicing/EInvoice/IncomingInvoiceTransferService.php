<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTransferService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceTransferStatus};
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\{Organization, PluginError, User};
use App\Modules\ModuleRegistry;
use App\Plugins\PluginErrorRecorder;
use App\Services\Invoicing\Contracts\IncomingInvoiceTransferTarget;
use App\Support\ErrorText;
use Illuminate\Support\Facades\{Cache, Log};
use Throwable;

/**
 * Übergabe von Rechnungseingängen an die eingeschalteten Buchhaltungsziele
 * (Feature 163, MVP-1111): eine Journalzeile je Eingang und Ziel, das Tor
 * entscheidet für alle Ziele gleich. Automatische Wiederholungen enden nach
 * fünf Fehlversuchen; der Knopf auf der Detailseite versucht es erneut.
 */
class IncomingInvoiceTransferService {
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly IncomingInvoiceTransferGate $gate,
        private readonly ModuleRegistry $modules,
    ) {}

    /** @return list<IncomingInvoiceTransferTarget> eingeschaltete Ziele der Organisation */
    public function targets(Organization $organization): array {
        $targets = [];
        foreach ($this->modules->extensions(IncomingInvoiceTransferTarget::class) as $class) {
            $target = app($class);
            if ($target instanceof IncomingInvoiceTransferTarget && $target->isEnabled($organization)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    public function hasTargets(Organization $organization): bool {
        return $this->targets($organization) !== [];
    }

    /**
     * Übergibt an alle zutreffenden Ziele (bzw. nur an `$onlyTarget`). Mit
     * `$actor` ist es ein bewusster Anstoß und überspringt die Versuchsgrenze.
     *
     * @return list<IncomingEInvoiceTransfer>
     */
    public function transfer(IncomingEInvoice $incoming, ?User $actor = null, ?string $onlyTarget = null): array {
        $organization = $incoming->organization;
        if ($organization === null) {
            return [];
        }
        $targets = array_values(array_filter(
            $this->targets($organization),
            static fn (IncomingInvoiceTransferTarget $target): bool => ($onlyTarget === null || $target->key() === $onlyTarget) && $target->appliesTo($incoming),
        ));
        if ($targets === []) {
            return [];
        }

        // Ein Beleg im Zielsystem ist nicht mehr löschbar: nie zwei Läufe für denselben Eingang.
        $lock = Cache::lock('incoming-invoice-transfer:' . $incoming->id, 300);
        if (! $lock->get()) {
            return [];
        }
        try {
            $blockers = $this->gate->blockers($incoming);
            $journals = [];
            foreach ($targets as $target) {
                $journals[] = $this->transferTo($incoming, $target, $blockers, $actor);
            }

            return $journals;
        } finally {
            $lock->release();
        }
    }

    /**
     * Offene, wartende und fehlgeschlagene Übergaben erneut anstoßen
     * (Befehl `incoming-invoices:transfer`, „Jetzt hochladen“ eines Ziels).
     *
     * @return array{transferred: int, failed: int} abgeschlossen bzw. fehlgeschlagen; Wartende zählen nicht
     */
    public function retryOpen(Organization $organization, ?string $onlyTarget = null): array {
        $counts = ['transferred' => 0, 'failed' => 0];
        foreach ($this->targets($organization) as $target) {
            if ($onlyTarget !== null && $target->key() !== $onlyTarget) {
                continue;
            }
            IncomingEInvoice::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where(static fn ($party) => $party->whereNotNull('supplier_id')->orWhereNotNull('customer_id'))
                ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value)
                ->whereNotExists(static fn ($done) => $done->selectRaw('1')->from('incoming_einvoice_transfers')
                    ->whereColumn('incoming_einvoice_transfers.incoming_einvoice_id', 'incoming_einvoices.id')
                    ->where('incoming_einvoice_transfers.target', $target->key())
                    ->where(static fn ($final) => $final->whereIn('incoming_einvoice_transfers.status', [IncomingInvoiceTransferStatus::Transferred->value, IncomingInvoiceTransferStatus::Linked->value])
                        ->orWhere('incoming_einvoice_transfers.attempts', '>=', self::MAX_ATTEMPTS)))
                ->orderBy('id')
                ->chunkById(100, function ($incomings) use ($target, &$counts): void {
                    foreach ($incomings as $incoming) {
                        foreach ($this->transfer($incoming, null, $target->key()) as $journal) {
                            if ($journal->status->isFinal()) {
                                $counts['transferred']++;
                            } elseif ($journal->status === IncomingInvoiceTransferStatus::Failed) {
                                $counts['failed']++;
                            }
                        }
                    }
                });
        }

        return $counts;
    }

    /** @param  list<string>  $blockers */
    private function transferTo(IncomingEInvoice $incoming, IncomingInvoiceTransferTarget $target, array $blockers, ?User $actor): IncomingEInvoiceTransfer {
        $journal = IncomingEInvoiceTransfer::query()->withoutGlobalScopes()->firstOrCreate(
            ['incoming_einvoice_id' => $incoming->id, 'target' => $target->key()],
            ['organization_id' => $incoming->organization_id],
        );
        if ($journal->status->isFinal()) {
            return $journal;
        }
        if ($blockers !== []) {
            $journal->forceFill(['status' => IncomingInvoiceTransferStatus::Waiting, 'error' => self::clip(implode(' ', $blockers))])->save();

            return $journal;
        }
        if ($actor === null && $journal->status === IncomingInvoiceTransferStatus::Failed && $journal->attempts >= self::MAX_ATTEMPTS) {
            return $journal;
        }

        $journal->forceFill(['attempts' => $journal->attempts + 1])->save();
        try {
            $result = $target->transfer($incoming, $journal);
            $journal->forceFill([
                'status' => $result->status,
                'external_id' => $result->externalId ?? $journal->external_id,
                'external_number' => $result->externalNumber ?? $journal->external_number,
                'error' => $result->note !== null ? self::clip($result->note) : null,
                'transferred_at' => $result->status->isFinal() ? now() : null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Übergabe eines Rechnungseingangs fehlgeschlagen', ['incoming' => $incoming->id, 'target' => $target->key(), 'exception' => $e::class]);
            $journal->forceFill(['status' => IncomingInvoiceTransferStatus::Failed, 'error' => self::clip(ErrorText::for($e))])->save();
            // Erst der letzte automatische Versuch geht in die Fehler-Inbox; vorher zählte jeder Ausrutscher zur Abschaltung.
            if ($journal->attempts === self::MAX_ATTEMPTS) {
                try {
                    app(PluginErrorRecorder::class)->record($target->key(), PluginError::PHASE_RUNTIME, $e, ['incoming_einvoice_id' => $incoming->id], (int) $incoming->organization_id);
                } catch (Throwable $recorderFailure) {
                    Log::warning('Plugin-Fehler konnte nicht protokolliert werden.', ['error' => $recorderFailure->getMessage()]);
                }
            }

            return $journal;
        }

        if ($journal->status->isFinal()) {
            if ($incoming->transferred_at === null) {
                $incoming->forceFill(['transferred_at' => now(), 'transferred_by' => $actor?->id])->save();
            }
            $incoming->audit('incoming_einvoice.transferred', [
                'target' => $journal->target,
                'status' => $journal->status->value,
                'external_id' => $journal->external_id,
                'sha256' => $incoming->sha256,
            ]);
        }

        return $journal;
    }

    private static function clip(string $text): string {
        return mb_substr($text, 0, 500);
    }
}
