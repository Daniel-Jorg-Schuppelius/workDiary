<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxationMethodResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{OpenItemDirection, TaxationMethod};
use App\Models\Accounting\{AccountingOpenItem, AccountingTaxationPeriod};
use App\Models\{Organization, User};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Versteuerungsart je Zeitraum (Feature 125, MVP-679) — EINZIGE Schreibstelle
 * für `accounting_taxation_periods`.
 *
 * Ohne Abschnitt gilt die **Soll-Versteuerung**: Sie ist der gesetzliche
 * Regelfall (§ 16 Abs. 1 UStG), die Ist-Versteuerung braucht eine Genehmigung
 * des Finanzamts (§ 20 UStG). Eine Software, die von sich aus Ist annimmt,
 * würde eine Genehmigung unterstellen, die niemand erteilt hat.
 */
class TaxationMethodResolver {
    public function __construct(private readonly AccountingEventRecorder $events) {}

    public function at(Organization $organization, ?CarbonInterface $date = null): TaxationMethod {
        $period = $this->periodAt($organization, $date);

        return $period instanceof AccountingTaxationPeriod ? $period->method : TaxationMethod::Debit;
    }

    public function periodAt(Organization $organization, ?CarbonInterface $date = null): ?AccountingTaxationPeriod {
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();

        return AccountingTaxationPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('valid_from', '<=', $day->toDateString())
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $day->toDateString());
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Wechselt die Methode ab einem Stichtag.
     *
     * Der Wechsel **blockiert nicht**: § 20 S. 3 UStG verlangt, dass Umsätze
     * nicht doppelt erfasst werden und nicht unversteuert bleiben — das ist
     * eine fachliche Entscheidung. Das Programm hält fest, welche offenen
     * Posten am Stichtag betroffen waren, damit die Steuerberatung sie
     * abarbeiten kann.
     */
    public function switchTo(
        Organization $organization,
        TaxationMethod $method,
        CarbonImmutable $from,
        User $actor,
        ?string $reason = null,
    ): AccountingTaxationPeriod {
        $from = $from->startOfDay();

        if ($this->at($organization, $from) === $method && $this->periodAt($organization, $from) !== null) {
            throw ValidationException::withMessages([
                'method' => (string) __('accounting.taxation.error.unchanged'),
            ]);
        }

        $later = AccountingTaxationPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('valid_from', '>', $from->toDateString())
            ->orderBy('valid_from')
            ->first();

        if ($later instanceof AccountingTaxationPeriod) {
            throw ValidationException::withMessages([
                'valid_from' => (string) __('accounting.taxation.error.later_section', [
                    'date' => $later->valid_from->format(\App\Support\Formats::date()),
                ]),
            ]);
        }

        $changeover = $this->changeoverReport($organization, $from);

        return DB::transaction(function () use ($organization, $method, $from, $actor, $reason, $changeover): AccountingTaxationPeriod {
            AccountingTaxationPeriod::query()
                ->where('organization_id', $organization->id)
                ->whereDate('valid_from', '=', $from->toDateString())
                ->delete();

            AccountingTaxationPeriod::query()
                ->where('organization_id', $organization->id)
                ->whereNull('valid_to')
                ->whereDate('valid_from', '<', $from->toDateString())
                ->update(['valid_to' => $from->subDay()->toDateString()]);

            $period = AccountingTaxationPeriod::query()->create([
                'organization_id' => $organization->id,
                'method' => $method,
                'valid_from' => $from->toDateString(),
                'valid_to' => null,
                'reason' => $reason,
                'changeover' => $changeover,
                'actor_user_id' => $actor->id,
            ]);

            $this->events->record($organization, 'accounting.taxation_method_switched', [
                'method' => $method->value,
                'valid_from' => $from->toDateString(),
                'open_items' => $changeover['count'],
                'open_amount' => $changeover['open_amount'],
            ], null, $actor);

            return $period;
        });
    }

    /**
     * Offene Posten am Stichtag — die Vorgänge, die beim Wechsel fachlich zu
     * beurteilen sind.
     *
     * @return array{count: int, open_amount: string, items: list<array<string, mixed>>}
     */
    public function changeoverReport(Organization $organization, CarbonImmutable $from): array {
        $items = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', OpenItemDirection::Receivable->value)
            ->stillOpen()
            ->whereDate('document_date', '<', $from->toDateString())
            ->orderBy('document_date')
            ->get();

        $total = '0.00';
        $rows = [];
        foreach ($items as $item) {
            $open = $item->open_amount?->getAmount() ?? '0.00';
            $total = number_format((float) $total + (float) $open, 2, '.', '');
            $rows[] = [
                'document' => $item->document_reference,
                'document_date' => $item->document_date->toDateString(),
                'open_amount' => $open,
            ];
        }

        return ['count' => $items->count(), 'open_amount' => $total, 'items' => $rows];
    }
}
