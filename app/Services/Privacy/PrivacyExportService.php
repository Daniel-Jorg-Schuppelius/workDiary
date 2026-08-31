<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Organization;
use App\Models\Privacy\{DataSubjectRequest, ProcessingActivity};

/**
 * Baut stichtagsbezogene Export-Datenstrukturen fuer das VVT und einzelne
 * Betroffenenfaelle. Die eigentliche Auslieferung + Audit-Protokollierung
 * uebernimmt der Controller (mit IP/User-Agent-Kontext).
 */
class PrivacyExportService {
    /**
     * Stichtags-Snapshot des Verzeichnisses (freigegebene Taetigkeiten mit
     * aktueller Version).
     *
     * @return array<string, mixed>
     */
    public function ropaSnapshot(Organization $organization): array {
        $activities = ProcessingActivity::query()
            ->where('organization_id', $organization->id)
            ->with('currentVersion')
            ->orderBy('name')
            ->get()
            ->map(function (ProcessingActivity $a): array {
                return [
                    'name' => $a->name,
                    'purpose' => $a->purpose,
                    'controller_role' => $a->controller_role->value,
                    'area' => $a->area,
                    'status' => $a->status->value,
                    'review_due_at' => $a->review_due_at?->toDateString(),
                    'dsfa_required' => (bool) $a->dsfa_required,
                    'risk_level' => $a->risk_level,
                    'current_version' => $a->currentVersion ? [
                        'version_no' => $a->currentVersion->version_no,
                        'valid_from' => $a->currentVersion->valid_from?->toDateString(),
                        'payload' => $a->currentVersion->payload,
                    ] : null,
                ];
            })
            ->all();

        // Unterauftragsverarbeiter je AVV (Nachtrag 043d): Bestandteil der
        // Auskunft, damit die Kette Verantwortlicher → AV → Sub-AV belegt ist.
        $agreements = \App\Models\Privacy\ProcessingAgreement::query()
            ->where('organization_id', $organization->id)
            ->with(['processor', 'subprocessors'])
            ->orderBy('title')
            ->get()
            ->map(fn(\App\Models\Privacy\ProcessingAgreement $agreement): array => [
                'title' => $agreement->title,
                'processor' => $agreement->processor?->name,
                'valid_until' => $agreement->valid_until?->toDateString(),
                'subprocessors' => $agreement->subprocessors->map(fn(\App\Models\Privacy\Subprocessor $sub): array => [
                    'name' => $sub->name,
                    'purpose' => $sub->purpose,
                    'location' => $sub->location,
                    'third_country' => (bool) $sub->third_country,
                    'safeguards' => $sub->safeguards,
                    'approved' => (bool) $sub->approved,
                ])->all(),
            ])
            ->all();

        return [
            'organization' => $organization->name,
            'generated_at' => now()->toIso8601String(),
            'activities' => $activities,
            'agreements' => $agreements,
        ];
    }

    /**
     * Einzelner Betroffenenfall inklusive Klartext-Inhalt (sofern DEK vorhanden)
     * und vollstaendiger Ereignis-Timeline.
     *
     * @return array<string, mixed>
     */
    public function requestExport(DataSubjectRequest $request): array {
        return [
            'request_number' => $request->request_number,
            'type' => $request->type->value,
            'status' => $request->status->value,
            'channel' => $request->channel,
            'received_at' => $request->received_at?->toIso8601String(),
            'deadline_at' => $request->deadline_at?->toIso8601String(),
            'identity_verified_at' => $request->identity_verified_at?->toIso8601String(),
            'subject' => $request->subject_ciphertext,           // entschluesselt via Cast
            'content' => $request->content_ciphertext,
            'decision' => $request->decision,
            'decision_note' => $request->decision_note_ciphertext,
            'decided_at' => $request->decided_at?->toIso8601String(),
            'closed_at' => $request->closed_at?->toIso8601String(),
            'events' => $request->events()->get()->map(static fn ($e): array => [
                'event' => $e->event,
                'actor_type' => $e->actor_type,
                'metadata' => $e->metadata,
                'at' => $e->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Flacht den VVT-Snapshot fuer CSV ab (eine Zeile je Taetigkeit).
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    public function ropaCsvRows(array $snapshot): array {
        $rows = [];
        /** @var list<array<string, mixed>> $activities */
        $activities = $snapshot['activities'] ?? [];
        foreach ($activities as $a) {
            $rows[] = [
                'name' => $a['name'] ?? '',
                'purpose' => $a['purpose'] ?? '',
                'controller_role' => $a['controller_role'] ?? '',
                'area' => $a['area'] ?? '',
                'status' => $a['status'] ?? '',
                'review_due_at' => $a['review_due_at'] ?? '',
                'dsfa_required' => ! empty($a['dsfa_required']) ? '1' : '0',
                'version_no' => $a['current_version']['version_no'] ?? '',
            ];
        }

        return $rows;
    }
}
