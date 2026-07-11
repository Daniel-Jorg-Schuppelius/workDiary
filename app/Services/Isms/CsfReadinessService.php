<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsfReadinessService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\ControlImplementationStatus;
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsRequirement, IsmsScope};
use Illuminate\Support\Collection;

/**
 * NIST-CSF-2.0-Auswertung (Feature 044/046, Nachtrag NIST): leitet je
 * Geltungsbereich die Abdeckung der sechs CSF-Funktionen ab — REINE
 * Leseaggregation, keine Persistenz.
 *
 * Zwei Quellen je Funktion:
 * - DIREKT: SoA-Aussagen zu importierten NIST-CSF-Anforderungen
 *   (Katalog nist-csf-2-0) im Geltungsbereich.
 * - ABGELEITET: über den Crosswalk (config/isms-crosswalks/) auf die
 *   ISO/IEC-27001-SoA gemappte Abdeckung.
 *
 * „Abgedeckt" folgt der ReadinessService-Konvention: anwendbare SoA-
 * Aussage mit Umsetzungsstatus implemented ODER partial. Das Ergebnis ist
 * eine fachliche SELBSTEINSCHÄTZUNG, KEINE amtliche Konformitätszusage.
 */
class CsfReadinessService {
    /** Normprofil-Key des CSF-Katalogs. */
    public const CSF_PROFILE_KEY = 'nist-csf-2-0';

    /** Crosswalk-Key CSF → ISO/IEC 27001. */
    public const CROSSWALK_KEY = 'nist-csf-2-0--iso27001-2022';

    /** Ampel: ab dieser Quote grün. */
    public const QUOTE_GREEN = 80;

    /** Ampel: ab dieser Quote gelb (darunter rot). */
    public const QUOTE_AMBER = 40;

    public function __construct(
        private readonly NormProfileRegistry $profiles,
        private readonly CrosswalkRegistry $crosswalks,
    ) {}

    /**
     * Abdeckung der sechs CSF-Funktionen je Geltungsbereich.
     *
     * @return array{
     *     has_nist: bool,
     *     has_crosswalk: bool,
     *     source_label: string,
     *     target_label: ?string,
     *     functions: list<array{ref: string, title: string, direct: array{total: int, applicable: int, covered: int, quote: int}, mapped: array{total: int, applicable: int, covered: int, quote: int}, mode: string, quote: int, tone: string}>,
     *     overall_quote: int,
     *     overall_tone: string,
     *     is_self_assessment: bool
     * }
     */
    public function forScope(IsmsScope $scope): array {
        $csf = $this->profiles->requirements(self::CSF_PROFILE_KEY);
        $csfMeta = $this->profiles->get(self::CSF_PROFILE_KEY);

        $crosswalkKey = $this->crosswalks->has(self::CROSSWALK_KEY) ? self::CROSSWALK_KEY : null;
        $crosswalkMeta = $crosswalkKey !== null ? $this->crosswalks->get($crosswalkKey) : null;

        $statements = $this->statements($scope);

        // Direkt: NIST-CSF-Anforderungen (ref_no → id) der Organisation.
        $csfReqByRef = $this->requirementIdsByRef($csfMeta['norm'], $csfMeta['edition']);
        $hasNist = $csfReqByRef->isNotEmpty();

        // Abgeleitet: ISO-Anforderungen + Mappings je Funktion.
        $isoReqByRef = $crosswalkMeta !== null
            ? $this->requirementIdsByRef($crosswalkMeta['target_norm'], $crosswalkMeta['target_edition'])
            : collect();
        $mappedRefsByFunction = $crosswalkKey !== null
            ? $this->mappedTargetsByFunction($crosswalkKey)
            : [];

        // Funktionen = CSF-Referenzen ohne Punkt (GV, ID, PR, DE, RS, RC).
        $functions = [];
        $sumApplicable = 0;
        $sumCovered = 0;
        foreach ($csf as $entry) {
            if (str_contains($entry['ref_no'], '.')) {
                continue;
            }

            $function = $entry['ref_no'];

            $directIds = $this->csfCategoryIds($function, $csf, $csfReqByRef);
            $direct = $this->coverage($directIds, $statements);

            $mappedIds = $this->resolveIds($mappedRefsByFunction[$function] ?? [], $isoReqByRef);
            $mapped = $this->coverage($mappedIds, $statements);

            // Modus: bevorzugt direkte NIST-SoA, sonst abgeleitete ISO-SoA.
            if ($direct['total'] > 0) {
                $mode = 'direct';
                $chosen = $direct;
            } elseif ($mapped['total'] > 0) {
                $mode = 'mapped';
                $chosen = $mapped;
            } else {
                $mode = 'none';
                $chosen = $mapped;
            }

            $sumApplicable += $chosen['applicable'];
            $sumCovered += $chosen['covered'];

            $functions[] = [
                'ref' => $function,
                'title' => $entry['title'],
                'direct' => $direct,
                'mapped' => $mapped,
                'mode' => $mode,
                'quote' => $chosen['quote'],
                'tone' => $mode === 'none' ? 'ghost' : $this->tone($chosen['quote']),
            ];
        }

        $overallQuote = $sumApplicable > 0 ? (int) round($sumCovered * 100 / $sumApplicable) : 0;

        return [
            'has_nist' => $hasNist,
            'has_crosswalk' => $crosswalkKey !== null,
            'source_label' => $csfMeta['label'],
            'target_label' => $crosswalkMeta['label'] ?? null,
            'functions' => $functions,
            'overall_quote' => $overallQuote,
            'overall_tone' => $sumApplicable > 0 ? $this->tone($overallQuote) : 'ghost',
            'is_self_assessment' => true,
        ];
    }

