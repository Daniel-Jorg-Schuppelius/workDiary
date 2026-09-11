<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRecipientInvoiceLines.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine, Organization};
use App\Models\Reselling\ResalePeriod;
use App\Services\Reselling\Register\{LicenseArticleClassifier, LinkProposer, PeriodLinker};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rechnungen und Lizenzpositionen eines Rechnungsempfängers aus dem
 * Belegspiegel (Feature 152, Review 2026-09-10): Kontakte → gültige
 * Ausgangsrechnungen im Fenster → Positionen von Abo-Artikeln (Klassifikator)
 * → Verbrauch je Position. Plugin-intern: der Kern liest über die
 * {@see LexofficeInvoiceMirrorSource} (Spiegel-Abstraktion).
 *
 * Fenster: eine Rechnung zählt ab `$from`, wenn ihr Belegdatum ODER das Ende
 * ihres Leistungszeitraums dort liegt (36-Monats-Rechnung von 2024 deckt 2026).
 */
final class LexofficeRecipientInvoiceLines {
    private const KIND_INVOICE = 'invoice';

    private const KIND_VOIDED = 'voided';

    private const KIND_CREDIT_NOTE = 'creditnote';

    /** @var array<int, list<int>> Organisation → IDs der Abo-Artikel (je Instanz gemerkt) */
    private array $licenseArticleIds = [];

    private readonly PeriodLinker $linker;

    public function __construct(private readonly LicenseArticleClassifier $classifier = new LicenseArticleClassifier(), ?PeriodLinker $linker = null) {
        $this->linker = $linker ?? new PeriodLinker;
    }

