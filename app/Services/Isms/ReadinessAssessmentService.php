<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReadinessAssessmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{Exploitability, IncidentSeverity};
use App\Models\Isms\{IsmsScope, IsmsSecurityIncident, IsmsVulnerability};

/**
 * Reifegrad-/Readiness-Assessment (Feature 044, MVP 3): leitet aus den
 * bestehenden ISMS-Registern je Domäne einen Reifegrad (Ampel + Score 0–100)
 * und daraus eine begründete Gesamteinschätzung „intern auditbereit?" ab.
 *
 * WICHTIG (046-Prinzip, „keine automatische Konformitätsbehauptung"):
 * Das Ergebnis ist ausschließlich eine begründete SELBSTEINSCHÄTZUNG/
 * Empfehlung — NIE „zertifiziert" oder „konform". Die Konformitäts- und
 * Zertifizierungsbewertung erfolgt durch eine unabhängige Zertifizierungs-
 * stelle. Diese Klasse setzt KEINE Konformitätsstatus und gibt das Wort
 * „zertifiziert" niemals als Ergebnis zurück; sie aggregiert nur die
 * vorhandenen Auditbereitschafts-Signale aus dem {@see ReadinessService}.
 *
 * Domänen (je eigene, konservative Score-Ableitung):
 * - soa: gewichtete Umsetzungsquote der anwendbaren Anforderungen je Norm.
 * - risk: offene hohe Risiken + überfällige/unbewertete Risiko-Reviews.
 * - evidence: Nachweislücken (anwendbar, ohne Nachweis & umgesetzte Maßnahme).
 * - audit: offene Nichtkonformitäten + überfällige Korrekturmaßnahmen.
 * - operations: offene kritische Vorfälle + offene/ausnutzbare Schwachstellen.
 * - suppliers: ungeprüfte Lieferanten (überfälliger Review).
 *
 * Die Gesamteinschätzung ist bewusst pessimistisch: bereits eine rote
 * Domäne genügt für „noch nicht auditbereit".
 */
class ReadinessAssessmentService {
    /** Ampel: grün ab diesem Score (gut). */
    public const SCORE_GREEN = 80;

    /** Ampel: gelb ab diesem Score (teilweise); darunter rot. */
    public const SCORE_AMBER = 50;

    public function __construct(
        private readonly ReadinessService $readiness,
    ) {}

    /**
     * Erzeugt das vollständige Readiness-Assessment für einen Geltungsbereich.
     *
     * @return array{
     *     domains: list<array{key: string, score: int, tone: string, level: string, signals: list<string>}>,
     *     overall_score: int,
     *     overall_tone: string,
     *     audit_ready: bool,
     *     blockers: list<array{domain: string, reason: string}>,
     *     is_self_assessment: true
     * }
     */
    public function forScope(IsmsScope $scope): array {
        $data = $this->readiness->forScope($scope);

        $domains = [
            $this->soaDomain($data),
            $this->riskDomain($data),
            $this->evidenceDomain($data),
            $this->auditDomain($data),
            $this->operationsDomain($scope),
            $this->supplierDomain($data),
        ];

        // Gesamtscore: gerundeter Mittelwert der Domänen-Scores.
        $overallScore = (int) round(array_sum(array_column($domains, 'score')) / max(1, count($domains)));

        // Blocker = jede rote Domäne (mit ihren Signalen als Begründung).
        $blockers = [];
        foreach ($domains as $domain) {
            if ($domain['level'] === 'red') {
                $blockers[] = [
                    'domain' => $domain['key'],
                    'reason' => $domain['signals'][0] ?? $domain['key'],
                ];
            }
        }

        $auditReady = $blockers === [] && $overallScore >= self::SCORE_GREEN;

        return [
            'domains' => $domains,
            'overall_score' => $overallScore,
            'overall_tone' => $this->tone($overallScore),
            'audit_ready' => $auditReady,
            'blockers' => $blockers,
            'is_self_assessment' => true,
        ];
    }

    /**
     * SoA-Domäne: gewichteter Mittelwert der Umsetzungsquoten je Norm (über
     * anwendbare Anforderungen). Ohne anwendbare Anforderungen gilt die
     * Domäne als nicht aufgebaut (Score 0).
     *
     * @param  array<string, mixed>  $data
     * @return array{key: string, score: int, tone: string, level: string, signals: list<string>}
     */
    private function soaDomain(array $data): array {
        $rows = $data['soa'];
        $applicable = (int) $rows->sum('applicable');
        $covered = (int) $rows->sum('covered');

        $score = $applicable > 0 ? (int) round($covered * 100 / $applicable) : 0;

        $signals = [];
        if ($applicable === 0) {
            $signals[] = (string) __('isms.readiness.signal.soa_empty');
        } else {
            $signals[] = (string) __('isms.readiness.signal.soa_coverage', [
                'covered' => $covered,
                'applicable' => $applicable,
                'quote' => $score,
            ]);
        }

        return $this->domain('soa', $score, $signals);
    }

    /**
     * Risiko-Domäne: jede offene hohe, überfällige oder unbewertete Position
     * zieht 20 Punkte ab (Start 100).
     *
     * @param  array<string, mixed>  $data
     * @return array{key: string, score: int, tone: string, level: string, signals: list<string>}
     */
    private function riskDomain(array $data): array {
        $high = (int) $data['high_risks']['count'];
        $overdue = (int) $data['reviews']['overdue_count'];
        $unassessed = (int) $data['reviews']['unassessed_count'];

        $score = $this->penalize(100, ($high + $overdue + $unassessed), 20);

        $signals = [];
        if ($high > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.high_risks', $high, ['count' => $high]);
        }
        if ($overdue > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.overdue_reviews', $overdue, ['count' => $overdue]);
        }
        if ($unassessed > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.unassessed_risks', $unassessed, ['count' => $unassessed]);
        }

        return $this->domain('risk', $score, $signals);
    }

