<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReleaseReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Release;

use App\Models\Auth\SecurityAdvisory;
use App\Models\Platform\IntegrityCheck;
use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\Helper\FileSystem\File;

/**
 * Sicherheits- und Datenschutzbericht je Release (MVP-946): Changelog-
 * Abschnitt der Version, datenschutzrelevante Einträge, Abhängigkeiten aus
 * der SBOM, offene Sicherheitshinweise und letzte Integritätsprüfung — ohne
 * Netzabfrage, als Markdown zur Ablage beim Release.
 */
final class ReleaseReportService {
    /** Stichworte für datenschutzrelevante Changelog-Einträge. */
    private const PRIVACY_TERMS = ['datenschutz', 'dsgvo', 'personenbezog', 'auskunft', 'lösch', 'anonymis', 'verschlüssel', 'einwilligung', 'aufbewahr', 'privacy', 'gdpr'];

    public function __construct(private readonly SbomGenerator $sbom) {}

    /**
     * @return array{version: string, entries: list<string>, privacy: list<string>, components: int, advisories: list<array{package: string, severity: string, external_id: string, fixed_in: ?string}>, integrity: ?array{status: string, ran_at: string, files: int, findings: int}}
     */
    public function collect(?string $version = null, ?string $changelog = null): array {
        $version ??= (string) config('app.version', '0.1.0-dev');
        $entries = $this->changelogEntries($changelog ?? (File::isFile(base_path('CHANGELOG.md')) ? File::read(base_path('CHANGELOG.md')) : ''), $version);
        $privacy = array_values(array_filter($entries, static function (string $entry): bool {
            $lower = mb_strtolower($entry);
            foreach (self::PRIVACY_TERMS as $term) {
                if (str_contains($lower, $term)) {
                    return true;
                }
            }

            return false;
        }));
        $check = IntegrityCheck::query()->latest('ran_at')->first();

        return [
            'version' => $version,
            'entries' => $entries,
            'privacy' => $privacy,
            'components' => count((array) ($this->sbom->generate()['components'] ?? [])),
            'advisories' => array_values(SecurityAdvisory::query()->open()->orderBy('severity')->get()
                ->map(static fn (SecurityAdvisory $a): array => ['package' => $a->package, 'severity' => $a->severity, 'external_id' => $a->external_id, 'fixed_in' => $a->fixed_in])->all()),
            'integrity' => $check !== null ? [
                'status' => $check->status->value,
                'ran_at' => $check->ran_at->toIso8601String(),
                'files' => $check->files_checked,
                'findings' => $check->added_count + $check->modified_count + $check->deleted_count,
            ] : null,
        ];
    }

    /** @param array{version: string, entries: list<string>, privacy: list<string>, components: int, advisories: list<array{package: string, severity: string, external_id: string, fixed_in: ?string}>, integrity: ?array{status: string, ran_at: string, files: int, findings: int}} $report */
    public function markdown(array $report): string {
        $lines = ['# ' . __('release_report.title', ['version' => $report['version']]), '', __('release_report.generated', ['date' => now()->format('d.m.Y H:i')]), ''];
        $lines[] = '## ' . __('release_report.section.privacy');
        $lines = [...$lines, ...($report['privacy'] !== [] ? array_map(static fn (string $e): string => '- ' . $e, $report['privacy']) : ['- ' . __('release_report.none')]), ''];
        $lines[] = '## ' . __('release_report.section.advisories');
        $lines = [...$lines, ...($report['advisories'] !== [] ? array_map(static fn (array $a): string => sprintf('- %s (%s, %s)%s', $a['package'], $a['severity'], $a['external_id'], $a['fixed_in'] !== null ? ' → ' . $a['fixed_in'] : ''), $report['advisories']) : ['- ' . __('release_report.no_advisories')]), ''];
        $lines[] = '## ' . __('release_report.section.integrity');
        $lines[] = $report['integrity'] !== null
            ? '- ' . __('release_report.integrity', ['status' => $report['integrity']['status'], 'date' => $report['integrity']['ran_at'], 'files' => $report['integrity']['files'], 'findings' => $report['integrity']['findings']])
            : '- ' . __('release_report.no_integrity');
        $lines[] = '';
        $lines[] = '## ' . __('release_report.section.dependencies');
        $lines[] = '- ' . __('release_report.components', ['count' => $report['components']]);
        $lines[] = '';
        $lines[] = '## ' . __('release_report.section.changes');
        $lines = [...$lines, ...($report['entries'] !== [] ? array_map(static fn (string $e): string => '- ' . $e, $report['entries']) : ['- ' . __('release_report.none')])];

        return implode("\n", $lines) . "\n";
    }

    /** @return list<string> Einträge des Abschnitts der Version (sonst „Unreleased“), je Aufzählungspunkt eine Zeile */
    private function changelogEntries(string $changelog, string $version): array {
        $sections = preg_split('/^## /m', $changelog) ?: [];
        $section = null;
        foreach ($sections as $candidate) {
            if (str_starts_with($candidate, '[' . $version . ']')) {
                $section = $candidate;

                break;
            }
            if ($section === null && str_starts_with($candidate, '[Unreleased]')) {
                $section = $candidate;
            }
        }
        if ($section === null) {
            return [];
        }
        $entries = [];
        foreach (preg_split('/\n(?=- )/', $section) ?: [] as $block) {
            $block = trim($block);
            if (str_starts_with($block, '- ')) {
                $block = explode("\n#", $block)[0];
                $entries[] = StringHelper::normalizeWhitespace(substr($block, 2));
            }
        }

        return $entries;
    }
}
