<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxExemptionNotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Finance\TaxRule;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;

/**
 * Pflichthinweise zu Umsätzen ohne Steuer (§ 14 Abs. 4 Nr. 8, § 14a UStG,
 * MVP-1623): welche Hinweise der {@see TaxResolver} in Entwürfe schreibt und
 * welche das Rechnungs-PDF ergänzt, weil der Rechnungstext sie nicht trägt.
 */
final class TaxExemptionNotes {
    /** Satzart der Steuerregel, deren Hinweistext der Resolver für die Kategorie einsetzt. */
    private const array RULE_TYPES = ['AE' => 'reverse_charge', 'G' => 'export', 'E' => 'exempt'];

    /** Hinweistexte aus config/taxation.php je Kategorie. */
    private const array CONFIG_NOTES = ['AE' => ['reverse_charge', 'reverse_charge_domestic'], 'G' => ['export']];

    public function __construct(private readonly XRechnungGenerator $einvoice) {}

    /**
     * Hinweise, die das PDF unter dem Steuerausweis druckt: je Kategorie ohne
     * Steuer der Befreiungsgrund der E-Rechnung (BT-120). Reverse Charge des
     * Kopfes und § 19 weist das PDF selbst aus; steht der Grund oder der
     * automatische Hinweis der Kategorie schon im Rechnungstext, entfällt er.
     *
     * @return list<string>
     */
    public function printed(Invoice $invoice): array {
        $organization = $invoice->organization;
        $smallBusiness = (string) data_get($organization, 'settings.einvoice.small_business', '0') === '1';
        $text = self::normalized((string) $invoice->notes);

        $printed = [];
        foreach ($this->einvoice->exemptionReasons($invoice) as $category => $reason) {
            if (($category === 'AE' && (bool) $invoice->is_reverse_charge) || ($category === 'E' && $smallBusiness)) {
                continue;
            }
            $known = [$reason, ...($organization !== null ? $this->automatic($organization, $category) : [])];
            foreach ($known as $note) {
                $needle = self::normalized($note);
                if ($needle !== '' && str_contains($text, $needle)) {
                    continue 2;
                }
            }
            $printed[] = $reason;
        }

        return array_values(array_unique($printed));
    }

    /**
     * Hinweistexte, die der {@see TaxResolver} in Entwürfe schreibt: Katalog,
     * Steuerregeln der Organisation und des Katalogs, § 19 in jeder Belegsprache;
     * mit Kategorie nur die Texte dieser Kategorie.
     *
     * @return list<string>
     */
    public function automatic(Organization $organization, ?string $category = null): array {
        $notes = [];
        foreach (self::CONFIG_NOTES as $noteCategory => $keys) {
            if ($category === null || $category === $noteCategory) {
                foreach ($keys as $key) {
                    $notes[] = (string) config('taxation.notes.' . $key);
                }
            }
        }
        $ruleType = $category !== null ? (self::RULE_TYPES[$category] ?? null) : null;
        if ($category === null || $ruleType !== null) {
            $notes = [...$notes, ...TaxRule::query()
                ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
                ->whereNotNull('note')
                ->when($ruleType !== null, fn ($query) => $query->where('rate_type', $ruleType))
                ->pluck('note')
                ->map(static fn ($note): string => (string) $note)
                ->all()];
        }
        if ($category === null || $category === 'E') {
            foreach (\App\Support\Locales::codes() as $locale) {
                $notes[] = (string) trans('Keine Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).', [], $locale);
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $notes), static fn (string $note): bool => $note !== '')));
    }

    /** Vergleichsform: ohne Leerraum, Punkte und Groß-/Kleinschreibung („§3a“ = „§ 3a“). */
    private static function normalized(string $text): string {
        return mb_strtolower((string) preg_replace('/[\s.]+/u', '', $text));
    }
}
