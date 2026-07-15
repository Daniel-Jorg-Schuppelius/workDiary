<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdvisoryImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{AdvisoryFormat, Exploitability, VulnerabilitySource, VulnerabilityStatus};
use App\Models\Isms\{IsmsAdvisory, IsmsSoftwareProduct, IsmsVulnerability};
use App\Models\{Organization, User};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/**
 * Advisory-Import + Inventar-/SBOM-Abgleich (Feature 044, MVP 2).
 *
 * Parst CSAF- bzw. VEX-Dokumente NATIV per json_decode (kein zusätzliches
 * Composer-Paket) und erzeugt/aktualisiert Schwachstelleneinträge im
 * Register. Jede betroffene Komponente wird gegen das Softwareinventar
 * ({@see IsmsSoftwareProduct} nach name/version) UND optional gegen die letzte
 * Release-SBOM (storage/app/sbom/workdiary-latest.cdx.json, CycloneDX-
 * components purl/name/version) abgeglichen.
 *
 * Gelesene Felder (CSAF):
 * - document.title, document.tracking.id
 * - product_tree (full_product_names / branches) → Produktname + Version
 * - vulnerabilities[].cve, .title
 * - vulnerabilities[].scores[].cvss_v3.baseScore (Severity-Ableitung)
 * - vulnerabilities[].product_status: known_affected / known_not_affected /
 *   fixed (CSAF und CSAF-VEX-Profil teilen diese Struktur)
 * - vulnerabilities[].flags / .threats (VEX-Begründung für not_affected)
 *
 * 044-KERNREGEL: Ein gefundener Treffer wird NIE automatisch als „ausnutzbar"
 * markiert. known_affected ⇒ Status open, exploitability=underInvestigation.
 * known_not_affected (VEX) ⇒ Status notAffected, exploitability=notExploitable
 * MIT der VEX-Begründung als Pflichtnotiz. Die endgültige Betroffenheits-
 * Entscheidung bleibt eine bewusste Nutzeraktion (VulnerabilityService).
 *
 * Nachweis: das Original-Advisory wird als Datei (local-Disk) plus SHA-256 in
 * isms_advisories abgelegt. Re-Import ist idempotent über
 * (organization_id, file_hash): dieselbe Datei legt keine zweite Advisory-Zeile
 * an und dedupliziert Schwachstellen über (identifier, affected_component).
 */
class AdvisoryImportService {
    private const STORAGE_DIR = 'isms/advisories';

    public function __construct(
        private readonly VulnerabilityService $vulnerabilities,
    ) {}