    /**
     * Crosswalk-Tabelle je Geltungsbereich: je CSF-Kategorie die gemappten
     * ISO-Referenzen mit Titel und die daraus abgeleitete Abdeckung.
     *
     * @return array{
     *     label: string,
     *     source_label: string,
     *     target_label: string,
     *     version: string,
     *     as_of: ?string,
     *     rows: list<array{source_ref: string, source_title: string, function: string, targets: list<array{ref: string, title: string}>, coverage: array{total: int, applicable: int, covered: int, quote: int}}>
     * }|null
     */
    public function crosswalkForScope(IsmsScope $scope): ?array {
        if (! $this->crosswalks->has(self::CROSSWALK_KEY)) {
            return null;
        }

        $meta = $this->crosswalks->get(self::CROSSWALK_KEY);
        $mappings = $this->crosswalks->mappings(self::CROSSWALK_KEY);

        $csfTitles = $this->titlesByRef(self::CSF_PROFILE_KEY);
        $isoProfileKey = $this->profileKeyForNorm($meta['target_norm'], $meta['target_edition']);
        $isoTitles = $isoProfileKey !== null ? $this->titlesByRef($isoProfileKey) : collect();

        $statements = $this->statements($scope);
        $isoReqByRef = $this->requirementIdsByRef($meta['target_norm'], $meta['target_edition']);

        $rows = [];
        foreach ($mappings as $mapping) {
            $targets = [];
            foreach ($mapping['target_refs'] as $ref) {
                $targets[] = ['ref' => $ref, 'title' => (string) ($isoTitles[$ref] ?? '')];
            }

            $ids = $this->resolveIds($mapping['target_refs'], $isoReqByRef);

            $rows[] = [
                'source_ref' => $mapping['source_ref'],
                'source_title' => (string) ($csfTitles[$mapping['source_ref']] ?? ''),
                'function' => explode('.', $mapping['source_ref'])[0],
                'targets' => $targets,
                'coverage' => $this->coverage($ids, $statements),
            ];
        }

        return [
            'label' => $meta['label'],
            'source_label' => $meta['source_norm'] . ' ' . $meta['source_edition'],
            'target_label' => $meta['target_norm'] . ':' . $meta['target_edition'],
            'version' => $meta['version'],
            'as_of' => $meta['as_of'],
            'rows' => $rows,
        ];
    }

    /**
     * SoA-Aussagen des Geltungsbereichs, indiziert nach Anforderungs-ID.
     *
     * @return Collection<int, IsmsApplicabilityStatement>
     */
    private function statements(IsmsScope $scope): Collection {
        return IsmsApplicabilityStatement::query()
            ->where('isms_scope_id', $scope->id)
            ->get()
            ->keyBy('isms_requirement_id');
    }

