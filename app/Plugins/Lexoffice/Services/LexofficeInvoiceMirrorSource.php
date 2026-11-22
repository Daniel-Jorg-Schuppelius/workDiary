<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceMirrorSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Services\Reselling\Mirror\{InvoiceMirrorSource, MirrorLine, MirrorVoucher};
use App\Services\Reselling\Plan\PlanTemplate;
use App\Services\Reselling\Register\{LicenseArticleClassifier, LicenseMonths, ResaleRequestMemo};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Cache, DB, Gate, Route};

/**
 * Lexoffice-Belegspiegel als Quelle des Reselling-Registers (Feature 152,
 * Review 2026-09-10, Spiegel-Abstraktion): übersetzt `LexofficeVoucherLine`
 * in {@see MirrorLine}s. Empfänger sind die Kunden hinter den verknüpften
 * Kontakten ({@see LexofficeContactMap}); ein Empfängerfilter läuft über
 * diese Verknüpfung (B13). Für die Anzeige fällt der Empfänger auf den vom
 * Belegabgleich gesetzten `voucher.customer_id` zurück (G), sonst bleibt nur
 * der Kontakt (`contact:<id>`).
 *
 * Belegabhängige Werte (Empfänger, Status, Belegtext-Hinweis, Permalink,
 * Vorschau-Route, Zeitraum) entstehen einmal je Beleg, nicht je Position;
 * Leseseite und Kontaktkarte leben je Request im {@see ResaleRequestMemo}
 * (Leistungsanalyse 2026-11-02, perfc-02/perfc-11).
 *
 * @phpstan-type VoucherContext array{customer_id: int|null, recipient_key: string, recipient_name: string|null, voucher_key: string, external_id: string, plan_key: string|null, number: string|null, date: CarbonImmutable|null, status: string, credit_note: bool, service_from: CarbonImmutable|null, service_to: CarbonImmutable|null, text: string|null, text_hint: string|null, permalink: string|null, preview_url: string|null}
 */
final class LexofficeInvoiceMirrorSource implements InvoiceMirrorSource {
    public const string KEY = 'lexoffice';

    /** Form der Cache-Zeilen; wechselt mit jeder Änderung an {@see toCacheRow()} oder an der Umrechnung. */
    private const int CACHE_VERSION = 1;

    /** Die Signatur entwertet; die Frist räumt nur ungenutzte Einträge ab. */
    private const int CACHE_TTL = 3600;

    /** So lange muss der Spiegel unverändert sein, bevor er in den Cache geht. */
    private const int CACHE_QUIET_SECONDS = 2;

    /** Darüber bleibt der Spiegel ohne Cache (Wertgröße im Datenbank-Cache). */
    private const int CACHE_MAX_LINES = 20000;

    /**
     * Leseseite je Request: die Quelle lebt im Singleton-Spiegel, der
     * Artikel-Cache der Leseseite gilt für alle Aufrufe eines Requests und
     * keinen darüber hinaus.
     */
    private function reader(): LexofficeRecipientInvoiceLines {
        return $this->memo()->remember('lexoffice-reader', static fn(): LexofficeRecipientInvoiceLines => new LexofficeRecipientInvoiceLines);
    }

    /** Kontaktkarte der Organisation je Request einmal (eine Mappe fragt sie fünf- bis siebenmal). */
    private function contactMap(Organization $organization): LexofficeContactMap {
        return $this->memo()->remember('lexoffice-contact-map:' . $organization->id, static fn(): LexofficeContactMap => LexofficeContactMap::forOrganization($organization));
    }

