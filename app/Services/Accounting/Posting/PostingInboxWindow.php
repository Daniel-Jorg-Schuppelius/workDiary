<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingInboxWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Finance\DatevBookingSource;
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\Organization;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Vorgeladenes für den Buchungs-Eingang, der je Vorgang einen vollständigen
 * Vorschlag baut: Regeln und Profil je Liste einmal, Übergabestand und
 * Vorauszahlungen der Vorgänge gebündelt. Scoped gebunden und nur zwischen
 * {@see open()} und {@see close()} wirksam — wer einen einzelnen Vorgang bucht,
 * liest wie bisher frisch.
 */
final class PostingInboxWindow {
    private bool $open = false;

    /** @var array<string, mixed> */
    private array $memo = [];

    /** @var array<string, bool> "<Morph-Typ>:<ID>" → schon an den Steuerberater übergeben */
    private array $handedOver = [];

    /** @var array<int, Collection<int, OnlinePayment>> Rechnung → bezahlte und erstattete Vorauszahlungen */
    private array $payments = [];

    public function open(): void {
        $this->close();
        $this->open = true;
    }

    public function close(): void {
        $this->open = false;
        $this->memo = [];
        $this->handedOver = [];
        $this->payments = [];
    }

    /**
     * Übergabestand und Vorauszahlungen für die Vorgänge einer Quelle in zwei Abfragen.
     *
     * @param  iterable<Model>  $sources
     */
    public function prime(Organization $organization, iterable $sources): void {
        if (! $this->open) {
            return;
        }
        /** @var array<string, list<int|string>> $byType */
        $byType = [];
        $invoiceIds = [];
        foreach ($sources as $source) {
            $byType[$source->getMorphClass()][] = $source->getKey();
            $this->handedOver[$source->getMorphClass() . ':' . $source->getKey()] = false;
            if ($source instanceof Invoice) {
                $invoiceIds[] = (int) $source->getKey();
                $this->payments[(int) $source->getKey()] = new Collection;
            }
        }
        foreach ($byType as $type => $ids) {
            $rows = DatevBookingSource::query()
                ->where('source_type', $type)
                ->whereIn('source_id', $ids)
                ->whereHas('batch', static fn ($query) => $query->where('status', 'exported'))
                ->toBase()
                ->distinct()
                ->pluck('source_id');
            foreach ($rows as $id) {
                $this->handedOver[$type . ':' . $id] = true;
            }
        }
        if ($invoiceIds !== []) {
            $payments = OnlinePayment::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('invoice_id', $invoiceIds)
                ->whereNotNull('subject_type')
                ->whereIn('status', [OnlinePaymentStatus::Paid->value, OnlinePaymentStatus::Refunded->value])
                ->orderBy('id')
                ->get();
            foreach ($payments as $payment) {
                $this->payments[(int) $payment->invoice_id]->push($payment);
            }
        }
    }

    /** Übergabestand eines Vorgangs; null, wenn er nicht vorgeladen ist. */
    public function handedOver(Model $source): ?bool {
        return $this->handedOver[$source->getMorphClass() . ':' . $source->getKey()] ?? null;
    }

    /**
     * Vorauszahlungen einer Rechnung nach ID; null, wenn sie nicht vorgeladen ist.
     *
     * @return Collection<int, OnlinePayment>|null
     */
    public function payments(Invoice $invoice): ?Collection {
        return $this->payments[(int) $invoice->getKey()] ?? null;
    }

    /**
     * Merkt einen Wert für die Dauer des Fensters; ohne Fenster wird jedes Mal neu geladen.
     *
     * @template T
     *
     * @param  Closure(): T  $load
     * @return T
     */
    public function remember(string $key, Closure $load): mixed {
        if (! $this->open) {
            return $load();
        }
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $load();
        }

        return $this->memo[$key];
    }
}
