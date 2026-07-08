<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VexExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Isms;

use App\Enums\Isms\{Exploitability, VulnerabilityStatus};
use App\Models\Isms\IsmsVulnerability;
use App\Models\Organization;

/**
 * CSAF-VEX-Generator für eigene Releases (Nachtrag 044c, AR §22): erzeugt
 * ein Dokument im csaf_vex-Profil aus den bewerteten Schwachstellen des
 * Registers. Pflichten des Profils werden eingehalten:
 *  - je Vulnerability genau ein product_status (fixed/known_affected/
 *    known_not_affected/under_investigation),
 *  - known_not_affected ⇒ Impact-Statement (threats/impact aus der
 *    Ausnutzbarkeits-Begründung),
 *  - known_affected ⇒ Action-Statement (remediations).
 * OpenVEX/CycloneDX-VEX bewusst nicht (CSAF-VEX ist laut AR §22 das
 * Primärformat; Mapping wäre verlustbehaftet).
 */
class VexExportService {
    public const PRODUCT_ID = 'WORKDIARY';

    /** @return array<string, mixed> */
    public function generate(Organization $organization): array {
        $version = (string) config('app.version', '0.1.0-dev');
        $today = now()->toIso8601String();

        $vulnerabilities = IsmsVulnerability::query()
            ->where('organization_id', $organization->id)
            ->orderBy('identifier')
            ->get()
            ->map(fn(IsmsVulnerability $vulnerability): array => $this->vexVulnerability($vulnerability))
            ->all();

        return [
            'document' => [
                'category' => 'csaf_vex',
                'csaf_version' => '2.0',
                'title' => sprintf('WorkDiary %s — VEX (Vulnerability Exploitability eXchange)', $version),
                'lang' => 'de-DE',
                'publisher' => [
                    'category' => 'vendor',
                    'name' => $organization->name,
                    'namespace' => (string) config('app.url', 'https://workdiary.invalid'),
                ],
                'tracking' => [
                    'id' => 'workdiary-vex-' . $version . '-' . now()->format('Ymd'),
                    'status' => 'final',
                    'version' => '1',
                    'initial_release_date' => $today,
                    'current_release_date' => $today,
                    'revision_history' => [
                        ['number' => '1', 'date' => $today, 'summary' => 'Initiale Ausgabe'],
                    ],
                ],
                'notes' => [
                    [
                        'category' => 'summary',
                        'title' => 'Geltungsbereich',
                        'text' => 'Ausnutzbarkeitsbewertung der im Schwachstellenregister geführten Einträge für dieses WorkDiary-Release.',
                    ],
                ],
            ],
            'product_tree' => [
                'full_product_names' => [
                    [
                        'product_id' => self::PRODUCT_ID,
                        'name' => 'WorkDiary ' . $version,
                        'product_identification_helper' => [
                            'purl' => 'pkg:generic/workdiary@' . $version,
                        ],
                    ],
                ],
            ],
            'vulnerabilities' => $vulnerabilities,
        ];
    }

    public function fileName(): string {
        return 'workdiary-' . (string) config('app.version', '0.1.0-dev') . '-vex.csaf.json';
    }

    /** @return array<string, mixed> */
    private function vexVulnerability(IsmsVulnerability $vulnerability): array {
        $identifier = (string) $vulnerability->getAttribute('identifier');
        $note = trim((string) ($vulnerability->getAttribute('exploitability_note') ?? ''));

        $entry = [
            'notes' => [[
                'category' => 'description',
                'title' => $identifier,
                'text' => trim(($vulnerability->getAttribute('title') ?? $identifier) . ' — Komponente: ' . ((string) ($vulnerability->getAttribute('affected_component') ?? 'unbekannt'))),
            ]],
        ];

        if (preg_match('/^CVE-\d{4}-\d{4,}$/', $identifier) === 1) {
            $entry['cve'] = $identifier;
        } else {
            $entry['ids'] = [['system_name' => 'other', 'text' => $identifier]];
        }

        [$statusKey, $extra] = $this->productStatus($vulnerability, $note);
        $entry['product_status'] = [$statusKey => [self::PRODUCT_ID]];

        return array_merge($entry, $extra);
    }

    /**
     * Mapping Registerstatus → CSAF-product_status inkl. Pflichtangaben.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function productStatus(IsmsVulnerability $vulnerability, string $note): array {
        $status = $vulnerability->getAttribute('status');
        $exploitability = $vulnerability->getAttribute('exploitability');

        // Nicht betroffen / nicht ausnutzbar ⇒ known_not_affected + Impact-Statement (Pflicht).
        if ($status === VulnerabilityStatus::NotAffected || $exploitability === Exploitability::NotExploitable) {
            return ['known_not_affected', [
                'threats' => [[
                    'category' => 'impact',
                    'details' => $note !== '' ? $note : 'Die verwundbare Funktionalität wird in dieser Auslieferung nicht verwendet.',
                ]],
            ]];
        }

        if ($status === VulnerabilityStatus::Resolved) {
            return ['fixed', []];
        }

        if ($exploitability === Exploitability::UnderInvestigation || $status === VulnerabilityStatus::UnderReview) {
            return ['under_investigation', []];
        }

        // Offen/mitigierend/akzeptiert ⇒ known_affected + Action-Statement (Pflicht).
        $details = match (true) {
            $status === VulnerabilityStatus::Mitigating => 'Abhilfemaßnahmen sind in Umsetzung.' . ($note !== '' ? ' ' . $note : ''),
            $status === VulnerabilityStatus::Accepted => 'Risiko bewusst akzeptiert.' . ($note !== '' ? ' ' . $note : ''),
            default => $note !== '' ? $note : 'Behebung geplant; bis dahin Standard-Härtungsmaßnahmen anwenden.',
        };

        return ['known_affected', [
            'remediations' => [[
                'category' => 'workaround',
                'details' => $details,
                'product_ids' => [self::PRODUCT_ID],
            ]],
        ]];
    }
}
