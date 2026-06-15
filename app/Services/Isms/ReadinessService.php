<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReadinessService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{AssessmentKind, ControlImplementationStatus, FindingKind, FindingStatus};
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsAuditFinding, IsmsCertificate, IsmsCorrectiveAction, IsmsNormStatus, IsmsRisk, IsmsRiskAssessment, IsmsScope, IsmsSoftwareProduct, IsmsSupplierAssessment};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\{Carbon, Collection};

/**
 * Auditbereitschafts-Dashboard (Feature 044, MVP 1, „Auditbereitschaft"):
 * REINE Leseaggregation der ISMS-Register je Geltungsbereich — keine
 * Schreiboperationen, keine eigene Persistenz. Alle Queries laufen über
 * die org-gescopten Models (BelongsToOrganization), die Scope-Trennung
 * erfolgt über isms_scope_id; Risiken/Audits ohne Scope (Altbestand)
 * zählen zum Default-Scope.
 *
 * Kennzahlen-Definitionen (bewusst konservativ/ehrlich):
 * - SoA-Fortschritt je Norm: Statements gesamt / anwendbar / Quote der
 *   anwendbaren mit Umsetzungsstatus implemented ODER partial.
 * - Hohe Risiken: offene Risiken (status != closed) mit score > 12
 *   (rote Zone der 5×5-Matrix, {@see IsmsRisk::scoreTone()}).
 * - Überfällige Reviews: jüngste FREIGEGEBENE Netto-Bewertung mit
 *   valid_until < heute; Risiken ohne jede Bewertung gelten als
 *   „unbewertet" (beides nur für offene Risiken).
 * - Überfällige Korrekturmaßnahmen: due_on < heute, status open/inProgress
 *   ({@see IsmsCorrectiveAction::scopeOverdue()}).
 * - Offene Nichtkonformitäten: Feststellungen kind nonconformityMajor/
 *   -Minor mit status != closed.
 * - Nachweislücken: ANWENDBARE SoA-Aussagen ohne Evidenz-Notiz UND ohne
 *   gemappte Maßnahme mit Umsetzungsstatus implemented.
 * - Zertifikate: Status je Norm + Ablauf/Überwachungstermin < 90 Tage.
 * - Software: Produkte mit erreichtem End-of-Life.
 * - Ungeprüfte Lieferanten (MVP 2/3): Lieferantenbewertungen mit
 *   überfälligem Review (next_review_on) und nicht abschließend freigegeben.
 */
class ReadinessService {
    /** Score-Schwelle der roten Matrix-Zone (score > 12 = hoch). */
    public const HIGH_RISK_THRESHOLD = 12;

    /** Vorwarnfenster für Zertifikatsablauf/Überwachungstermine (Tage). */
    public const CERTIFICATE_WINDOW_DAYS = 90;

    /** Listenlimit „Top hohe Risiken". */
    public const HIGH_RISK_LIMIT = 5;

    /** Listenlimit „Nachweislücken". */
    public const EVIDENCE_GAP_LIMIT = 10;

    /** Listenlimit der übrigen Drill-down-Listen. */
    public const LIST_LIMIT = 5;

    /**
     * Aggregiert alle Dashboard-Blöcke für einen Geltungsbereich.
     *
     * @return array{
     *     soa: Collection<int, array{norm: string, total: int<0, max>, applicable: int<0, max>, covered: int<0, max>, quote: int}>,
     *     high_risks: array{count: int, top: Collection<int, IsmsRisk>},
     *     reviews: array{overdue_count: int, overdue: Collection<int, IsmsRisk>, unassessed_count: int, unassessed: Collection<int, IsmsRisk>},
     *     actions: array{overdue_count: int, overdue: Collection<int, IsmsCorrectiveAction>},
     *     nonconformities: array{open_count: int, open: Collection<int, IsmsAuditFinding>},
     *     evidence_gaps: array{count: int, top: Collection<int, IsmsApplicabilityStatement>},
     *     certificates: Collection<int, array{norm: string, status: \App\Enums\Isms\NormConformityStatus, valid_until: Carbon|null, expiring: bool, next_surveillance: Carbon|null, surveillance_soon: bool}>,
     *     software: array{eol_count: int},
     *     suppliers: array{overdue_count: int, overdue: Collection<int, IsmsSupplierAssessment>}
     * }
     */
    public function forScope(IsmsScope $scope): array {
        $reviews = $this->reviewStatus($scope);

        return [
            'soa' => $this->soaProgress($scope),
            'high_risks' => $this->highRisks($scope),
            'reviews' => $reviews,
            'actions' => $this->overdueCorrectiveActions($scope),
            'nonconformities' => $this->openNonconformities($scope),
            'evidence_gaps' => $this->evidenceGaps($scope),
            'certificates' => $this->certificateStatus($scope),
            'software' => ['eol_count' => IsmsSoftwareProduct::query()->eolReached()->count()],
            'suppliers' => $this->supplierReviews($scope),
        ];
    }