    private function memo(): ResaleRequestMemo {
        return app(ResaleRequestMemo::class);
    }

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new LexofficeVoucherLine)->getMorphClass();
    }

    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, bool $licenceOnly = true): Collection {
        $map = $this->contactMap($organization);
        if ($recipientCustomerIds === null && $from === null && $to === null && ! $licenceOnly) {
            return $this->allLines($organization, $map);
        }

        return $this->map($organization, $this->reader()->for($organization, $this->contacts($map, $recipientCustomerIds), $from, $to, $licenceOnly), $map);
    }

    /**
     * Alle Positionen gültiger Rechnungen der Organisation — die Ladung, die fast jede Seite des
     * Registers braucht. Umgerechnet liegen sie über Requests hinweg im Cache, unter einer Signatur
     * des Spiegelbestands (Anzahl, letzte Änderung, höchste ID von Positionen, Belegen, Artikeln und
     * Einstufungen): ändert sich etwas, auch per Massen-Update oder in einem anderen Prozess, passt
     * die Signatur nicht mehr und es wird neu gelesen. Nicht im Cache: was vom
     * Nutzer oder von anderen Tabellen abhängt (Vorschau-Recht, Kundenname, Kontaktzuordnung).
     *
     * @return Collection<int, MirrorLine>
     */
    private function allLines(Organization $organization, LexofficeContactMap $map): Collection {
        $key = 'resale:lexoffice-mirror-lines:' . self::CACHE_VERSION . ':' . $organization->id;
        [$signature, $changedAt] = $this->signature($organization);
        $cached = Cache::get($key);
        $rows = is_array($cached) && ($cached['signature'] ?? null) === $signature && is_array($cached['rows'] ?? null) ? $cached['rows'] : null;
        if ($rows === null) {
            $models = $this->reader()->for($organization, null, null, null, false);
            $lines = $this->map($organization, $models, $map);
            // Die Signatur misst in Sekunden: läuft gerade ein Sync, könnte eine zweite Änderung derselben
            // Sekunde unbemerkt bleiben — erst ablegen, wenn der Spiegel kurz ruht. Ein Stempel deutlich in der
            // Zukunft (Ortszeit per SQL geschrieben) ist kein laufender Sync und sperrt den Cache nicht.
            $quiet = $changedAt === null || $changedAt < now()->subSeconds(self::CACHE_QUIET_SECONDS)->format('Y-m-d H:i:s') || $changedAt > now()->addMinute()->format('Y-m-d H:i:s');
            if ($quiet && $lines->count() <= self::CACHE_MAX_LINES) {
                $rows = [];
                $sqids = [];
                $models = $models->values();
                foreach ($lines->values() as $index => $line) {
                    $voucher = $models->get($index)?->voucher;
                    if ($voucher === null) {
                        return $lines;
                    }
                    $sqid = $sqids[(int) $voucher->id] ??= Sqid::encode(LexofficeVoucher::class, (int) $voucher->id);
                    $rows[] = [...self::toCacheRow($line, $voucher->customer_id !== null ? (int) $voucher->customer_id : null, self::recipientName($voucher, null, [])), $sqid];
                }
                try {
                    Cache::put($key, ['signature' => $signature, 'rows' => $rows], self::CACHE_TTL);
                } catch (\Throwable) {
                    // Zu groß für den Cache-Speicher: weiter ohne Cache, jede Seite rechnet wie bisher.
                }
            }

            return $lines;
        }

        return $this->fromCacheRows($organization, $rows, $map);
    }

    /**
     * Bestandssignatur des Spiegels (eine Abfrage mit zwölf Aggregaten) und der Zeitpunkt der letzten Änderung.
     * Bewusst ohne den Org-Stempel des Registers: ein Bezug ändert den Spiegel nicht.
     *
     * @return array{0: string, 1: string|null}
     */
    private function signature(Organization $organization): array {
        $columns = [];
        $bindings = [];
        foreach (['lexoffice_voucher_lines', 'lexoffice_vouchers', 'lexoffice_articles', 'resale_article_classifications'] as $i => $table) {
            foreach (['c' => 'COUNT(*)', 'u' => 'MAX(updated_at)', 'm' => 'MAX(id)'] as $alias => $aggregate) {
                $columns[] = sprintf('(SELECT %s FROM %s WHERE organization_id = ?) AS %s%d', $aggregate, $table, $alias, $i);
                $bindings[] = $organization->id;
            }
        }
        $row = (array) DB::selectOne('SELECT ' . implode(', ', $columns), $bindings);
        $changedAt = null;
        foreach ($row as $alias => $value) {
            if (str_starts_with((string) $alias, 'u') && $value !== null && ($changedAt === null || (string) $value > $changedAt)) {
                $changedAt = substr((string) $value, 0, 19);
            }
        }

        return [implode('|', array_map(static fn(mixed $value): string => (string) $value, array_values($row))), $changedAt];
    }

    /**
     * @return list<mixed>
     */
    private static function toCacheRow(MirrorLine $line, ?int $voucherCustomerId, ?string $recipientFallback): array {
        $date = static fn(?CarbonImmutable $value): ?string => $value?->format('Y-m-d H:i:s.u e');

        return [
            $line->morphClass, $line->morphId, $line->organizationId, $line->key, $line->voucherKey, $line->voucherNumber, $date($line->voucherDate),
            $line->voucherStatus, $line->isCreditNote, $voucherCustomerId, $line->recipientKey, $recipientFallback, $line->articleKey, $line->articleName,
            $line->articleIsLicence, $line->name, $line->description, $line->quantity, $line->unitName,
            $line->unitNet->getAmount(), $line->unitNet->getScale(), $line->totalNet->getAmount(), $line->totalNet->getScale(), $line->currency->value,
            $date($line->serviceFrom), $date($line->serviceTo), $line->voucherText, $line->voucherTextHint, $line->position, $line->permalink,
            $line->voucherExternalId, $line->planKey,
        ];
    }

    /**
     * Spiegelzeilen aus dem Cache: Kundenzuordnung aus der Kontaktkarte, Kundenname und Vorschau-Recht
     * frisch — der Rest wie beim Umrechnen abgelegt.
     *
     * @param  array<int, mixed>  $rows
     * @return Collection<int, MirrorLine>
     */
    private function fromCacheRows(Organization $organization, array $rows, LexofficeContactMap $map): Collection {
        $customers = [];
        $customerIds = [];
        foreach ($rows as $row) {
            $recipientKey = (string) $row[10];
            if (! array_key_exists($recipientKey, $customers)) {
                $customers[$recipientKey] = $map->byContact(substr($recipientKey, strlen('contact:'))) ?? $row[9];
            }
            if ($customers[$recipientKey] !== null) {
                $customerIds[(int) $customers[$recipientKey]] = true;
            }
        }
        /** @var array<int, string> $names */
        $names = $customerIds === [] ? [] : Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', array_keys($customerIds))->pluck('name', 'id')->all();
        $canPreview = self::canPreview();
        $dates = [];
        $date = static function (?string $value) use (&$dates): ?CarbonImmutable {
            if ($value === null) {
                return null;
            }
            if (! isset($dates[$value])) {
                [$day, $time, $zone] = explode(' ', $value, 3);
                $dates[$value] = new CarbonImmutable($day . ' ' . $time, $zone);
            }

            return $dates[$value];
        };

        // Beträge und Vorschau-Links wiederholen sich über tausende Positionen: je Wert ein (unveränderliches) Objekt.
        $amounts = [];
        $money = static function (string $amount, CurrencyCode $currency, int $scale) use (&$amounts): Money {
            return $amounts[$amount . '|' . $scale . '|' . $currency->value] ??= Money::of($amount, $currency, $scale);
        };
        $previews = [];

        $lines = [];
        foreach ($rows as $row) {
            $voucherKey = (string) $row[4];
            $customerId = $customers[(string) $row[10]] !== null ? (int) $customers[(string) $row[10]] : null;
            $name = $customerId !== null ? ($names[$customerId] ?? null) : null;
            $currency = CurrencyCode::from((string) $row[23]);
            $lines[] = new MirrorLine(
                sourceKey: self::KEY,
                morphClass: (string) $row[0],
                morphId: (int) $row[1],
                organizationId: (int) $row[2],
                key: (string) $row[3],
                voucherKey: $voucherKey,
                voucherExternalId: $row[30],
                planKey: $row[31],
                voucherNumber: $row[5],
                voucherDate: $date($row[6]),
                voucherStatus: (string) $row[7],
                isCreditNote: (bool) $row[8],
                recipientCustomerId: $customerId,
                recipientKey: (string) $row[10],
                recipientName: $name ?? $row[11],
                articleKey: $row[12],
                articleName: $row[13],
                articleIsLicence: (bool) $row[14],
                name: (string) $row[15],
                description: $row[16],
                quantity: (float) $row[17],
                unitName: $row[18],
                unitNet: $money((string) $row[19], $currency, (int) $row[20]),
                totalNet: $money((string) $row[21], $currency, (int) $row[22]),
                currency: $currency,
                serviceFrom: $date($row[24]),
                serviceTo: $date($row[25]),
                voucherText: $row[26],
                voucherTextHint: $row[27],
                position: (int) $row[28],
                permalink: $row[29],
                previewUrl: $canPreview ? ($previews[(string) $row[32]] ??= self::previewUrlOf((string) $row[32])) : null,
            );
        }

        return collect($lines);
    }

    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection {
        $map = $this->contactMap($organization);

        return $this->map($organization, $this->reader()->voided($organization, $this->contacts($map, $recipientCustomerIds)), $map);
    }

    public function planLines(Organization $organization, array $planKeys, CarbonImmutable $from): Collection {
        $prefix = LexofficeRecurringPlanSource::KEY . ':';
        $ids = [];
        foreach ($planKeys as $key) {
            if (str_starts_with($key, $prefix) && strlen($key) > strlen($prefix)) {
                $ids[] = substr($key, strlen($prefix));
            }
        }
        if ($ids === []) {
            return collect();
        }

        return $this->map($organization, $this->reader()->forTemplates($organization, array_values(array_unique($ids)), $from), $this->contactMap($organization));
    }

    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection {
        $map = $this->contactMap($organization);

        return $this->map($organization, $this->reader()->creditNotes($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from), $map);
    }

    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection {
        $map = $this->contactMap($organization);
        $vouchers = $this->reader()->vouchers($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from, $limit);
        $names = $this->customerNames($organization, $vouchers->flatMap(static fn(LexofficeVoucher $v): Collection => $v->lines), $map);
        $canPreview = self::canPreview();
        $classifier = app(LicenseArticleClassifier::class);

        return $vouchers->map(fn(LexofficeVoucher $voucher): MirrorVoucher => $this->toVoucher($voucher, $map, $names, $canPreview, $classifier))->values();
    }

    public function voucherByKey(Organization $organization, string $voucherKey): ?MirrorVoucher {
        if (! is_numeric($voucherKey)) {
            return null;
        }
        $voucher = LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with('lines.article:id,name,unit_name')
            ->find((int) $voucherKey);
        if ($voucher === null) {
            return null;
        }
        $map = $this->contactMap($organization);

        return $this->toVoucher($voucher, $map, $this->customerNames($organization, $voucher->lines, $map), self::canPreview(), app(LicenseArticleClassifier::class));
    }

    /** @param  array<int, string>  $names */
    private function toVoucher(LexofficeVoucher $voucher, LexofficeContactMap $map, array $names, bool $canPreview, LicenseArticleClassifier $classifier): MirrorVoucher {
        $context = self::voucherContext($voucher, $map, $names, $canPreview);
        $lines = [];
        foreach ($voucher->lines as $line) {
            $lines[] = $this->lineFrom($line, $context, $classifier);
        }

        return new MirrorVoucher(
            sourceKey: self::KEY,
            voucherKey: $context['voucher_key'],
            voucherNumber: $context['number'],
            voucherDate: $context['date'],
            voucherStatus: $context['status'],
            isCreditNote: $context['credit_note'],
            recipientCustomerId: $context['customer_id'],
            recipientName: $context['recipient_name'],
            voucherText: $context['text'],
            voucherTextHint: $context['text_hint'],
            serviceFrom: $context['service_from'],
            serviceTo: $context['service_to'],
            permalink: $context['permalink'],
            previewUrl: $context['preview_url'],
            lines: $lines,
        );
    }

    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int {
        $map = $this->contactMap($organization);

        return $this->reader()->pendingCount($organization, $this->contacts($map, $recipientCustomerIds) ?? [], $from);
    }

    public function unlinkedLines(Organization $organization, int $limit): array {
        $query = $this->reader()->unlinkedQuery($organization);
        $total = (clone $query)->count();
        $rows = $query
            ->with(['voucher:id,external_id,contact_external_id,customer_id,voucher_type,voucher_number,voucher_date,voucher_status,service_starts_on,service_ends_on,voucher_text,recipient_name,recurring_template_id', 'article:id,name,unit_name'])
            ->orderByDesc(LexofficeVoucher::query()->withoutGlobalScopes()->select('voucher_date')->whereColumn('lexoffice_vouchers.id', 'lexoffice_voucher_lines.voucher_id'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return ['total' => $total, 'lines' => $this->map($organization, $rows, $this->contactMap($organization))];
    }

    public function linesByIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }
        $rows = LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->with(['voucher', 'article:id,name,unit_name'])
            ->get();

        return $this->map($organization, $rows, $this->contactMap($organization))->keyBy(static fn(MirrorLine $line): int => $line->morphId);
    }

    public function lineByKey(Organization $organization, string $key): ?MirrorLine {
        $id = Sqid::decode(LexofficeVoucherLine::class, $key);

        return $id === null ? null : $this->linesByIds($organization, [$id])->get($id);
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return $this->contactMap($organization)->byCustomer($recipient->id) !== [];
    }

    public function constrainProposalLinks(Organization $organization, Builder $links): void {
        // Entwürfe werden nicht gespiegelt (B20): jeder Vorschlag auf eine Spiegelposition ist ersetzbar.
    }

    public function draftBecameInvoice(Organization $organization, string $reference): bool {
        // Lexoffice behält die ID beim Abschließen: gespiegelter Beleg mit der Entwurfs-ID, der kein Entwurf mehr ist.
        return LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('external_id', $reference)
            ->where(static fn(Builder $w) => $w->whereNull('voucher_status')->orWhere('voucher_status', '!=', 'draft'))
            ->exists();
    }

    /**
     * Eine Position als Spiegelzeile — auch ohne Kontaktkarte (Tests, Einzelfälle):
     * dann bleibt der Empfänger der Kunde laut Beleg, sonst nur der Kontakt.
     *
     * @param  array<int, string>  $customerNames  Kunden-ID → Name
     */
    public function toLine(LexofficeVoucherLine $line, ?LexofficeContactMap $map = null, array $customerNames = [], ?bool $canPreview = null): MirrorLine {
        $line->loadMissing('voucher');

        return $this->lineFrom($line, self::voucherContext($line->voucher, $map, $customerNames, $canPreview ?? self::canPreview()), app(LicenseArticleClassifier::class));
    }

    /**
     * Werte, die alle Positionen eines Belegs teilen — einmal je Beleg statt je Position.
     *
     * @param  array<int, string>  $customerNames
     * @return VoucherContext
     */
    private static function voucherContext(LexofficeVoucher $voucher, ?LexofficeContactMap $map, array $customerNames, bool $canPreview): array {
        $customerId = self::customerIdOf($voucher, $map);

        return [
            'customer_id' => $customerId,
            'recipient_key' => 'contact:' . (string) $voucher->contact_external_id,
            'recipient_name' => self::recipientName($voucher, $customerId, $customerNames),
            'voucher_key' => (string) $voucher->id,
            'external_id' => (string) $voucher->external_id,
            'plan_key' => $voucher->recurring_template_id !== null ? PlanTemplate::keyOf(LexofficeRecurringPlanSource::KEY, (string) $voucher->recurring_template_id) : null,
            'number' => $voucher->voucher_number,
            'date' => self::dateOf($voucher, 'voucher_date'),
            'status' => self::status($voucher),
            'credit_note' => (string) $voucher->voucher_type === 'creditnote',
            'service_from' => self::dateOf($voucher, 'service_starts_on'),
            'service_to' => self::dateOf($voucher, 'service_ends_on'),
            'text' => $voucher->voucher_text,
            'text_hint' => $voucher->voucherTextHint(),
            'permalink' => $voucher->lexofficePermalink(),
            'preview_url' => $canPreview ? self::previewUrl((int) $voucher->id) : null,
        ];
    }

    /** @param  VoucherContext  $context */
    private function lineFrom(LexofficeVoucherLine $line, array $context, LicenseArticleClassifier $classifier): MirrorLine {
        $article = $line->lexoffice_article_id !== null ? $line->article : null;
        $articleKey = $line->lexoffice_article_id !== null ? ArticleCatalog::key(LexofficeArticleCatalogSource::PREFIX, (int) $line->lexoffice_article_id) : null;
        // Sammelrechnung: der Belegzeitraum umspannt alle Positionen, der eigene steht im Text.
        $period = LicenseMonths::periodInText($line->description);

        return new MirrorLine(
            sourceKey: self::KEY,
            morphClass: $line->getMorphClass(),
            morphId: (int) $line->id,
            organizationId: (int) $line->organization_id,
            key: Sqid::encode(LexofficeVoucherLine::class, $line->id),
            voucherKey: $context['voucher_key'],
            voucherExternalId: $context['external_id'],
            planKey: $context['plan_key'],
            voucherNumber: $context['number'],
            voucherDate: $context['date'],
            voucherStatus: $context['status'],
            isCreditNote: $context['credit_note'],
            recipientCustomerId: $context['customer_id'],
            recipientKey: $context['recipient_key'],
            recipientName: $context['recipient_name'],
            articleKey: $articleKey,
            articleName: $article?->name,
            articleIsLicence: $line->getAttribute('is_license') !== null ? (bool) $line->getAttribute('is_license') : $classifier->isLicense((int) $line->organization_id, $articleKey, $article?->name),
            name: $line->name,
            description: $line->description,
            quantity: (float) $line->quantity,
            unitName: $line->unit_name,
            unitNet: $line->unit_net,
            totalNet: $line->total_net,
            currency: $line->currency,
            serviceFrom: $period['from'] ?? $context['service_from'],
            serviceTo: $period['to'] ?? $context['service_to'],
            voucherText: $context['text'],
            voucherTextHint: $context['text_hint'],
            position: (int) $line->position,
            permalink: $context['permalink'],
            previewUrl: $context['preview_url'],
        );
    }

    /**
     * Kontakte der Empfänger: null = alle; ohne verknüpften Kontakt [] (keine Positionen).
     *
     * @param  list<int>|null  $recipientCustomerIds
     * @return list<string>|null
     */
    private function contacts(LexofficeContactMap $map, ?array $recipientCustomerIds): ?array {
        if ($recipientCustomerIds === null) {
            return null;
        }
        $contacts = [];
        foreach ($recipientCustomerIds as $customerId) {
            foreach ($map->byCustomer($customerId) as $contact) {
                $contacts[$contact] = true;
            }
        }

        return array_keys($contacts);
    }

    /**
     * Positionen zu Spiegelzeilen: Belegwerte einmal je Beleg, Klassifikator und
     * Vorschau-Recht einmal je Aufruf.
     *
     * @param  Collection<int, LexofficeVoucherLine>  $rows
     * @return Collection<int, MirrorLine>
     */
    private function map(Organization $organization, Collection $rows, LexofficeContactMap $map): Collection {
        if ($rows->isEmpty()) {
            return collect();
        }
        $names = $this->customerNames($organization, $rows, $map);
        $canPreview = self::canPreview();
        $classifier = app(LicenseArticleClassifier::class);
        /** @var array<int, VoucherContext> $contexts */
        $contexts = [];
        $lines = [];
        foreach ($rows as $line) {
            $voucher = $line->voucher;
            $contexts[(int) $voucher->id] ??= self::voucherContext($voucher, $map, $names, $canPreview);
            $lines[] = $this->lineFrom($line, $contexts[(int) $voucher->id], $classifier);
        }

        return collect($lines);
    }

    /**
     * Namen der Empfänger-Kunden der geladenen Positionen (eine Abfrage).
     *
     * @param  Collection<int, LexofficeVoucherLine>  $rows
     * @return array<int, string>
     */
    private function customerNames(Organization $organization, Collection $rows, LexofficeContactMap $map): array {
        $ids = [];
        foreach ($rows as $line) {
            $id = self::customerIdOf($line->voucher, $map);
            if ($id !== null) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }
        /** @var array<int, string> $names */
        $names = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', array_keys($ids))->pluck('name', 'id')->all();

        return $names;
    }

    private static function customerIdOf(LexofficeVoucher $voucher, ?LexofficeContactMap $map): ?int {
        $mapped = $map?->byContact((string) $voucher->contact_external_id);

        return $mapped ?? ($voucher->customer_id !== null ? (int) $voucher->customer_id : null);
    }

    /**
     * @param  array<int, string>  $customerNames
     */
    private static function recipientName(LexofficeVoucher $voucher, ?int $customerId, array $customerNames): ?string {
        $name = $customerId !== null ? ($customerNames[$customerId] ?? null) : null;
        if ($name === null) {
            $name = trim((string) $voucher->recipient_name);
        }

        return $name !== '' ? $name : null;
    }

    private static function status(LexofficeVoucher $voucher): string {
        return match ((string) $voucher->voucher_status) {
            'draft' => MirrorLine::STATUS_DRAFT,
            'voided' => MirrorLine::STATUS_VOIDED,
            'paid', 'paidoff' => MirrorLine::STATUS_PAID,
            default => MirrorLine::STATUS_ISSUED,
        };
    }

    private static function date(mixed $value): ?CarbonImmutable {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }

    /**
     * Datumsspalte des Belegs als unveränderliches Datum. Tausende Belege teilen sich
     * wenige Tage; je Rohwert wird einmal umgerechnet (unveränderlich, also teilbar).
     */
    private static function dateOf(LexofficeVoucher $voucher, string $column): ?CarbonImmutable {
        /** @var array<string, CarbonImmutable> $dates */
        static $dates = [];
        $raw = $voucher->getAttributes()[$column] ?? null;
        if (! is_string($raw)) {
            return self::date($voucher->getAttribute($column));
        }
        $key = $raw . '|' . date_default_timezone_get();

        return $dates[$key] ??= self::date($voucher->getAttribute($column));
    }

    private static function canPreview(): bool {
        return Gate::allows(Permission::VoucherViewAny->value) && Route::has('lexoffice.vouchers.preview');
    }

    private static function previewUrl(int $voucherId): string {
        return self::previewUrlOf(Sqid::encode(LexofficeVoucher::class, $voucherId));
    }

    /** Vorschau-Adresse zum Sqid eines Belegs: die Route einmal je Prozess und Wurzel-URL, je Beleg nur der Schlüssel. */
    private static function previewUrlOf(string $voucherSqid): string {
        static $templates = [];
        $template = $templates[url('/')] ??= route('lexoffice.vouchers.preview', '__VOUCHER__');

        return str_replace('__VOUCHER__', $voucherSqid, $template);
    }

    public function bookedNet(Organization $organization, string $voucherNumber): ?Money {
        $voucher = LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('voucher_number', $voucherNumber)
            ->where('voucher_status', '!=', 'voided')
            ->orderByDesc('voucher_date')
            ->first();

        return $voucher?->net_amount;
    }

    public function bookedVoucherNumbers(Organization $organization, array $voucherNumbers): array {
        if ($voucherNumbers === []) {
            return [];
        }

        return array_values(array_map('strval', LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('voucher_number', $voucherNumbers)
            ->where('voucher_status', '!=', 'voided')
            ->distinct()
            ->pluck('voucher_number')
            ->all()));
    }
}