    /**
     * Importiert ein CSAF-Dokument (auch CSAF-VEX-Profil) und gleicht es gegen
     * Inventar + SBOM ab. Liefert die erzeugte/gefundene Advisory-Zeile.
     *
     * @throws ValidationException bei ungültigem JSON
     */
    public function importCsaf(string $jsonContent, Organization $organization, User $importer, AdvisoryFormat $format = AdvisoryFormat::Csaf): IsmsAdvisory {
        $decoded = json_decode($jsonContent, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'advisory' => __('isms.error.advisory_invalid_json'),
            ]);
        }
        // CSAF-Dokumente sind JSON-Objekte ⇒ auf string-indiziertes Array verengen.
        $document = $this->stringKeyed($decoded);

        $hash = CryptoHelper::hash($jsonContent);

        // Re-Import-Idempotenz: identische Datei ⇒ bestehende Advisory zurückgeben.
        $existing = IsmsAdvisory::query()
            ->where('organization_id', $organization->id)
            ->where('file_hash', $hash)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $title = $this->stringAt($document, ['document', 'title']) ?? __('isms.advisory.untitled');
        $trackingId = $this->stringAt($document, ['document', 'tracking', 'id']);
        $productMap = $this->buildProductMap($document);
        $sbomComponents = $this->sbomComponents();

        return DB::transaction(function () use ($jsonContent, $organization, $importer, $format, $hash, $title, $trackingId, $document, $productMap, $sbomComponents): IsmsAdvisory {
            $path = self::STORAGE_DIR . '/' . $hash . '.json';
            Storage::disk('local')->put($path, $jsonContent);

            $advisory = IsmsAdvisory::query()->create([
                'organization_id' => $organization->id,
                'title' => mb_substr($title, 0, 250),
                'format' => $format->value,
                'document_id_ref' => $trackingId,
                'file_path' => $path,
                'file_hash' => $hash,
                'imported_by_user_id' => $importer->id,
                'vuln_count' => 0,
            ]);

            $count = $this->processVulnerabilities($document, $organization, $importer, $advisory, $productMap, $sbomComponents);

            $advisory->update(['vuln_count' => $count]);

            return $advisory->refresh();
        });
    }

    /**
     * Verarbeitet die vulnerabilities[]-Liste und legt je betroffener
     * Komponente einen Eintrag an (bzw. aktualisiert ihn beim Re-Import).
     *
     * @param  array<string, mixed>  $document
     * @param  array<string, array{name: string, version: string|null}>  $productMap
     * @param  list<array{name: string, version: string|null, purl: string|null}>  $sbomComponents
     */
    private function processVulnerabilities(array $document, Organization $organization, User $importer, IsmsAdvisory $advisory, array $productMap, array $sbomComponents): int {
        $vulns = $document['vulnerabilities'] ?? [];
        if (! is_array($vulns)) {
            return 0;
        }

        $count = 0;
        foreach ($vulns as $vulnData) {
            if (! is_array($vulnData)) {
                continue;
            }
            $vuln = $this->stringKeyed($vulnData);

            $cve = $this->stringAt($vuln, ['cve']);
            $vulnTitle = $this->stringAt($vuln, ['title']) ?? $cve ?? __('isms.advisory.untitled');
            $cvss = $this->extractCvss($vuln);

            $productStatus = $this->stringKeyed($vuln['product_status'] ?? null);
            $vexReason = $this->vexReason($vuln);

            // known_affected ⇒ open / underInvestigation (NIE automatisch exploitable).
            foreach ($this->productIds($productStatus, 'known_affected') as $productId) {
                $count += $this->upsertVulnerability(
                    $organization,
                    $importer,
                    $advisory,
                    $cve,
                    $vulnTitle,
                    $cvss,
                    $this->componentLabel($productId, $productMap, $sbomComponents),
                    $this->matchInventory($organization, $productId, $productMap),
                    VulnerabilityStatus::Open,
                    Exploitability::UnderInvestigation,
                    null,
                ) ? 1 : 0;
            }

            // known_not_affected (VEX) ⇒ notAffected / notExploitable MIT Begründung.
            foreach ($this->productIds($productStatus, 'known_not_affected') as $productId) {
                $count += $this->upsertVulnerability(
                    $organization,
                    $importer,
                    $advisory,
                    $cve,
                    $vulnTitle,
                    $cvss,
                    $this->componentLabel($productId, $productMap, $sbomComponents),
                    $this->matchInventory($organization, $productId, $productMap),
                    VulnerabilityStatus::NotAffected,
                    Exploitability::NotExploitable,
                    $vexReason ?? __('isms.advisory.vex_not_affected_default'),
                ) ? 1 : 0;
            }
        }

        return $count;
    }

    /**
     * Legt einen Schwachstelleneintrag an oder aktualisiert ihn (Dedup über
     * organization_id + identifier + affected_component). severity wird aus
     * cvss abgeleitet (VulnerabilityService::resolveSeverity).
     *
     * @return bool true, wenn ein Eintrag erzeugt/aktualisiert wurde
     */
    private function upsertVulnerability(
        Organization $organization,
        User $importer,
        IsmsAdvisory $advisory,
        ?string $identifier,
        string $title,
        ?float $cvss,
        string $component,
        ?int $productId,
        VulnerabilityStatus $status,
        Exploitability $exploitability,
        ?string $note,
    ): bool {
        $severity = $this->vulnerabilities->resolveSeverity(null, $cvss);

        $query = IsmsVulnerability::query()
            ->withTrashed()
            ->where('organization_id', $organization->id)
            ->where('affected_component', $component);
        if ($identifier !== null) {
            $query->where('identifier', $identifier);
        } else {
            $query->whereNull('identifier');
        }
        /** @var IsmsVulnerability|null $existing */
        $existing = $query->first();

        if ($existing !== null) {
            // Re-Import: Stammdaten/Quelle aktualisieren, aber eine bereits
            // GETROFFENE bewusste Ausnutzbarkeits-Entscheidung NICHT überschreiben.
            $existing->restore();
            $existing->update([
                'title' => $title,
                'cvss_score' => $cvss,
                'severity' => $severity->value,
                'isms_software_product_id' => $productId ?? $existing->isms_software_product_id,
                'isms_advisory_id' => $advisory->id,
                'source' => VulnerabilitySource::AdvisoryImport->value,
                'advisory_ref' => $identifier ?? $advisory->document_id_ref,
            ]);

            return true;
        }

        $this->vulnerabilities->create($importer, [
            'title' => $title,
            'identifier' => $identifier,
            'cvss_score' => $cvss,
            'severity' => $severity->value,
            'affected_component' => $component,
            'isms_software_product_id' => $productId,
            'isms_advisory_id' => $advisory->id,
            'status' => $status->value,
            'exploitability' => $exploitability->value,
            'exploitability_note' => $note,
            'source' => VulnerabilitySource::AdvisoryImport->value,
            'advisory_ref' => $identifier ?? $advisory->document_id_ref,
        ]);

        return true;
    }

    /**
     * Versucht, einen CSAF-Produkt-Treffer im Softwareinventar (nach Name,
     * optional Version) zu finden; liefert die Inventar-Produkt-ID, sonst
     * null. Der Abgleich gegen die Release-SBOM läuft separat über
     * {@see self::componentLabel()} (kennzeichnet ausgelieferte Komponenten).
     *
     * @param  array<string, array{name: string, version: string|null}>  $productMap
     */
    private function matchInventory(Organization $organization, string $productId, array $productMap): ?int {
        $product = $productMap[$productId] ?? null;
        if ($product === null) {
            return null;
        }

        $name = $product['name'];
        $version = $product['version'];

        $query = IsmsSoftwareProduct::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
        if ($version !== null && $version !== '') {
            $query->where('product_version', $version);
        }
        $match = $query->value('id');
        if (is_numeric($match)) {
            return (int) $match;
        }

        // Name-only-Fallback im Inventar (Version weicht ab/fehlt).
        $nameOnly = IsmsSoftwareProduct::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');

        return is_numeric($nameOnly) ? (int) $nameOnly : null;
    }

    /**
     * CSAF product_status[$bucket] → Liste der Produkt-Referenz-IDs.
     *
     * @param  array<string, mixed>  $productStatus
     * @return list<string>
     */
    private function productIds(array $productStatus, string $bucket): array {
        $ids = $productStatus[$bucket] ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn(mixed $id): string => is_scalar($id) ? (string) $id : '', $ids),
            static fn(string $id): bool => $id !== '',
        ));
    }

    /**
     * Baut die CSAF-product_tree auf eine Map product_id ⇒ {name, version}.
     * Unterstützt full_product_names UND branches (rekursiv, Version aus dem
     * product_version-Branch).
     *
     * @param  array<string, mixed>  $document
     * @return array<string, array{name: string, version: string|null}>
     */
    private function buildProductMap(array $document): array {
        $tree = $document['product_tree'] ?? [];
        if (! is_array($tree)) {
            return [];
        }

        $map = [];

        $fullNames = $tree['full_product_names'] ?? [];
        if (is_array($fullNames)) {
            foreach ($fullNames as $entryData) {
                if (! is_array($entryData)) {
                    continue;
                }
                $entry = $this->stringKeyed($entryData);
                $id = $this->stringAt($entry, ['product_id']);
                $name = $this->stringAt($entry, ['name']);
                if ($id === null || $name === null) {
                    continue;
                }
                $map[$id] = ['name' => $this->baseName($name), 'version' => $this->versionFromName($name)];
            }
        }

        if (is_array($tree['branches'] ?? null)) {
            $this->collectBranches(array_values($tree['branches']), null, $map);
        }

        return $map;
    }

    /**
     * Rekursive CSAF-branches-Auswertung: der product_version-Branch trägt die
     * Version, sein product.product_id verweist auf den Eintrag.
     *
     * @param  array<int, mixed>  $branches
     * @param  array<string, array{name: string, version: string|null}>  $map
     */
    private function collectBranches(array $branches, ?string $inheritedVersion, array &$map): void {
        foreach ($branches as $branchData) {
            if (! is_array($branchData)) {
                continue;
            }
            $branch = $this->stringKeyed($branchData);
            $category = $this->stringAt($branch, ['category']);
            $branchName = $this->stringAt($branch, ['name']);
            $version = $category === 'product_version' ? $branchName : $inheritedVersion;

            $product = $this->stringKeyed($branch['product'] ?? null);
            if ($product !== []) {
                $id = $this->stringAt($product, ['product_id']);
                $name = $this->stringAt($product, ['name']);
                if ($id !== null && $name !== null) {
                    $map[$id] = [
                        'name' => $this->baseName($name),
                        'version' => $version ?? $this->versionFromName($name),
                    ];
                }
            }

            if (is_array($branch['branches'] ?? null)) {
                $this->collectBranches(array_values($branch['branches']), $version, $map);
            }
        }
    }

    /**
     * Liest die letzte Release-SBOM (CycloneDX) als Komponentenliste, falls
     * vorhanden — für den Abgleich gegen die produktbezogene Stückliste.
     *
     * @return list<array{name: string, version: string|null, purl: string|null}>
     */
    private function sbomComponents(): array {
        $path = 'sbom/' . SbomGenerator::latestAlias();
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get($path), true);
        if (! is_array($decoded) || ! is_array($decoded['components'] ?? null)) {
            return [];
        }

        $components = [];
        foreach ($decoded['components'] as $component) {
            if (! is_array($component)) {
                continue;
            }
            $name = $component['name'] ?? null;
            if (! is_scalar($name)) {
                continue;
            }
            $version = $component['version'] ?? null;
            $purl = $component['purl'] ?? null;
            $components[] = [
                'name' => (string) $name,
                'version' => is_scalar($version) ? (string) $version : null,
                'purl' => is_scalar($purl) ? (string) $purl : null,
            ];
        }

        return $components;
    }

    /**
     * Komponenten-Label für den Schwachstelleneintrag (Name@Version aus der
     * product_tree, sonst die rohe Produkt-ID). Taucht die Komponente in der
     * letzten Release-SBOM auf, wird das Label mit „(SBOM)" markiert — so ist
     * der SBOM-Abgleich am Eintrag sichtbar.
     *
     * @param  array<string, array{name: string, version: string|null}>  $productMap
     * @param  list<array{name: string, version: string|null, purl: string|null}>  $sbomComponents
     */
    private function componentLabel(string $productId, array $productMap, array $sbomComponents): string {
        $product = $productMap[$productId] ?? null;
        if ($product === null) {
            return mb_substr($productId, 0, 250);
        }

        $label = $product['name'];
        if (($product['version'] ?? null) !== null && $product['version'] !== '') {
            $label .= '@' . $product['version'];
        }

        if ($this->matchesSbom($product['name'], $product['version'] ?? null, $sbomComponents)) {
            $label .= ' (SBOM)';
        }

        return mb_substr($label, 0, 250);
    }

    /**
     * Prüft, ob eine Komponente (Name, optional Version) in der letzten
     * Release-SBOM enthalten ist (Name-Match, Version bestätigt zusätzlich).
     *
     * @param  list<array{name: string, version: string|null, purl: string|null}>  $sbomComponents
     */
    private function matchesSbom(string $name, ?string $version, array $sbomComponents): bool {
        $needle = mb_strtolower($name);
        foreach ($sbomComponents as $component) {
            if (mb_strtolower($component['name']) !== $needle) {
                continue;
            }
            if ($version === null || $version === '' || $component['version'] === null) {
                return true;
            }
            if ($component['version'] === $version) {
                return true;
            }
        }

        return false;
    }

    /**
     * VEX-Begründung für known_not_affected aus den CSAF-Strukturen.
     * Bevorzugt die menschenlesbare Begründung (threats[].details mit
     * category=impact); fällt sonst auf das maschinenlesbare flags[].label
     * zurück (z. B. vulnerable_code_not_in_execute_path).
     *
     * @param  array<string, mixed>  $vuln
     */
    private function vexReason(array $vuln): ?string {
        $threats = $vuln['threats'] ?? [];
        if (is_array($threats)) {
            foreach ($threats as $threatData) {
                if (! is_array($threatData)) {
                    continue;
                }
                $threat = $this->stringKeyed($threatData);
                $category = $this->stringAt($threat, ['category']);
                $details = $this->stringAt($threat, ['details']);
                if ($category === 'impact' && $details !== null) {
                    return mb_substr($details, 0, 10000);
                }
            }
        }

        $flags = $vuln['flags'] ?? [];
        if (is_array($flags)) {
            foreach ($flags as $flag) {
                if (is_array($flag) && isset($flag['label']) && is_string($flag['label']) && $flag['label'] !== '') {
                    return mb_substr($flag['label'], 0, 10000);
                }
            }
        }

        return null;
    }

    /**
     * CVSS-v3-Basisscore aus scores[].cvss_v3.baseScore (erster Treffer).
     *
     * @param  array<string, mixed>  $vuln
     */
    private function extractCvss(array $vuln): ?float {
        $scores = $vuln['scores'] ?? [];
        if (! is_array($scores)) {
            return null;
        }
        foreach ($scores as $score) {
            if (! is_array($score)) {
                continue;
            }
            $cvssV3 = $score['cvss_v3'] ?? null;
            if (! is_array($cvssV3)) {
                continue;
            }
            $base = $cvssV3['baseScore'] ?? null;
            if (is_numeric($base)) {
                return round((float) $base, 1);
            }
        }

        return null;
    }

    /** Basisname ohne angehängte Version (z. B. „Foo 1.2.3" ⇒ „Foo"). */
    private function baseName(string $name): string {
        return trim((string) preg_replace('/\s+\d[\w.\-]*$/', '', $name));
    }

    /** Versionsanteil aus „Foo 1.2.3" ⇒ „1.2.3", sonst null. */
    private function versionFromName(string $name): ?string {
        if (preg_match('/\s+(\d[\w.\-]*)$/', $name, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Verengt einen mixed-Wert auf ein string-indiziertes Array (JSON-Objekt).
     * Nicht-Arrays ergeben ein leeres Array; numerische Schlüssel (JSON-
     * Sonderfall) werden verworfen — alle ausgewerteten CSAF-/CycloneDX-Pfade
     * laufen über benannte Schlüssel.
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(mixed $value): array {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Liest einen verschachtelten String-Wert aus einem Array entlang eines
     * Schlüsselpfads; null, wenn der Pfad fehlt oder kein String/Skalar ist.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private function stringAt(array $data, array $path): ?string {
        $value = $data;
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
