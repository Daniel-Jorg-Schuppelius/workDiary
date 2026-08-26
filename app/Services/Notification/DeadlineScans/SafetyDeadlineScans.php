<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyDeadlineScans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Safety\{HazardAssessment, MedicalCheckup, SafetyInstruction, SafetyInstructionParticipant};
use App\Models\User;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Arbeitsschutz-Fristen (Feature 132): GBU-Wiedervorlage, Wiederholungs-
 * unterweisung je Teilnehmer und arbeitsmedizinische Vorsorge — ein
 * Fachmodul, eine Scan-Klasse (B11). Wie beim ISMS-Risiko-Review läuft
 * alles über EINE Phase `due` mit Fenster „≤ heute + Vorlauf": fällig und
 * überfällig feuern dasselbe Ereignis, Dedup über das Dispatch-Log.
 */
class SafetyDeadlineScans extends AbstractDeadlineScan {
    public function key(): string {
        return 'safety';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $limit = Carbon::today()->addDays($options->expiringDays)->toDateString();

        $sent = $this->scanAssessments($dispatcher, $limit);
        $sent += $this->scanInstructions($dispatcher, $limit);
        $sent += $this->scanCheckups($dispatcher, $limit);

        return $sent;
    }

    /**
     * Freigegebene Gefährdungsbeurteilungen mit naher/überschrittener
     * Wiedervorlage — archivierte (abgelöste) Stände stoßen kein Review an.
     */
    private function scanAssessments(NotificationDispatcher $dispatcher, string $limit): int {
        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => HazardAssessment::query()
                    ->approved()
                    ->whereNotNull('review_due_on')
                    ->whereDate('review_due_on', '<=', $limit),
                'event' => NotificationEvent::SafetyAssessmentReviewDue,
                'payload' => fn(HazardAssessment $assessment): array => [
                    'title' => trim($assessment->displayNo() . ' — ' . $assessment->area, ' —'),
                    'message' => (string) __('notification.message.safety_assessment_review_due', [
                        'date' => $assessment->review_due_on?->format('d.m.Y') ?? '–',
                    ]),
                    'message_key' => 'notification.message.safety_assessment_review_due',
                    'message_params' => ['date' => $assessment->review_due_on?->toDateString() ?? '–'],
                    'url' => route('safety.assessments.show', $assessment),
                    'due_at' => $assessment->review_due_on,
                ],
            ],
        ]);
    }

    /**
     * Wiederholungsunterweisung je Person: nur der JÜNGSTE Nachweis je
     * (Person, Thema) zählt — eine spätere Unterweisung zum selben Thema
     * löst die Fälligkeit der älteren ab.
     */
    private function scanInstructions(NotificationDispatcher $dispatcher, string $limit): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(SafetyInstructionParticipant $participant): ?User => $participant->user()->first(),
            'due' => [
                'query' => fn() => SafetyInstructionParticipant::query()
                    ->whereNotNull('next_due_on')
                    ->whereDate('next_due_on', '<=', $limit)
                    ->whereHas('instruction')
                    ->whereNotExists(function (QueryBuilder $query): void {
                        $query->from('safety_instruction_participants as p2')
                            ->join('safety_instructions as i2', 'i2.id', '=', 'p2.safety_instruction_id')
                            ->join('safety_instructions as i1', 'i1.id', '=', 'safety_instruction_participants.safety_instruction_id')
                            ->whereColumn('p2.user_id', 'safety_instruction_participants.user_id')
                            ->whereColumn('i2.topic', 'i1.topic')
                            ->whereColumn('i2.held_on', '>', 'i1.held_on')
                            ->whereNull('i2.deleted_at');
                    }),
                'event' => NotificationEvent::SafetyInstructionDue,
                'payload' => fn(SafetyInstructionParticipant $participant): array => $this->instructionPayload($participant),
            ],
        ]);
    }

    /**
     * Vorsorge je Person: nur die JÜNGSTE Vorsorge je (Person, Art) — eine
     * neue Vorsorge löst die Fälligkeit der älteren ab.
     */
    private function scanCheckups(NotificationDispatcher $dispatcher, string $limit): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(MedicalCheckup $checkup): ?User => $checkup->user()->first(),
            'due' => [
                'query' => fn() => MedicalCheckup::query()
                    ->whereNotNull('next_due_on')
                    ->whereDate('next_due_on', '<=', $limit)
                    ->whereNotExists(function (QueryBuilder $query): void {
                        $query->from('medical_checkups as m2')
                            ->whereColumn('m2.user_id', 'medical_checkups.user_id')
                            ->whereColumn('m2.kind', 'medical_checkups.kind')
                            ->whereColumn('m2.performed_on', '>', 'medical_checkups.performed_on')
                            ->whereNull('m2.deleted_at');
                    }),
                'event' => NotificationEvent::SafetyCheckupDue,
                'payload' => fn(MedicalCheckup $checkup): array => [
                    'title' => trim($checkup->kind->label() . ' — ' . (string) $checkup->occasion, ' —'),
                    'message' => (string) __('notification.message.safety_checkup_due', [
                        'kind' => $checkup->kind->label(),
                        'date' => $checkup->next_due_on?->format('d.m.Y') ?? '–',
                    ]),
                    'message_key' => 'notification.message.safety_checkup_due',
                    'message_params' => [
                        'kind' => ['key' => 'enums.safety.checkup-kind.' . $checkup->kind->value, 'fallback' => $checkup->kind->label()],
                        'date' => $checkup->next_due_on?->toDateString() ?? '–',
                    ],
                    'url' => route('safety.checkups.index'),
                    'due_at' => $checkup->next_due_on,
                ],
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function instructionPayload(SafetyInstructionParticipant $participant): array {
        /** @var SafetyInstruction|null $instruction */
        $instruction = $participant->instruction()->withTrashed()->first();

        return [
            'title' => trim(($instruction?->displayNo() ?? '') . ' — ' . (string) $instruction?->topic, ' —'),
            'message' => (string) __('notification.message.safety_instruction_due', [
                'topic' => (string) $instruction?->topic,
                'date' => $participant->next_due_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.safety_instruction_due',
            'message_params' => [
                'topic' => (string) $instruction?->topic,
                'date' => $participant->next_due_on?->toDateString() ?? '–',
            ],
            'url' => $instruction !== null ? route('safety.instructions.show', $instruction) : $this->safeRoute('safety.instructions.index'),
            'due_at' => $participant->next_due_on,
        ];
    }
}
