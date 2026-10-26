<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseInvoiceLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\License;

use App\Models\Platform\Organization;
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleLicenseProduct};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rechnungsbezug an Stücklizenzen (Feature 185, MVP-1427/1428): Kandidaten
 * für den Verkaufsdialog aus dem Belegspiegel und der Prüffall „verkauft
 * ohne Rechnung“. Die Positionen stammen aus derselben Spiegelquelle wie die
 * Bezüge der Abo-Perioden; ob eine Position zum Produkt passt, entscheidet
 * der Artikel (`article_ref`), sonst die Bezeichnung.
 */
final class LicenseInvoiceLinks {
    /** Verkauf ohne Rechnung gilt ab diesem Alter als Prüffall. */
    public const UNBILLED_DAYS = 30;

    /** Positionen um das Verkaufsdatum herum, die der Dialog anbietet. */
    public const CANDIDATE_WINDOW_DAYS = 60;

    public function __construct(private readonly InvoiceMirror $mirror) {}

    /**
     * Positionen des Spiegels im Fenster um das Verkaufsdatum, als Formular-
     * Optionen: passende zuerst (Artikel bzw. Produktname), dann nach
     * Belegdatum absteigend. Die Auswahl je Kunde trifft der Dialog.
     *
     * @return list<array{key: string, customer_id: int|null, label: string, date: string, voucher: string, match: bool}>
     */
    public function candidates(Organization $organization, ?ResaleLicenseProduct $product, CarbonImmutable $soldOn): array {
        // Alle Positionen, nicht nur Lizenzartikel der Abos: Stückprodukte haben ihren eigenen Artikel.
        $lines = $this->mirror->linesFor($organization, null, $soldOn->subDays(self::CANDIDATE_WINDOW_DAYS), $soldOn->addDays(self::CANDIDATE_WINDOW_DAYS), licenceOnly: false);
        $options = [];
        foreach ($lines as $line) {
            $options[] = [
                'key' => $line->key,
                'customer_id' => $line->recipientCustomerId,
                'label' => trim(($line->voucherNumber ?? '—') . ' · ' . $line->label() . ' × ' . NumberHelper::toGermanFormat($line->quantity, 3, withThousandsSeparator: false, trimTrailingZeros: true)),
                'date' => $line->voucherDate?->toDateString() ?? '',
                'voucher' => (string) ($line->voucherNumber ?? ''),
                'match' => $product !== null && self::matches($line, $product),
            ];
        }
        usort($options, static fn(array $a, array $b): int => ($b['match'] <=> $a['match']) ?: strcmp($b['date'], $a['date']) ?: strcmp($a['label'], $b['label']));

        return $options;
    }

    /** Position gehört zum Lizenzprodukt: gleicher Artikel, sonst Produktname in der Bezeichnung. */
    public static function matches(MirrorLine $line, ResaleLicenseProduct $product): bool {
        $article = trim((string) $product->article_ref);
        if ($article !== '' && $line->articleKey !== null && trim($line->articleKey) !== '') {
            return strcasecmp($article, trim($line->articleKey)) === 0;
        }

        return mb_stripos($line->label(), $product->name) !== false || mb_stripos($line->text(), $product->name) !== false;
    }

    /**
     * Laufende Verkäufe ohne Rechnungsbezug, älter als {@see UNBILLED_DAYS}.
     *
     * @return Builder<ResaleLicenseAssignment>
     */
    public function unbilledQuery(Organization $organization, ?CarbonImmutable $reference = null, ?int $customerId = null): Builder {
        $reference ??= CarbonImmutable::today();

        return ResaleLicenseAssignment::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('ended_at')
            ->whereNull('linkable_id')
            ->where(static fn(Builder $q) => $q->whereNull('invoice_reference')->orWhere('invoice_reference', ''))
            ->where('sold_on', '<', DateRange::dayAfter($reference->subDays(self::UNBILLED_DAYS)))
            ->when($customerId !== null, static fn(Builder $q) => $q->where('customer_id', $customerId));
    }

    /** @return Collection<int, ResaleLicenseAssignment> */
    public function unbilled(Organization $organization, ?CarbonImmutable $reference = null, ?int $customerId = null): Collection {
        return $this->unbilledQuery($organization, $reference, $customerId)
            ->with(['unit.batch.product', 'customer:id,name', 'foreignCustomer:id,name'])
            ->orderBy('sold_on')->orderBy('id')
            ->get();
    }

    public function unbilledCount(Organization $organization, ?CarbonImmutable $reference = null, ?int $customerId = null): int {
        return $this->unbilledQuery($organization, $reference, $customerId)->count();
    }

    /** Zeile älter als die Frist und ohne Rechnungsbezug — für die Markierung in Listen. */
    public static function isOverdue(ResaleLicenseAssignment $assignment, ?CarbonImmutable $reference = null): bool {
        $reference ??= CarbonImmutable::today();

        return $assignment->ended_at === null && ! $assignment->hasInvoice() && $assignment->sold_on->addDays(self::UNBILLED_DAYS)->lessThan($reference);
    }
}