    /**
     * Nachweis-Domäne: jede Nachweislücke zieht 10 Punkte ab.
     *
     * @param  array<string, mixed>  $data
     * @return array{key: string, score: int, tone: string, level: string, signals: list<string>}
     */
    private function evidenceDomain(array $data): array {
        $gaps = (int) $data['evidence_gaps']['count'];
        $score = $this->penalize(100, $gaps, 10);

        $signals = $gaps > 0
            ? [(string) trans_choice('isms.readiness.signal.evidence_gaps', $gaps, ['count' => $gaps])]
            : [(string) __('isms.readiness.signal.ok')];

        return $this->domain('evidence', $score, $signals);
    }

    /**
     * Audit-Domäne: offene Nichtkonformitäten (je 25) und überfällige
     * Korrekturmaßnahmen (je 15) ziehen ab. Eine offene Nichtkonformität ist
     * für ein internes Audit besonders gravierend.
     *
     * @param  array<string, mixed>  $data
     * @return array{key: string, score: int, tone: string, level: string, signals: list<string>}
     */
    private function auditDomain(array $data): array {
        $nonconformities = (int) $data['nonconformities']['open_count'];
        $actions = (int) $data['actions']['overdue_count'];

        $score = $this->penalize(100, 0, 0) - ($nonconformities * 25) - ($actions * 15);
        $score = max(0, min(100, $score));

        $signals = [];
        if ($nonconformities > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.nonconformities', $nonconformities, ['count' => $nonconformities]);
        }
        if ($actions > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.overdue_actions', $actions, ['count' => $actions]);
        }

        return $this->domain('audit', $score, $signals);
    }

    /**
     * Betriebs-Domäne: offene kritische Vorfälle (je 30) und offene
     * Schwachstellen — eine als „ausnutzbar" entschiedene Schwachstelle
     * wiegt schwerer (je 25) als eine sonstige offene kritische/hohe (je 10).
     * Live aus den org-gescopten Registern (kein Scope-Feld bei Vorfällen im
     * MVP-Schema vorhanden → organisationsweit; bewusst konservativ).
     *
     * @return array{key: string, score: int, tone: string, level: string, signals: list<string>}
     */
    private function operationsDomain(IsmsScope $scope): array {
        $criticalIncidents = IsmsSecurityIncident::query()->openCritical()->count();

        $exploitable = IsmsVulnerability::query()
            ->open()
            ->where('exploitability', Exploitability::Exploitable->value)
            ->count();

        $openSevere = IsmsVulnerability::query()
            ->open()
            ->whereIn('severity', [IncidentSeverity::Critical->value, IncidentSeverity::High->value])
            ->where('exploitability', '!=', Exploitability::Exploitable->value)
            ->count();

        $score = 100 - ($criticalIncidents * 30) - ($exploitable * 25) - ($openSevere * 10);
        $score = max(0, min(100, $score));

        $signals = [];
        if ($criticalIncidents > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.critical_incidents', $criticalIncidents, ['count' => $criticalIncidents]);
        }
        if ($exploitable > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.exploitable_vulns', $exploitable, ['count' => $exploitable]);
        }
        if ($openSevere > 0) {
            $signals[] = (string) trans_choice('isms.readiness.signal.open_severe_vulns', $openSevere, ['count' => $openSevere]);
        }

        return $this->domain('operations', $score, $signals);
    }

    /**
     * Lieferanten-Domäne: jeder ungeprüfte Lieferant (überfälliger Review)
     * zieht 20 Punkte ab.
     *
     * @param  array<string, mixed>  $data
     * @return array{key: string, score: int, tone: string, level: string, signals: list<string>}
     */
    private function supplierDomain(array $data): array {
        $overdue = (int) $data['suppliers']['overdue_count'];
        $score = $this->penalize(100, $overdue, 20);

        $signals = $overdue > 0
            ? [(string) trans_choice('isms.readiness.signal.unchecked_suppliers', $overdue, ['count' => $overdue])]
            : [(string) __('isms.readiness.signal.ok')];

        return $this->domain('suppliers', $score, $signals);
    }

    /**
     * Baut den Domänen-Eintrag (Ampel + Reifegrad-Stufe aus dem Score).
     *
     * @param  list<string>  $signals
     * @return array{key: string, score: int, tone: string, level: string, signals: list<string>}
     */
    private function domain(string $key, int $score, array $signals): array {
        $score = max(0, min(100, $score));
        $level = $this->level($score);

        return [
            'key' => $key,
            'score' => $score,
            'tone' => $this->tone($score),
            'level' => $level,
            'signals' => $signals === [] ? [(string) __('isms.readiness.signal.ok')] : $signals,
        ];
    }

    /** Score-Abzug: Start − (Anzahl × Gewicht), auf [0,100] begrenzt. */
    private function penalize(int $base, int $count, int $weight): int {
        return max(0, min(100, $base - ($count * $weight)));
    }

    /** Ampel-Stufe (rot/gelb/grün) aus dem Score. */
    private function level(int $score): string {
        return match (true) {
            $score >= self::SCORE_GREEN => 'green',
            $score >= self::SCORE_AMBER => 'amber',
            default => 'red',
        };
    }

    /** DaisyUI-Tone aus dem Score. */
    private function tone(int $score): string {
        return match ($this->level($score)) {
            'green' => 'success',
            'amber' => 'warning',
            default => 'error',
        };
    }

}
