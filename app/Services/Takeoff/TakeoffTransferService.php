<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffTransferService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Takeoff;

use App\Enums\Takeoff\{TakeoffStatus, TakeoffTransferKind};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Sales\Quote;
use App\Models\Takeoff\{Takeoff, TakeoffTransfer};
use App\Services\Document\DocumentService;
use App\Services\Invoicing\{InvoiceGenerator, QuoteService};
use App\Services\Takeoff\Contracts\TakeoffProgressRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengen eines abgeschlossenen Aufmaßes übernehmen (MVP-1059): als Positionen
 * eines Angebots- oder Rechnungsentwurfs oder als Leistungsstand der
 * LV-Positionen. Jede Art einmal je Blatt — eine zweite Übernahme würde Mengen
 * doppelt stellen. Die Rechnung bekommt das Aufmaß-PDF als Dokument.
 */
final class TakeoffTransferService {
    public function __construct(
        private readonly TakeoffService $takeoffs,
        private readonly QuoteService $quotes,
        private readonly InvoiceGenerator $invoices,
        private readonly TakeoffProgressRecorder $progress,
        private readonly TakeoffPdfRenderer $pdf,
        private readonly DocumentService $documents,
    ) {}

    public function toQuote(Takeoff $takeoff, User $actor): Quote {
        $this->assertTransferable($takeoff, TakeoffTransferKind::Quote);
        [$customer, $project] = $this->customerOf($takeoff);

        return DB::transaction(function () use ($takeoff, $actor, $customer, $project): Quote {
            $quote = $this->quotes->create(
                ['customer_id' => $customer->id, 'project_id' => $project?->id, 'terms' => (string) __('takeoff.transfer.based_on', ['title' => $takeoff->title])],
                array_map(fn (array $line): array => [...$line, 'unit_price' => $line['unit_price'] ?? '0'], $this->lines($takeoff)),
                $actor,
            );
            $this->record($takeoff, TakeoffTransferKind::Quote, $quote, $actor);

            return $quote;
        });
    }

    public function toInvoice(Takeoff $takeoff, User $actor): Invoice {
        $this->assertTransferable($takeoff, TakeoffTransferKind::Invoice);
        [$customer, $project] = $this->customerOf($takeoff);

        return DB::transaction(function () use ($takeoff, $actor, $customer, $project): Invoice {
            $invoice = $this->invoices->emptyDraft($customer, $project);
            $position = 0;
            foreach ($this->lines($takeoff) as $line) {
                $invoice->items()->create([
                    ...$line,
                    'organization_id' => $invoice->organization_id,
                    'unit' => $line['unit'] ?? '',
                    'unit_price' => $line['unit_price'] ?? '0',
                    'position' => ++$position,
                ]);
            }
            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();
            $this->documents->createFromContents($invoice, $actor, [
                'title' => (string) __('takeoff.transfer.document_title', ['title' => $takeoff->title]),
                'document_type' => 'other',
            ], $this->pdf->render($takeoff), $this->pdf->filename($takeoff), 'application/pdf');
            $this->record($takeoff, TakeoffTransferKind::Invoice, $invoice, $actor);

            return $invoice;
        });
    }

    /** @return int Anzahl gemeldeter LV-Positionen */
    public function toProgress(Takeoff $takeoff, User $actor): int {
        $this->assertTransferable($takeoff, TakeoffTransferKind::Progress);
        $groups = array_values(array_filter($this->takeoffs->totals($takeoff), static fn (array $g): bool => $g['boqItem'] !== null));
        if ($groups === []) {
            throw new RuntimeException((string) __('takeoff.transfer.error.no_boq'));
        }

        return DB::transaction(function () use ($takeoff, $actor, $groups): int {
            foreach ($groups as $group) {
                $this->progress->recordMeasured(
                    $group['boqItem'],
                    $group['quantity'],
                    $takeoff->diary_entry_id,
                    (string) __('takeoff.transfer.progress_note', ['title' => $takeoff->title]),
                    $actor->id,
                );
            }
            $this->record($takeoff, TakeoffTransferKind::Progress, null, $actor);

            return count($groups);
        });
    }

    /**
     * Positionen aus den Mengen je Ziel: Artikel mit Verkaufspreis, LV-Position mit Einheitspreis, sonst Text.
     *
     * @return list<array{description: string, quantity: string, unit: ?string, article_id: ?int, unit_price: ?string}>
     */
    private function lines(Takeoff $takeoff): array {
        $lines = [];
        foreach ($this->takeoffs->totals($takeoff) as $group) {
            if ((float) $group['quantity'] <= 0) {
                continue;
            }
            $price = $group['article']->default_sale_price ?? $group['boqItem']->unit_price ?? null;
            $lines[] = [
                'description' => mb_substr($group['label'] !== '' ? $group['label'] : (string) __('takeoff.title'), 0, 500),
                'quantity' => $group['quantity'],
                'unit' => $group['unit'],
                'article_id' => $group['article']?->id,
                'unit_price' => $price?->withScale(2)->getAmount(),
            ];
        }
        if ($lines === []) {
            throw new RuntimeException((string) __('takeoff.transfer.error.empty'));
        }

        return $lines;
    }

    /** @return array{0: Customer, 1: ?Project} */
    private function customerOf(Takeoff $takeoff): array {
        $project = $takeoff->project ?? $takeoff->diaryEntry->project ?? $takeoff->billOfQuantity->project ?? null;
        $customer = $takeoff->diaryEntry->customer ?? $project->customer ?? null;
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __('takeoff.transfer.error.no_customer'));
        }

        return [$customer, $project];
    }

    private function assertTransferable(Takeoff $takeoff, TakeoffTransferKind $kind): void {
        if ($takeoff->status !== TakeoffStatus::Completed) {
            throw new RuntimeException((string) __('takeoff.transfer.error.not_completed'));
        }
        if ($takeoff->transfers()->where('kind', $kind->value)->exists()) {
            throw new RuntimeException((string) __('takeoff.transfer.error.already', ['kind' => $kind->label()]));
        }
    }

    private function record(Takeoff $takeoff, TakeoffTransferKind $kind, ?Model $target, User $actor): void {
        TakeoffTransfer::query()->create([
            'organization_id' => $takeoff->organization_id,
            'takeoff_id' => $takeoff->id,
            'kind' => $kind->value,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'created_by' => $actor->id,
        ]);
        $takeoff->audit('takeoff.transferred', ['kind' => $kind->value]);
    }
}