    /**
     * Anforderungs-IDs einer Norm/Ausgabe (org-gescopt), indiziert nach ref_no.
     *
     * @return Collection<string, int>
     */
    private function requirementIdsByRef(string $norm, string $edition): Collection {
        return IsmsRequirement::query()
            ->where('norm', $norm)
            ->where('edition', $edition)
            ->pluck('id', 'ref_no');
    }

    /**
     * Kurztitel einer Norm (ref_no → title) aus dem Normprofil.
     *
     * @return Collection<string, string>
     */
    private function titlesByRef(string $profileKey): Collection {
        return collect($this->profiles->requirements($profileKey))
            ->mapWithKeys(fn(array $r): array => [$r['ref_no'] => $r['title']]);
    }

    /**
     * Findet den Normprofil-Key zu Norm/Ausgabe — null, wenn keiner passt.
     */
    private function profileKeyForNorm(string $norm, string $edition): ?string {
        foreach ($this->profiles->all() as $key => $meta) {
            if ($meta['norm'] === $norm && $meta['edition'] === $edition) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Gemappte ISO-Zielreferenzen je CSF-Funktion (Präfix vor dem Punkt),
     * dedupliziert.
     *
     * @return array<string, list<string>>
     */
    private function mappedTargetsByFunction(string $crosswalkKey): array {
        $byFunction = [];
        foreach ($this->crosswalks->mappings($crosswalkKey) as $mapping) {
            $function = explode('.', $mapping['source_ref'])[0];
            foreach ($mapping['target_refs'] as $ref) {
                $byFunction[$function][$ref] = true;
            }
        }

        return array_map(static fn(array $refs): array => array_keys($refs), $byFunction);
    }

    /**
     * Existierende Anforderungs-IDs der CSF-Kategorien einer Funktion.
     *
     * @param  list<array{ref_no: string, title: string}>  $csf
     * @param  Collection<string, int>  $csfReqByRef
     * @return list<int>
     */
    private function csfCategoryIds(string $function, array $csf, Collection $csfReqByRef): array {
        $refs = [];
        foreach ($csf as $entry) {
            if (str_starts_with($entry['ref_no'], $function . '.')) {
                $refs[] = $entry['ref_no'];
            }
        }

        return $this->resolveIds($refs, $csfReqByRef);
    }

    /**
     * Löst Referenznummern auf existierende Anforderungs-IDs auf.
     *
     * @param  list<string>  $refs
     * @param  Collection<string, int>  $reqByRef
     * @return list<int>
     */
    private function resolveIds(array $refs, Collection $reqByRef): array {
        $ids = [];
        foreach ($refs as $ref) {
            $id = $reqByRef[$ref] ?? null;
            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Abdeckung über eine Menge von Anforderungs-IDs: gesamt, anwendbar,
     * abgedeckt (implemented/partial) und Quote (abgedeckt/anwendbar).
     *
     * @param  list<int>  $requirementIds
     * @param  Collection<int, IsmsApplicabilityStatement>  $statements
     * @return array{total: int, applicable: int, covered: int, quote: int}
     */
    private function coverage(array $requirementIds, Collection $statements): array {
        $total = count($requirementIds);
        $applicable = 0;
        $covered = 0;

        foreach ($requirementIds as $id) {
            $statement = $statements[$id] ?? null;
            if ($statement === null || ! $statement->applicable) {
                continue;
            }

            $applicable++;
            if (in_array($statement->implementation_status, [
                ControlImplementationStatus::Implemented,
                ControlImplementationStatus::Partial,
            ], true)) {
                $covered++;
            }
        }

        return [
            'total' => $total,
            'applicable' => $applicable,
            'covered' => $covered,
            'quote' => $applicable > 0 ? (int) round($covered * 100 / $applicable) : 0,
        ];
    }

    /** DaisyUI-Tone aus einer Quote (grün/gelb/rot). */
    private function tone(int $quote): string {
        return match (true) {
            $quote >= self::QUOTE_GREEN => 'success',
            $quote >= self::QUOTE_AMBER => 'warning',
            default => 'error',
        };
    }
}
