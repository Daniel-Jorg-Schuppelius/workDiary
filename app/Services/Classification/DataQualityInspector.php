<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataQualityInspector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase};
use App\Models\DiaryEntry;

/**
 * Leitet sichtbare Datenqualitäts-Hinweise (Feature 024) ohne neue
 * Pflichtmechanik ab: nutzt die bereits vorhandene Pflichtklassifikations-
 * Logik ({@see ClassificationRequirementValidator}) und füttert sie mit den
 * Werten, die ein Auftrag heute persistiert (entry_type über die Auftragsart,
 * priority über die Spalte). Daraus entstehen rein lesende Lücken-Hinweise
 * für die Detailansicht — es wird nichts erzwungen oder geschrieben.
 */
class DataQualityInspector {
    public function __construct(
        private readonly ClassificationRequirementValidator $validator,
    ) {}

    /**
     * Liefert die fehlenden Pflichtklassifikationen eines Auftrags für die
     * angegebene Phase (Default: bei Anlage). Jeder Eintrag enthält die
     * Domäne, ein menschenlesbares Label und die Schwere (hard/soft).
     *
     * @return list<array{domain: string, label: string, severity: string, blocking: bool}>
     */
    public function diaryEntryGaps(
        DiaryEntry $entry,
        ClassificationRequirementPhase $phase = ClassificationRequirementPhase::OnCreate,
    ): array {
        $results = $this->validator->validate($entry, $phase, $this->persistedValues($entry), audit: false);

        $gaps = [];
        foreach ($results as $result) {
            $gaps[] = [
                'domain' => $result->requiredDomain,
                'label' => $this->domainLabel($result->requiredDomain),
                'severity' => $result->severity->value,
                'blocking' => $result->isBlocking(),
            ];
        }

        return $gaps;
    }

    /**
     * Bildet die heute am Auftrag persistierten Klassifikationswerte auf das
     * vom Validator erwartete `valuesByDomain`-Format ab. Bewusst konservativ:
     * nur tatsächlich gespeicherte Werte (kein Erfinden von Persistenz).
     *
     * @return array<string, list<string>>
     */
    private function persistedValues(DiaryEntry $entry): array {
        $values = [];

        $entryTypeSlug = $entry->entryType?->slug;
        if (is_string($entryTypeSlug) && $entryTypeSlug !== '') {
            $values[ClassificationDomain::EntryType->value] = [$entryTypeSlug];
        }

        $priority = $entry->priority;
        if ($priority !== null) {
            $values[ClassificationDomain::Priority->value] = [$priority->value];
        }

        return $values;
    }

    private function domainLabel(string $domain): string {
        return match (ClassificationDomain::tryFrom($domain)) {
            ClassificationDomain::EntryType => (string) __('Auftragstypen'),
            ClassificationDomain::Activity => (string) __('Tätigkeiten'),
            ClassificationDomain::DefectType => (string) __('Fehlertypen'),
            ClassificationDomain::RootCause => (string) __('Ursachen'),
            ClassificationDomain::Result => (string) __('Ergebnisse'),
            ClassificationDomain::Priority => (string) __('Prioritäten'),
            ClassificationDomain::GoodwillReason => (string) __('Kulanzgründe'),
            ClassificationDomain::ReworkReason => (string) __('Nacharbeitsgründe'),
            ClassificationDomain::ProductGroup => (string) __('Produktgruppen'),
            ClassificationDomain::DienstmittelType => (string) __('Dienstmitteltypen'),
            null => $domain,
        };
    }
}
