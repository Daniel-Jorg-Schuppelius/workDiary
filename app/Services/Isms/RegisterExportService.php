<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RegisterExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Models\Isms\{IsmsApplicabilityStatement, IsmsControl, IsmsRequirement, IsmsRisk, IsmsRiskAssessment, IsmsScope};
use App\Models\User;
use App\Support\Toolkit\CsvFacade;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Direkt-Exporte der ISMS-Register (Feature 044, MVP 1, „Versionierte
 * JSON-, CSV- und druckbare Exporte"): Risikoregister, Anforderungen/SoA
 * (je Geltungsbereich) und Maßnahmen als JSON oder CSV.
 *
 * - JSON trägt einen meta-Block (organisation, scope, generated_at,
 *   app_version — Muster {@see AuditPackageService::metaSection()}).
 * - CSV mit Semikolon, UTF-8-BOM und kommentiertem Kopf (Muster
 *   {@see \App\Services\Finance\Targets\FileTarget}).
 * - „Versioniert" im Sinne der Feature-Doku erfüllt der unveränderliche
 *   Auditpaket-Snapshot ({@see AuditPackageService}); die Direkt-Exporte
 *   hier tragen generated_at als ehrlichen Datenstand — bewusst KEIN
 *   eigenes Versions-/Tabellenmodell.
 */
class RegisterExportService {
    public const FORMATS = ['json', 'csv'];

    public const REGISTER_RISKS = 'risks';

    public const REGISTER_SOA = 'soa';

    public const REGISTER_CONTROLS = 'controls';

    private const BOM = "\xEF\xBB\xBF";

    // ── Registerstände (Spalten + Zeilen) ──────────────────────────────

    /**
     * Risikoregister: alle Risiken der Organisation inkl. jüngster
     * freigegebener Netto-Bewertung (Ablauf-/Reviewdatum).
     *
     * @return array{columns: array<string, string>, rows: array<int, array<string, scalar|null>>}
     */
    public function riskRegister(): array {
        $rows = IsmsRisk::query()
            ->with([
                'owner:id,name',
                'scope:id,name',
                'controls:id,title',
                'assessments' => fn($q) => $q
                    ->approvedNet()
                    ->orderByDesc('assessment_no'),
            ])
            ->orderBy('risk_no')
            ->get()
            ->map(function (IsmsRisk $risk): array {
                /** @var IsmsRiskAssessment|null $latestNet */
                $latestNet = $risk->assessments->first();

                return [
                    'no' => $risk->displayNo(),
                    'title' => $risk->title,
                    'scope' => $risk->scope?->name,
                    'category' => $risk->category->value,
                    'status' => $risk->status->value,
                    'treatment' => $risk->treatment->value,
                    'likelihood' => $risk->likelihood,
                    'impact' => $risk->impact,
                    'score' => $risk->score,
                    'owner' => $risk->owner?->name,
                    'review_due_on' => $risk->review_due_on?->toDateString(),
                    'assessment_valid_until' => $latestNet?->valid_until?->toDateString(),
                    'controls' => $risk->controls->pluck('title')->implode(', '),
                ];
            })
            ->values()
            ->all();

        return [
            'columns' => [
                'no' => (string) __('isms.field.risk_no'),
                'title' => (string) __('isms.field.title'),
                'scope' => (string) __('isms.field.scope'),
                'category' => (string) __('isms.field.category'),
                'status' => (string) __('isms.field.status'),
                'treatment' => (string) __('isms.field.treatment'),
                'likelihood' => (string) __('isms.field.likelihood'),
                'impact' => (string) __('isms.field.impact'),
                'score' => (string) __('isms.field.score'),
                'owner' => (string) __('isms.field.owner'),
                'review_due_on' => (string) __('isms.field.review_due_on'),
                'assessment_valid_until' => (string) __('isms.field.assessment_valid_until'),
                'controls' => (string) __('isms.field.controls'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Anforderungen/SoA eines Geltungsbereichs: je Aussage Normreferenz,
     * Anwendbarkeit, Begründung, Umsetzungsstatus, Nachweis und gemappte
     * Maßnahmen (natürliche Ref-Sortierung wie auf der Seite).
     *
     * @return array{columns: array<string, string>, rows: array<int, array<string, scalar|null>>}
     */
    public function soaRegister(IsmsScope $scope): array {
        $rows = IsmsApplicabilityStatement::query()
            ->where('isms_scope_id', $scope->id)
            ->with(['requirement.controls' => fn($q) => $q->orderBy('title')])
            ->get()
            ->sort(fn(IsmsApplicabilityStatement $a, IsmsApplicabilityStatement $b): int => strcmp((string) $a->requirement?->norm, (string) $b->requirement?->norm)
                ?: strnatcmp((string) $a->requirement?->ref_no, (string) $b->requirement?->ref_no))
            ->values()
            ->map(fn(IsmsApplicabilityStatement $statement): array => [
                'norm' => $statement->requirement?->normLabel(),
                'ref_no' => $statement->requirement?->ref_no,
                'title' => $statement->requirement?->title,
                'applicable' => $statement->applicable ? 1 : 0,
                'justification' => $statement->justification,
                'implementation_status' => $statement->implementation_status->value,
                'evidence_note' => $statement->evidence_note,
                'controls' => $statement->requirement?->controls->pluck('title')->implode(', ') ?? '',
            ])
            ->all();

        return [
            'columns' => [
                'norm' => (string) __('isms.field.norm'),
                'ref_no' => (string) __('isms.field.ref_no'),
                'title' => (string) __('isms.field.title'),
                'applicable' => (string) __('isms.field.applicable'),
                'justification' => (string) __('isms.field.justification'),
                'implementation_status' => (string) __('isms.field.implementation_status'),
                'evidence_note' => (string) __('isms.field.evidence_note'),
                'controls' => (string) __('isms.field.controls'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Maßnahmenregister: normneutrale Maßnahmen mit Umsetzungsstatus,
     * Owner, Nachweis-Notiz und den Referenzen der erfüllten
     * Anforderungen sowie den verknüpften Risiken.
     *
     * @return array{columns: array<string, string>, rows: array<int, array<string, scalar|null>>}
     */
    public function controlRegister(): array {
        $rows = IsmsControl::query()
            ->with(['owner:id,name', 'requirements', 'risks'])
            ->orderBy('title')
            ->get()
            ->map(fn(IsmsControl $control): array => [
                'title' => $control->title,
                'implementation_status' => $control->implementation_status->value,
                'owner' => $control->owner?->name,
                'evidence_note' => $control->evidence_note,
                'requirements' => $control->requirements
                    ->map(fn(IsmsRequirement $r): string => $r->normLabel() . ' ' . $r->ref_no)
                    ->implode(', '),
                'risks' => $control->risks
                    ->map(fn(IsmsRisk $r): string => $r->displayNo())
                    ->implode(', '),
            ])
            ->values()
            ->all();

        return [
            'columns' => [
                'title' => (string) __('isms.field.title'),
                'implementation_status' => (string) __('isms.field.implementation_status'),
                'owner' => (string) __('isms.field.owner'),
                'evidence_note' => (string) __('isms.field.evidence_note'),
                'requirements' => (string) __('isms.field.requirements'),
                'risks' => (string) __('isms.field.risks'),
            ],
            'rows' => $rows,
        ];
    }

    // ── Serialisierung ─────────────────────────────────────────────────

    /**
     * JSON-Dokument mit meta-Block (organisation, scope, generated_at,
     * app_version — Muster AuditPackageService) und den Registerzeilen.
     *
     * @param  array{columns: array<string, string>, rows: array<int, array<string, scalar|null>>}  $register
     */
    public function toJson(string $registerKey, User $actor, ?IsmsScope $scope, array $register): string {
        $json = json_encode([
            'meta' => $this->meta($registerKey, $actor, $scope) + ['row_count' => count($register['rows'])],
            'rows' => $register['rows'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('ISMS-Registerexport konnte nicht serialisiert werden: ' . $registerKey);
        }

        return $json;
    }

    /**
     * CSV mit Semikolon, UTF-8-BOM und kommentiertem Kopf (Datenstand) —
     * Muster FileTarget; Spaltentitel in der Anzeigesprache.
     *
     * @param  array{columns: array<string, string>, rows: array<int, array<string, scalar|null>>}  $register
     */
    public function toCsv(string $registerKey, User $actor, ?IsmsScope $scope, array $register): string {
        $meta = $this->meta($registerKey, $actor, $scope);

        $lines = [
            '# ' . $meta['register'],
            '# ' . __('isms.export.meta_organisation') . ': ' . $meta['organisation'],
            '# ' . __('isms.export.meta_scope') . ': ' . ($meta['scope'] ?? '—'),
            '# ' . __('isms.export.meta_generated_at') . ': ' . $meta['generated_at'],
            '# ' . __('isms.export.meta_app_version') . ': ' . $meta['app_version'],
            CsvFacade::line(array_values($register['columns'])),
        ];

        foreach ($register['rows'] as $row) {
            $cells = [];
            foreach (array_keys($register['columns']) as $key) {
                $cells[] = $row[$key] ?? '';
            }
            $lines[] = CsvFacade::line($cells);
        }

        return self::BOM . implode("\r\n", $lines) . "\r\n";
    }

    /** Download-Dateiname, z. B. isms-risks-20260612_101500.csv. */
    public function filename(string $registerKey, string $format): string {
        return sprintf('isms-%s-%s.%s', $registerKey, Carbon::now()->format('Ymd_His'), $format);
    }

    /**
     * meta-Block des Exports: ehrlicher Datenstand (generated_at) statt
     * Stichtags-Behauptung — „versioniert" leistet das Auditpaket.
     *
     * @return array{register: string, organisation: string|null, scope: string|null, generated_at: string, generated_by: string, app_version: string}
     */
    private function meta(string $registerKey, User $actor, ?IsmsScope $scope): array {
        return [
            'register' => (string) __('isms.export.register_' . $registerKey),
            'organisation' => $actor->organization?->name,
            'scope' => $scope?->name,
            'generated_at' => Carbon::now()->toIso8601String(),
            'generated_by' => $actor->name,
            'app_version' => (string) config('app.version', '0.1.0-dev'),
        ];
    }
}