    /**
     * Lizenzpositionen der Kontakte im Fenster, mit Beleg und Artikel geladen.
     *
     * @param  list<string>|null  $contactIds  null = alle Kontakte der Organisation; [] = keine
     * @param  CarbonImmutable|null  $from  Belegdatum oder Leistungsende ab diesem Tag
     * @param  CarbonImmutable|null  $to  Belegdatum bis einschließlich diesem Tag
     * @return Collection<int, LexofficeVoucherLine>
     */
    public function for(Organization $organization, ?array $contactIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection {
        return $this->lines($organization, $contactIds, $from, $to, self::KIND_INVOICE);
    }

    /**
     * Lizenzpositionen stornierter Rechnungen der Kontakte (Erklärung einer Lücke).
     *
     * @param  list<string>|null  $contactIds
     * @return Collection<int, LexofficeVoucherLine>
     */
    public function voided(Organization $organization, ?array $contactIds): Collection {
        return $this->lines($organization, $contactIds, null, null, self::KIND_VOIDED);
    }

    /**
     * Lizenzpositionen gültiger Gutschriften der Kontakte (Review 2026-09-10,
     * A3) — für negative Bezüge von Hand; der Vorschlagslauf verrechnet
     * Gutschriften bewusst nicht heuristisch, deshalb nicht Teil von `for()`.
     *
     * @param  list<string>  $contactIds
     * @param  CarbonImmutable|null  $from  Belegdatum oder Leistungsende ab diesem Tag
     * @return Collection<int, LexofficeVoucherLine>
     */
    public function creditNotes(Organization $organization, array $contactIds, ?CarbonImmutable $from = null): Collection {
        return $this->lines($organization, $contactIds, $from, null, self::KIND_CREDIT_NOTE);
    }

    /**
     * Kandidaten für eine Periode: Lizenzpositionen des Empfängers im Fenster
     * der Periode (−90 Tage, Fensterende je Position, Leistungszeitraum),
     * neueste Rechnung zuerst.
     *
     * @param  list<string>  $contactIds
     * @return Collection<int, LexofficeVoucherLine>
     */
    public function forPeriod(Organization $organization, array $contactIds, ResalePeriod $period): Collection {
        $source = new LexofficeInvoiceMirrorSource($this->classifier);

        return $this->for($organization, $contactIds, $period->starts_on->subDays(LinkProposer::WINDOW_BEFORE))
            ->filter(static fn(LexofficeVoucherLine $line): bool => LinkProposer::inWindow($period, $source->toLine($line, canPreview: false)))
            ->sortBy([static fn(LexofficeVoucherLine $a, LexofficeVoucherLine $b): int => ($b->voucher->voucher_date <=> $a->voucher->voucher_date) ?: ($a->position <=> $b->position)])
            ->values();
    }

    /**
     * Gültige Ausgangsrechnungen der Kontakte ab `$from` mit gespiegelten
     * Positionen (neueste zuerst); jede Position trägt `is_license` und den
     * Beleg als geladene Relation (Leistungszeitraum für die Lizenzmonate).
     * Nicht-Lizenzpositionen bleiben dabei — zur Prüfung der Rechnung.
     *
     * @param  list<string>  $contactIds
     * @return Collection<int, LexofficeVoucher>
     */
    public function vouchers(Organization $organization, array $contactIds, CarbonImmutable $from, int $limit = 150): Collection {
        if ($contactIds === []) {
            return collect();
        }
        $vouchers = $this->voucherQuery($organization, $contactIds, $from)->whereNotNull('lines_synced_at')
            ->with(['lines.article:id,name,unit_name,resale_role'])
            ->orderByDesc('voucher_date')->orderByDesc('id')->limit($limit)->get();
        foreach ($vouchers as $voucher) {
            foreach ($voucher->lines as $line) {
                $line->setRelation('voucher', $voucher);
                $line->setAttribute('is_license', $this->classifier->isLicense($line->article));
            }
        }

        return $vouchers;
    }

    /**
     * Rechnungen der Kontakte ab `$from`, deren Positionen noch nicht gespiegelt sind.
     *
     * @param  list<string>  $contactIds
     */
    public function pendingCount(Organization $organization, array $contactIds, ?CarbonImmutable $from = null): int {
        if ($contactIds === []) {
            return 0;
        }

        return $this->voucherQuery($organization, $contactIds, $from)->whereNull('lines_synced_at')->count();
    }

    /**
     * Verbrauch je Position über alle Perioden (Vorschläge eingeschlossen).
     *
     * @param  Collection<int, LexofficeVoucherLine>  $lines
     * @return array<int, array{months: float, periods: list<string>}>
     */
    public function consumed(Collection $lines, ?ResalePeriod $except = null): array {
        $ids = [];
        foreach ($lines as $line) {
            $ids[] = (int) $line->id;
        }

        return $this->linker->consumedMonths((new LexofficeVoucherLine)->getMorphClass(), $ids, $except);
    }

    /**
     * Lizenzpositionen gültiger Rechnungen ohne Bezug zu einer Periode —
     * nur die Abfrage: die Seite zählt einmal und lädt begrenzt (C12).
     *
     * @return Builder<LexofficeVoucherLine>
     */
    public function unlinkedQuery(Organization $organization): Builder {
        $articleIds = $this->licenseArticleIds($organization);

        return LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('lexoffice_article_id', $articleIds === [] ? [0] : $articleIds)
            ->whereDoesntHave('periodLinks')
            ->whereIn('voucher_id', $this->voucherQuery($organization, null, null)->select('id'));
    }

    /**
     * @param  list<string>|null  $contactIds
     * @param  self::KIND_*  $kind
     * @return Collection<int, LexofficeVoucherLine>
     */
    private function lines(Organization $organization, ?array $contactIds, ?CarbonImmutable $from, ?CarbonImmutable $to, string $kind): Collection {
        if ($contactIds === []) {
            return collect();
        }
        $articleIds = $this->licenseArticleIds($organization);
        if ($articleIds === []) {
            return collect();
        }

        return LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('lexoffice_article_id', $articleIds)
            ->whereIn('voucher_id', $this->voucherQuery($organization, $contactIds, $from, $to, $kind)->select('id'))
            ->with(['voucher:id,external_id,contact_external_id,customer_id,voucher_type,voucher_number,voucher_date,voucher_status,service_starts_on,service_ends_on,voucher_text,recipient_name', 'article:id,name,unit_name,resale_role'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Belege der Kontakte (null = alle) im Fenster: gültige Ausgangsrechnungen,
     * gültige Gutschriften oder — für die Erklärung einer Lücke — die
     * stornierten Rechnungen.
     *
     * @param  list<string>|null  $contactIds
     * @param  self::KIND_*  $kind
     * @return Builder<LexofficeVoucher>
     */
    private function voucherQuery(Organization $organization, ?array $contactIds, ?CarbonImmutable $from, ?CarbonImmutable $to = null, string $kind = self::KIND_INVOICE): Builder {
        $query = LexofficeVoucher::query()->withoutGlobalScopes()->where('organization_id', $organization->id);
        if ($kind === self::KIND_VOIDED) {
            $query->where('voucher_type', 'invoice')->where('archived', false)->where('voucher_status', 'voided');
            if ($contactIds !== null) {
                $query->whereIn('contact_external_id', $contactIds);
            }
        } elseif ($kind === self::KIND_CREDIT_NOTE) {
            $query->issuedCreditNotes($contactIds ?? []);
        } else {
            $query->issuedInvoices($contactIds ?? []);
        }
        if ($from !== null) {
            $query->where(static fn(Builder $w) => $w->where('voucher_date', '>=', DateRange::day($from))->orWhere('service_ends_on', '>=', DateRange::day($from)));
        }
        if ($to !== null) {
            $query->where('voucher_date', '<', DateRange::dayAfter($to));
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function licenseArticleIds(Organization $organization): array {
        if (! isset($this->licenseArticleIds[$organization->id])) {
            $ids = [];
            foreach (LexofficeArticle::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get(['id', 'name', 'resale_role']) as $article) {
                if ($this->classifier->isLicense($article)) {
                    $ids[] = (int) $article->id;
                }
            }
            $this->licenseArticleIds[$organization->id] = $ids;
        }

        return $this->licenseArticleIds[$organization->id];
    }
}
