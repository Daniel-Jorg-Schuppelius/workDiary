<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsDeadlineScans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Isms\RiskStatus;
use App\Enums\Notification\NotificationEvent;
use App\Models\Isms\{IsmsCertificate, IsmsCorrectiveAction, IsmsRisk, IsmsRiskAssessment, IsmsSupplierAssessment, IsmsVulnerability};
use App\Models\User;
use App\Services\Isms\ConformityService;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * ISMS-Fristen (Features 044/046): Zertifikate, Korrekturmaßnahmen,
 * Risiko-Reviews, Schwachstellen und Lieferantenbewertungen — ein Fachmodul,
 * eine Scan-Klasse (B11).
 */
class IsmsDeadlineScans extends AbstractDeadlineScan {
    public function __construct(private readonly ConformityService $conformity) {}

    public function key(): string {
        return 'isms';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $sent = $this->scanCertificates($dispatcher, $options);
        $sent += $this->scanCorrectiveActions($dispatcher);
        $sent += $this->scanRiskAssessments($dispatcher, $options->expiringDays);
        $sent += $this->scanVulnerabilities($dispatcher);
        $sent += $this->scanSupplierReviews($dispatcher);

        return $sent;
    }

    /**
     * ISMS-Zertifikate (Feature 046, Inkrement B): erst den automatischen
     * Verfall durchsetzen (ConformityService), dann ablaufende Zertifikate
     * melden (Vorlauf --expiring-days).
     */
    private function scanCertificates(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $expired = $this->conformity->expireOverdue();
        if ($expired > 0) {
            $options->info(sprintf('%d Konformitätsstatus auf „Zertifikat abgelaufen" gesetzt.', $expired));
        }

        $today = Carbon::today();
        $expiringDays = $options->expiringDays;

        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => IsmsCertificate::query()
                    ->whereDate('valid_from', '<=', $today)
                    ->whereDate('valid_until', '>=', $today)
                    ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays)),
                'event' => NotificationEvent::IsmsCertificateExpiring,
                'payload' => fn(IsmsCertificate $certificate): array => $this->certificatePayload($certificate),
            ],
        ]);
    }

    /**
     * ISMS-Korrekturmaßnahmen (Feature 046, Inkrement C): überfällige Maßnahmen
     * melden (Empfänger Verantwortlicher, Fallback teamleitung) + Eskalation.
     */
    private function scanCorrectiveActions(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsCorrectiveAction $action): ?User => $action->owner()->first(),
            'overdue' => [
                'query' => fn() => IsmsCorrectiveAction::query()->overdue(),
                'event' => NotificationEvent::IsmsCorrectiveActionOverdue,
                'payload' => fn(IsmsCorrectiveAction $action): array => $this->correctiveActionPayload($action),
            ],
        ]);
    }

    /**
     * Risiko-Bewertungshistorie (Feature 046, Inkrement D): jüngste freigegebene
     * Netto-Bewertung je Risiko; feuert isms.riskReviewDue bei nahem/überschrittenem
     * valid_until an den Risikoeigentümer (offene Risiken).
     * Abgrenzung: isms_risks.review_due_on wird NICHT gescannt — nur assessment.valid_until.
     */
    private function scanRiskAssessments(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $today = Carbon::today();

        // Nur der jüngste freigegebene Netto-Stand je Risiko — ältere (abgelöste) dürfen kein Review anstoßen.
        $latestApprovedNetIds = IsmsRiskAssessment::query()
            ->approvedNet()
            ->selectRaw('max(id)')
            ->groupBy('isms_risk_id');

        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsRiskAssessment $assessment): ?User => $this->riskAssessmentAffected($assessment),
            'due' => [
                'query' => fn() => IsmsRiskAssessment::query()
                    ->whereIn('id', $latestApprovedNetIds)
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays))
                    ->whereHas('risk', fn($query) => $query->where('status', '!=', RiskStatus::Closed->value)),
                'event' => NotificationEvent::IsmsRiskReviewDue,
                'payload' => fn(IsmsRiskAssessment $assessment): array => $this->riskAssessmentPayload($assessment),
            ],
        ]);
    }

    /**
     * Schwachstellenregister (Feature 044, MVP 2): überfällige Schwachstellen
     * melden (Empfänger Verantwortlicher, Fallback teamleitung) + Eskalation.
     */
    private function scanVulnerabilities(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsVulnerability $vulnerability): ?User => $vulnerability->owner()->first(),
            'overdue' => [
                'query' => fn() => IsmsVulnerability::query()->overdue(),
                'event' => NotificationEvent::IsmsVulnerabilityOverdue,
                'payload' => fn(IsmsVulnerability $vulnerability): array => $this->vulnerabilityPayload($vulnerability),
            ],
        ]);
    }

    /**
     * Lieferantenbewertung (Feature 044, MVP 2/3): überfällige Reviews melden
     * (Empfänger Verantwortlicher, Fallback teamleitung) + Eskalation.
     */
    private function scanSupplierReviews(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsSupplierAssessment $assessment): ?User => $assessment->owner()->first(),
            'overdue' => [
                'query' => fn() => IsmsSupplierAssessment::query()->reviewOverdue(),
                'event' => NotificationEvent::IsmsSupplierReviewOverdue,
                'payload' => fn(IsmsSupplierAssessment $assessment): array => $this->supplierReviewPayload($assessment),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function certificatePayload(IsmsCertificate $certificate): array {
        $normStatus = $certificate->normStatus()->withTrashed()->first();

        return [
            'title' => trim(($normStatus?->normLabel() ?? '') . ' — ' . $certificate->certificate_no, ' —'),
            'message' => (string) __('notification.message.certificate_expiring', [
                'date' => $certificate->valid_until->format('d.m.Y'),
            ]),
            'message_key' => 'notification.message.certificate_expiring',
            'message_params' => ['date' => $certificate->valid_until->toDateString()],
            'url' => route('isms.conformity.index'),
            'due_at' => $certificate->valid_until,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function correctiveActionPayload(IsmsCorrectiveAction $action): array {
        $finding = $action->finding()->withTrashed()->first();

        return [
            'title' => trim(($finding?->displayNo() ?? '') . ' — ' . $action->title, ' —'),
            'message' => (string) __('notification.message.corrective_action_overdue', [
                'date' => $action->due_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.corrective_action_overdue',
            'message_params' => ['date' => $action->due_on?->toDateString() ?? '–'],
            'url' => route('isms.audits.index'),
            'due_at' => $action->due_on,
        ];
    }

    private function riskAssessmentAffected(IsmsRiskAssessment $assessment): ?User {
        return $assessment->risk()->withTrashed()->first()?->owner()->first();
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function riskAssessmentPayload(IsmsRiskAssessment $assessment): array {
        /** @var IsmsRisk|null $risk */
        $risk = $assessment->risk()->withTrashed()->first();

        return [
            'title' => trim(($risk?->displayNo() ?? '') . ' — ' . (string) $risk?->title, ' —'),
            'message' => (string) __('notification.message.risk_review_due', [
                'date' => $assessment->valid_until?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.risk_review_due',
            'message_params' => ['date' => $assessment->valid_until?->toDateString() ?? '–'],
            'url' => route('isms.risks.index'),
            'due_at' => $assessment->valid_until,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function vulnerabilityPayload(IsmsVulnerability $vulnerability): array {
        return [
            'title' => trim($vulnerability->displayNo() . ' — ' . $vulnerability->title, ' —'),
            'message' => (string) __('notification.message.vulnerability_overdue', [
                'date' => $vulnerability->due_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.vulnerability_overdue',
            'message_params' => ['date' => $vulnerability->due_on?->toDateString() ?? '–'],
            'url' => route('isms.vulnerabilities.index'),
            'due_at' => $vulnerability->due_on,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function supplierReviewPayload(IsmsSupplierAssessment $assessment): array {
        return [
            'title' => trim($assessment->displayNo() . ' — ' . $assessment->displayName(), ' —'),
            'message' => (string) __('notification.message.supplier_review_overdue', [
                'date' => $assessment->next_review_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.supplier_review_overdue',
            'message_params' => ['date' => $assessment->next_review_on?->toDateString() ?? '–'],
            'url' => route('isms.suppliers.index'),
            'due_at' => $assessment->next_review_on,
        ];
    }
}