    /**
     * Ungeprüfte Lieferanten (Feature 044, MVP 2/3): Lieferantenbewertungen
     * mit überfälligem Review (next_review_on überschritten) UND noch nicht
     * abschließend freigegeben — die in MVP 1 bewusst entfallene Kennzahl, da
     * es damals noch kein Lieferantenmodul gab. Lieferantenbewertungen tragen
     * optional einen Scope; ohne Scope zählen sie zum Default-Scope.
     *
     * @return array{overdue_count: int, overdue: Collection<int, IsmsSupplierAssessment>}
     */
    private function supplierReviews(IsmsScope $scope): array {
        $assessments = IsmsSupplierAssessment::query()
            ->reviewOverdue()
            ->where(function (Builder $query) use ($scope): void {
                $query->where('isms_scope_id', $scope->id);
                if ($scope->is_default) {
                    $query->orWhereNull('isms_scope_id');
                }
            })
            ->with(['owner:id,name', 'supplier:id,name'])
            ->orderBy('next_review_on')
            ->get();

        return [
            'overdue_count' => $assessments->count(),
            'overdue' => $assessments->take(self::LIST_LIMIT)->values(),
        ];
    }

    /**
     * SoA-Fortschritt je Norm: Statements gesamt, davon anwendbar und die
     * Quote der anwendbaren mit Umsetzungsstatus implemented/partial.
     *
     * @return Collection<int, array{norm: string, total: int<0, max>, applicable: int<0, max>, covered: int<0, max>, quote: int}>
     */
    private function soaProgress(IsmsScope $scope): Collection {
        return IsmsApplicabilityStatement::query()
            ->where('isms_scope_id', $scope->id)
            ->with('requirement')
            ->get()
            ->filter(fn(IsmsApplicabilityStatement $s): bool => $s->requirement !== null)
            ->groupBy(fn(IsmsApplicabilityStatement $s): string => (string) $s->requirement?->normLabel())
            ->map(function (Collection $statements, string $norm): array {
                $applicable = $statements->filter(fn(IsmsApplicabilityStatement $s): bool => $s->applicable);
                $covered = $applicable->filter(fn(IsmsApplicabilityStatement $s): bool => in_array($s->implementation_status, [
                    ControlImplementationStatus::Implemented,
                    ControlImplementationStatus::Partial,
                ], true));

                return [
                    'norm' => $norm,
                    'total' => $statements->count(),
                    'applicable' => $applicable->count(),
                    'covered' => $covered->count(),
                    'quote' => $applicable->count() > 0
                        ? (int) round($covered->count() * 100 / $applicable->count())
                        : 0,
                ];
            })
            ->sortBy('norm')
            ->values();
    }

    /**
     * Hohe Risiken: offen (status != closed) und score > 12 — Zähler plus
     * Top-5-Liste (höchster Score zuerst).
     *
     * @return array{count: int, top: Collection<int, IsmsRisk>}
     */
    private function highRisks(IsmsScope $scope): array {
        $risks = $this->scopedRisks($scope)
            ->open()
            ->where('score', '>', self::HIGH_RISK_THRESHOLD)
            ->with('owner:id,name')
            ->orderByDesc('score')
            ->orderBy('risk_no')
            ->get();

        return [
            'count' => $risks->count(),
            'top' => $risks->take(self::HIGH_RISK_LIMIT)->values(),
        ];
    }

    /**
     * Bewertungs-Reviews offener Risiken: überfällig = jüngste FREIGEGEBENE
     * Netto-Bewertung mit valid_until < heute; unbewertet = Risiko ohne
     * jede Bewertung (gleich welcher Art).
     *
     * @return array{overdue_count: int, overdue: Collection<int, IsmsRisk>, unassessed_count: int, unassessed: Collection<int, IsmsRisk>}
     */
    private function reviewStatus(IsmsScope $scope): array {
        $risks = $this->scopedRisks($scope)
            ->open()
            ->with(['assessments' => fn($q) => $q->orderByDesc('assessment_no')])
            ->orderBy('risk_no')
            ->get();

        $overdue = $risks->filter(function (IsmsRisk $risk): bool {
            $latestNet = $risk->assessments
                ->first(fn(IsmsRiskAssessment $a): bool => $a->kind === AssessmentKind::Net && $a->isApproved());

            return $latestNet !== null && $latestNet->isReviewOverdue();
        })->values();

        $unassessed = $risks->filter(fn(IsmsRisk $risk): bool => $risk->assessments->isEmpty())->values();

        return [
            'overdue_count' => $overdue->count(),
            'overdue' => $overdue->take(self::LIST_LIMIT)->values(),
            'unassessed_count' => $unassessed->count(),
            'unassessed' => $unassessed->take(self::LIST_LIMIT)->values(),
        ];
    }

