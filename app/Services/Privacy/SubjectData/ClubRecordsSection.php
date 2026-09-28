<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubRecordsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Club\{
    ClubAttendanceRecord,
    ClubCompetitionEntry,
    ClubDonation,
    ClubEventParticipation,
    ClubExamCandidate,
    ClubFeeAssignment,
    ClubFeeClaimItem,
    ClubFeeExemption,
    ClubGroupMembership,
    ClubHorseUse,
    ClubMatchAvailability,
    ClubMember,
    ClubMemberGrade,
    ClubMemberProof,
    ClubMembershipPeriod,
    ClubNotification,
    ClubPerformance,
    ClubResourceBooking,
    ClubResourceClearance,
    ClubSquadMember,
    ClubStartRight
};
use Illuminate\Database\Eloquent\{Builder, Model};

/** Vereinsfamilien eines Mitglieds (MVP-1009) — Zähler, Zeitraum und Detailauszug je Familie. */
class ClubRecordsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'club_records';
    }

    public function title(): string {
        return __('Vereinsdaten (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, ClubMember::class);
        /** @var ClubMember $m */
        $m = $subject;
        return ['families' => [
            $this->family('club_membership_periods', __('Mitgliedschaftszeiträume'), $this->rows($m, ClubMembershipPeriod::class), 'starts_on',
                columns: ['kind' => __('Art'), 'starts_on' => __('Beginn'), 'ends_on' => __('Ende')]),
            $this->family('club_group_memberships', __('Gruppenzugehörigkeiten'), $this->rows($m, ClubGroupMembership::class), 'created_at',
                columns: ['status' => __('Status'), 'valid_from' => __('Gültig ab'), 'valid_to' => __('Gültig bis')]),
            $this->family('club_event_participations', __('Terminanmeldungen'), $this->rows($m, ClubEventParticipation::class), 'created_at',
                columns: ['status' => __('Status'), 'registered_at' => __('Angemeldet am'), 'cancelled_at' => __('Abgesagt am')]),
            $this->family('club_attendance_records', __('Anwesenheiten'), $this->rows($m, ClubAttendanceRecord::class), 'created_at',
                columns: ['status' => __('Status'), 'minutes' => __('Minuten'), 'recorded_at' => __('Erfasst am')]),
            $this->family('club_fee_assignments', __('Beitragszuordnungen'), $this->rows($m, ClubFeeAssignment::class), 'created_at',
                columns: ['valid_from' => __('Gültig ab'), 'valid_to' => __('Gültig bis'), 'discount_percent' => __('Ermäßigung (%)')]),
            $this->family('club_fee_claim_items', __('Beitragspositionen'), $this->rows($m, ClubFeeClaimItem::class), 'created_at',
                columns: ['label' => __('Bezeichnung'), 'period_start' => __('Beginn'), 'period_end' => __('Ende'), 'amount' => __('Betrag')]),
            $this->family('club_fee_exemptions', __('Beitragsbefreiungen'), $this->rows($m, ClubFeeExemption::class), 'created_at',
                columns: ['kind' => __('Art'), 'percent' => __('Prozent'), 'starts_on' => __('Beginn'), 'ends_on' => __('Ende')]),
            $this->family('club_donations', __('Spenden'), $this->rows($m, ClubDonation::class), 'received_on',
                columns: ['received_on' => __('Datum'), 'kind' => __('Art'), 'amount' => __('Betrag')]),
            $this->family('club_member_grades', __('Graduierungen'), $this->rows($m, ClubMemberGrade::class), 'created_at',
                columns: ['obtained_on' => __('Erworben am'), 'source' => __('Quelle'), 'revoked_at' => __('Widerrufen am')]),
            $this->family('club_exam_candidates', __('Prüfungsanmeldungen'), $this->rows($m, ClubExamCandidate::class), 'created_at',
                columns: ['status' => __('Status'), 'requested_at' => __('Angemeldet am')]),
            $this->family('club_member_proofs', __('Nachweise'), $this->rows($m, ClubMemberProof::class), 'created_at',
                columns: ['label' => __('Bezeichnung'), 'obtained_on' => __('Erworben am'), 'valid_until' => __('Gültig bis')]),
            $this->family('club_performances', __('Leistungen'), $this->rows($m, ClubPerformance::class), 'created_at',
                columns: ['performed_on' => __('Datum'), 'discipline_code' => __('Disziplin'), 'value' => __('Wert'), 'unit' => __('Einheit'), 'placement' => __('Platzierung')]),
            $this->family('club_competition_entries', __('Wettkampfmeldungen'), $this->rows($m, ClubCompetitionEntry::class), 'created_at',
                columns: ['discipline_code' => __('Disziplin'), 'status' => __('Status'), 'registered_at' => __('Angemeldet am')]),
            $this->family('club_start_rights', __('Startrechte'), $this->rows($m, ClubStartRight::class), 'created_at',
                columns: ['reference' => __('Referenz'), 'valid_from' => __('Gültig ab'), 'valid_to' => __('Gültig bis')]),
            $this->family('club_squad_members', __('Kaderzugehörigkeiten'), $this->rows($m, ClubSquadMember::class), 'created_at',
                columns: ['valid_from' => __('Gültig ab'), 'valid_to' => __('Gültig bis'), 'position_code' => __('Position')]),
            $this->family('club_match_availabilities', __('Spieltagsrückmeldungen'), $this->rows($m, ClubMatchAvailability::class), 'created_at',
                columns: ['status' => __('Status'), 'responded_at' => __('Rückmeldung am')]),
            $this->family('club_resource_bookings', __('Ressourcenbuchungen'), $this->rows($m, ClubResourceBooking::class), 'created_at',
                columns: ['starts_at' => __('Beginn'), 'ends_at' => __('Ende')]),
            $this->family('club_resource_clearances', __('Ressourcenfreigaben'), $this->rows($m, ClubResourceClearance::class), 'created_at',
                columns: ['granted_on' => __('Erteilt am'), 'valid_to' => __('Gültig bis')]),
            $this->family('club_horse_uses', __('Pferdeeinsätze'), $this->rows($m, ClubHorseUse::class), 'created_at',
                columns: ['recorded_at' => __('Erfasst am'), 'minutes' => __('Minuten')]),
            $this->family('club_notifications', __('Vereinsbenachrichtigungen'), $this->rows($m, ClubNotification::class), 'created_at',
                columns: ['kind' => __('Art'), 'status' => __('Status'), 'sent_at' => __('Gesendet am')]),
        ]];
    }

    /**
     * @param  class-string<Model>  $class
     * @return Builder<Model>
     */
    private function rows(ClubMember $member, string $class): Builder {
        return $class::query()->withoutGlobalScopes()->where('organization_id', $member->organization_id)->where('club_member_id', $member->id);
    }
}
