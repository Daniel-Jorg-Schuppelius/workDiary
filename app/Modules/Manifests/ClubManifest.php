<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Vereinsverwaltung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ClubManifest extends Manifest {
    public function code(): string {
        return 'club';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Vereinsverwaltung';
    }

    public function licenseCode(): string {
        return 'module.club';
    }

    public function description(): string {
        return 'Mitglieder ohne Loginpflicht, Gruppen mit Alterskriterien, Vertretungen und CSV-Erstimport für Vereine.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Club',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'club_attendance_confirmations',
            'club_attendance_records',
            'club_attendance_requirements',
            'club_attendance_revisions',
            'club_attendance_sheets',
            'club_competition_details',
            'club_competition_entries',
            'club_departments',
            'club_event_details',
            'club_event_groups',
            'club_event_participations',
            'club_event_roles',
            'club_exam_candidate_records',
            'club_exam_candidates',
            'club_exam_offer_grades',
            'club_exam_offers',
            'club_fee_accounts',
            'club_fee_assignments',
            'club_fee_claim_items',
            'club_fee_claims',
            'club_fee_dunnings',
            'club_fee_exemptions',
            'club_fee_payments',
            'club_fee_runs',
            'club_fee_surcharges',
            'club_fee_tariff_rates',
            'club_fee_tariffs',
            'club_grade_requirements',
            'club_grades',
            'club_grading_systems',
            'club_grading_versions',
            'club_group_change_proposals',
            'club_group_memberships',
            'club_groups',
            'club_guardians',
            'club_horse_assignments',
            'club_horse_groups',
            'club_horse_uses',
            'club_horses',
            'club_lineup_entries',
            'club_match_availabilities',
            'club_match_details',
            'club_match_proposals',
            'club_member_grades',
            'club_member_proofs',
            'club_members',
            'club_membership_periods',
            'club_notifications',
            'club_performances',
            'club_resource_bookings',
            'club_resource_clearances',
            'club_resource_closures',
            'club_resources',
            'club_seasons',
            'club_sport_profiles',
            'club_squad_members',
            'club_squads',
            'club_start_rights',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'club.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Club,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Club\DeadlineScans\ClubEventReminderScan::class,
                \App\Services\Club\DeadlineScans\ClubGroupCriteriaScan::class,
            ],
            \App\Services\Import\EntitySpec::class => [
                \App\Services\Club\Import\ClubMemberSpec::class,
            ],
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Club\Demo\ClubDemoSeeder::class,
            ],
            \App\Services\Finance\Contracts\AllocationTargetHandler::class => [
                \App\Services\Club\Finance\ClubFeeAllocationHandler::class,
            ],
            \App\Services\Event\Contracts\RoomBlockingSource::class => [
                \App\Services\Club\ClubResourceService::class,
            ],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            \App\Events\Calendar\EventOccurrenceCreated::class => [
                \App\Listeners\Club\InheritClubEventFromMaster::class,
            ],
        ];
    }
}