    /**
     * Überfällige Korrekturmaßnahmen (due_on < heute, open/inProgress) der
     * Audits des Geltungsbereichs — Zähler plus Liste (älteste zuerst).
     *
     * @return array{overdue_count: int, overdue: Collection<int, IsmsCorrectiveAction>}
     */
    private function overdueCorrectiveActions(IsmsScope $scope): array {
        $actions = IsmsCorrectiveAction::query()
            ->overdue()
            ->whereHas('finding.ismsAudit', fn($q) => $q->where('isms_scope_id', $scope->id))
            ->with(['owner:id,name', 'finding.ismsAudit'])
            ->orderBy('due_on')
            ->get();

        return [
            'overdue_count' => $actions->count(),
            'overdue' => $actions->take(self::LIST_LIMIT)->values(),
        ];
    }

    /**
     * Offene Nichtkonformitäten (kind nonconformityMajor/-Minor, status
     * != closed) der Audits des Geltungsbereichs.
     *
     * @return array{open_count: int, open: Collection<int, IsmsAuditFinding>}
     */
    private function openNonconformities(IsmsScope $scope): array {
        $findings = IsmsAuditFinding::query()
            ->whereIn('kind', [FindingKind::NonconformityMajor->value, FindingKind::NonconformityMinor->value])
            ->where('status', '!=', FindingStatus::Closed->value)
            ->whereHas('ismsAudit', fn($q) => $q->where('isms_scope_id', $scope->id))
            ->with('ismsAudit')
            ->orderBy('finding_no')
            ->get();

        return [
            'open_count' => $findings->count(),
            'open' => $findings->take(self::LIST_LIMIT)->values(),
        ];
    }

    /**
     * Nachweislücken: anwendbare SoA-Aussagen ohne Evidenz-Notiz UND ohne
     * gemappte Maßnahme mit Umsetzungsstatus implemented — Zähler plus
     * Top-10 in natürlicher Ref-Sortierung.
     *
     * @return array{count: int, top: Collection<int, IsmsApplicabilityStatement>}
     */
    private function evidenceGaps(IsmsScope $scope): array {
        $gaps = IsmsApplicabilityStatement::query()
            ->where('isms_scope_id', $scope->id)
            ->applicable()
            ->where(fn($q) => $q->whereNull('evidence_note')->orWhere('evidence_note', ''))
            ->whereDoesntHave(
                'requirement.controls',
                fn($q) => $q->where('implementation_status', ControlImplementationStatus::Implemented->value),
            )
            ->with('requirement')
            ->get()
            ->sort(fn(IsmsApplicabilityStatement $a, IsmsApplicabilityStatement $b): int => strcmp((string) $a->requirement?->norm, (string) $b->requirement?->norm)
                ?: strnatcmp((string) $a->requirement?->ref_no, (string) $b->requirement?->ref_no))
            ->values();

        return [
            'count' => $gaps->count(),
            'top' => $gaps->take(self::EVIDENCE_GAP_LIMIT)->values(),
        ];
    }

    /**
     * Zertifikatslage je Norm des Geltungsbereichs: Konformitätsstatus,
     * Ablauf des heute gültigen Zertifikats und nächster Überwachungs-
     * termin — jeweils mit Warn-Flag bei < 90 Tagen.
     *
     * @return Collection<int, array{norm: string, status: \App\Enums\Isms\NormConformityStatus, valid_until: Carbon|null, expiring: bool, next_surveillance: Carbon|null, surveillance_soon: bool}>
     */
    private function certificateStatus(IsmsScope $scope): Collection {
        $window = Carbon::today()->addDays(self::CERTIFICATE_WINDOW_DAYS);

        return IsmsNormStatus::query()
            ->where('isms_scope_id', $scope->id)
            ->with(['certificates' => fn($q) => $q->orderByDesc('valid_until')])
            ->get()
            ->sortBy(fn(IsmsNormStatus $s): string => $s->normLabel())
            ->values()
            ->map(function (IsmsNormStatus $status) use ($window): array {
                $active = $status->certificates
                    ->first(fn(IsmsCertificate $c): bool => $c->isValidOn(Carbon::today()));
                $nextSurveillance = $status->certificates
                    ->map(fn(IsmsCertificate $c): ?Carbon => $c->nextSurveillanceOn())
                    ->filter()
                    ->sort()
                    ->first();

                return [
                    'norm' => $status->normLabel(),
                    'status' => $status->status,
                    'valid_until' => $active?->valid_until,
                    'expiring' => $active !== null && $active->valid_until->startOfDay()->lte($window),
                    'next_surveillance' => $nextSurveillance,
                    'surveillance_soon' => $nextSurveillance !== null && $nextSurveillance->startOfDay()->lte($window),
                ];
            });
    }

    /**
     * Risiken des Geltungsbereichs — Altbestand ohne Scope zählt zum
     * Default-Scope (Risiken erhalten seit Feature 046 immer den
     * Default-Scope, {@see RiskService::create()}).
     *
     * @return Builder<IsmsRisk>
     */
    private function scopedRisks(IsmsScope $scope): Builder {
        return IsmsRisk::query()->where(function (Builder $query) use ($scope): void {
            $query->where('isms_scope_id', $scope->id);
            if ($scope->is_default) {
                $query->orWhereNull('isms_scope_id');
            }
        });
    }
}
