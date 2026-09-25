<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningRecordsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Enums\Learning\LearningEnrollmentStatus;
use App\Models\Learning\{LearningBooking, LearningCertificate, LearningEnrollment, LearningQuizAttempt, LearningTimeSession};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Lernplattform in der Betroffenenauskunft (Feature 149, MVP-787):
 * Einschreibungen, Prüfungsversuche, Zertifikate, Lernzeit und Buchungen
 * einer Person — als Familien mit Zeitraum und Zählern. Fragetexte und
 * Antworten gehören nicht in die Auskunft (Prüfungsakte bleibt Prüfungsakte);
 * Zertifikatsnummern schon, weil sie der Person zugeordnet sind.
 */
class LearningRecordsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'learning';
    }

    public function title(): string {
        return __('Lernplattform (Schulungen, Prüfungen, Zertifikate)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;
        $orgId = (int) $u->organization_id;

        $enrollments = LearningEnrollment::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('user_id', $u->id);
        $enrollmentIds = (clone $enrollments)->select('id');

        // Zähler je Status als lesbare Zeile — die Familie trägt nur Skalare.
        $byStatus = [];
        foreach (LearningEnrollmentStatus::cases() as $status) {
            $count = (clone $enrollments)->where('status', $status->value)->count();
            if ($count > 0) {
                $byStatus[] = $status->value . ': ' . $count;
            }
        }

        $certificates = LearningCertificate::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('user_id', $u->id);

        return ['families' => [
            $this->family(
                'learning_enrollments',
                __('Einschreibungen'),
                $enrollments,
                'created_at',
                ['by_status' => implode(', ', $byStatus)],
                columns: ['created_at' => __('Datum'), 'status' => __('Status'), 'started_at' => __('Beginn'), 'completed_at' => __('Abgeschlossen'), 'score_percent' => __('Ergebnis (%)')],
            ),
            $this->family(
                'learning_quiz_attempts',
                __('Prüfungsversuche'),
                LearningQuizAttempt::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)->whereIn('learning_enrollment_id', $enrollmentIds),
                'started_at',
                ['passed' => LearningQuizAttempt::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)->whereIn('learning_enrollment_id', $enrollmentIds)
                    ->where('passed', true)->count()],
                    columns: ['started_at' => __('Beginn'), 'attempt_no' => __('Versuch'), 'score_percent' => __('Ergebnis (%)'), 'passed' => __('Bestanden')],
                ),
            $this->family(
                'learning_certificates',
                __('Zertifikate'),
                $certificates,
                'issued_on',
                ['numbers' => implode(', ', array_map('strval', (clone $certificates)->orderBy('issued_on')->pluck('number')->all()))],
                columns: ['issued_on' => __('Ausgestellt'), 'number' => __('Nummer'), 'valid_until' => __('Gültig bis')],
            ),
            $this->family(
                'learning_time_sessions',
                __('Lernzeit-Sitzungen'),
                LearningTimeSession::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)->where('user_id', $u->id),
                'started_at',
                ['active_minutes_total' => intdiv((int) LearningTimeSession::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)->where('user_id', $u->id)->sum('active_seconds'), 60)],
                    columns: ['started_at' => __('Beginn'), 'ended_at' => __('Ende'), 'active_seconds' => __('Aktive Sekunden')],
                ),
            $this->family(
                'learning_bookings',
                __('Kursbuchungen'),
                LearningBooking::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)->where('user_id', $u->id),
                'requested_at',
                columns: ['requested_at' => __('Angefragt'), 'status' => __('Status'), 'seats' => __('Plätze')],
            ),
        ]];
    }
}
