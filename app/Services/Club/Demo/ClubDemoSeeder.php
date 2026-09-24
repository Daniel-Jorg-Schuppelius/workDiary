<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDemoSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Club\{ClubAvailabilityStatus, ClubEventKind, ClubEventRoleKind, ClubEventVisibility, ClubFeePaymentMethod, ClubFeeTariffKind, ClubGuardianPermission, ClubHorseKind, ClubLineupSlot, ClubParticipationSource, ClubResultFormat};
use App\Models\Calendar\Event;
use App\Models\Club\{ClubFeeAccount, ClubFeeClaim, ClubGrade, ClubGradingSystem, ClubGroup, ClubHorse, ClubMember, ClubResource, ClubSportProfile};
use App\Models\Platform\{Organization, User};
use App\Services\Club\{ClubAttendanceService, ClubCompetitionService, ClubEventService, ClubExamService, ClubFeePaymentService, ClubFeeRunService, ClubFeeService, ClubGradingService, ClubGroupService, ClubHorseService, ClubMatchService, ClubMemberService, ClubResourceService, ClubStarterPackService, ClubTeamService};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Musterbranche Sportverein (Feature 159, MVP-848): fiktiver Mehrspartenverein
 * mit allen elf Sportarten aus den Startpaketen — Mitglieder ohne Login,
 * Vertretungen, Gruppen, Training mit Anwesenheit, Beiträge mit Familienkonto
 * und Lauf, Mannschaften mit Spieltagen, Wettkämpfe, Graduierung, Reitbetrieb
 * und Sportstätten. Alle Personen, Vereine und Nachweise sind erfunden;
 * Termine liegen relativ zum Seed-Tag, Namen und Mengen sind fest.
 */
class ClubDemoSeeder {
    private const TIMEZONE = 'Europe/Berlin';

    /** Startpaket → [ISO-Wochentag, Startstunde Wanduhr]; Gruppen einer Sportart folgen im 90-Minuten-Takt. */
    private const SCHEDULE = [
        'kampfsport' => [1, 17], 'handball' => [1, 19], 'tischtennis' => [2, 18], 'basketball' => [2, 18],
        'hockey' => [3, 17], 'volleyball' => [3, 19], 'reiten' => [4, 15], 'tennis' => [4, 17],
        'fussball' => [5, 17], 'leichtathletik' => [5, 16], 'schiesssport' => [6, 10],
    ];

    /** Startpaket → Ressourcen, die die Trainings belegen (Reihenfolge = Gruppenreihenfolge, wird zyklisch genutzt). */
    private const TRAINING_RESOURCES = [
        'kampfsport' => ['Dojo'], 'tischtennis' => ['Tischtennishalle'], 'hockey' => ['Kunstrasenplatz Hockey'], 'reiten' => ['Reithalle'],
        'fussball' => ['Rasenplatz 1', 'Kunstrasenplatz'], 'handball' => ['Sporthalle'], 'basketball' => [], 'volleyball' => ['Beachfeld'],
        'tennis' => ['Tennisplatz 1', 'Tennisplatz 2'], 'leichtathletik' => ['Laufbahn'], 'schiesssport' => ['Luftgewehrstand', 'Bogenhalle'],
    ];

    /**
     * Fiktive Personen: Vorname, Nachname, Alter, Gruppen, optional E-Mail.
     * Familie Brandt teilt ein Familienkonto; Murat Yilmaz ist in zwei Abteilungen.
     */
    private const PEOPLE = [
        ['Thomas', 'Brandt', 44, ['Fußball Erste Herren'], 'thomas.brandt@verein.demo.test'],
        ['Sabine', 'Brandt', 42, ['Volleyball Damen'], 'sabine.brandt@verein.demo.test'],
        ['Lena', 'Brandt', 9, ['Judo Kinder (6–9)', 'Fußball E-Jugend']],
        ['Jonas', 'Brandt', 14, ['LA Jugend (12–17)', 'Judo Jugend (10–15)']],
        ['Murat', 'Yilmaz', 35, ['Judo Erwachsene', 'TT Herren I'], 'murat.yilmaz@verein.demo.test'],
        ['Anke', 'Feldmann', 29, ['Judo Erwachsene']],
        ['Sven', 'Richter', 51, ['Judo Erwachsene']],
        ['Finn', 'Hoffmann', 7, ['Judo Kinder (6–9)']],
        ['Mia', 'Krüger', 8, ['Judo Kinder (6–9)']],
        ['Paul', 'Lindner', 12, ['Judo Jugend (10–15)']],
        ['Karl', 'Weber', 58, ['TT Herren I'], 'karl.weber@verein.demo.test'],
        ['Jens', 'Albrecht', 33, ['TT Herren I']],
        ['Dirk', 'Neumann', 47, ['TT Herren I']],
        ['Emil', 'Hartmann', 11, ['TT Jugend']],
        ['Nora', 'Schulz', 13, ['TT Jugend']],
        ['Petra', 'Wagner', 39, ['TT Damen I']],
        ['Ines', 'Lorenz', 45, ['TT Damen I']],
        ['Julia', 'Sommer', 24, ['Hockey Damen']],
        ['Hanna', 'Berger', 27, ['Hockey Damen']],
        ['Lea', 'Vogt', 31, ['Hockey Damen']],
        ['Katrin', 'Maier', 22, ['Hockey Damen']],
        ['Tim', 'Bauer', 11, ['Hockey Knaben B']],
        ['Noah', 'Keller', 11, ['Hockey Knaben B']],
        ['Charlotte', 'Winter', 34, ['Reitgruppe Fortgeschrittene'], 'charlotte.winter@verein.demo.test'],
        ['Sophie', 'Lang', 16, ['Reitgruppe Fortgeschrittene']],
        ['Emma', 'Fischer', 9, ['Reitgruppe Anfänger', 'Voltigieren']],
        ['Ben', 'Schäfer', 11, ['Reitgruppe Anfänger']],
        ['Ute', 'Brenner', 48, ['Reitgruppe Anfänger']],
        ['Luis', 'Wolf', 9, ['Fußball E-Jugend']],
        ['Elias', 'Schmitt', 10, ['Fußball E-Jugend']],
        ['Ali', 'Demir', 9, ['Fußball E-Jugend']],
        ['Leon', 'Koch', 9, ['Fußball E-Jugend']],
        ['Oskar', 'Braun', 9, ['Fußball E-Jugend']],
        ['Marco', 'Peters', 28, ['Fußball Erste Herren']],
        ['Daniel', 'Krause', 31, ['Fußball Erste Herren']],
        ['Kevin', 'Schröder', 25, ['Fußball Erste Herren']],
        ['Tobias', 'Frank', 34, ['Fußball Erste Herren']],
        ['Nico', 'Zimmermann', 22, ['Fußball Erste Herren']],
        ['Yannick', 'Roth', 27, ['Fußball Erste Herren']],
        ['Stefan', 'Huber', 30, ['Handball Herren']],
        ['Lars', 'Meier', 26, ['Handball Herren']],
        ['Florian', 'Schmid', 33, ['Handball Herren']],
        ['Jan', 'Becker', 29, ['Handball Herren']],
        ['Lukas', 'Voigt', 11, ['Handball D-Jugend']],
        ['Matteo', 'Kunz', 12, ['Handball D-Jugend']],
        ['Samuel', 'Otto', 24, ['Basketball Herren']],
        ['David', 'Ludwig', 21, ['Basketball Herren']],
        ['Jonas', 'Weiß', 27, ['Basketball Herren']],
        ['Erik', 'Pohl', 30, ['Basketball Herren']],
        ['Laura', 'Engel', 26, ['Volleyball Damen']],
        ['Marie', 'Haas', 29, ['Volleyball Damen']],
        ['Nina', 'Seidel', 24, ['Volleyball Damen']],
        ['Peter', 'Graf', 52, ['Tennis Herren 40'], 'peter.graf@verein.demo.test'],
        ['Ralf', 'Ebert', 46, ['Tennis Herren 40']],
        ['Bernd', 'Klein', 61, ['Tennis Herren 40']],
        ['Clara', 'Busch', 12, ['Tennis Jugend']],
        ['Felix', 'Arnold', 15, ['Tennis Jugend']],
        ['Amelie', 'Kaiser', 15, ['LA Jugend (12–17)']],
        ['Max', 'Lehmann', 16, ['LA Jugend (12–17)']],
        ['Sarah', 'Böhm', 23, ['LA Aktive']],
        ['Philipp', 'Jung', 20, ['LA Aktive']],
        ['Heinz', 'Baumann', 66, ['Schützen Aktive'], 'heinz.baumann@verein.demo.test'],
        ['Gerd', 'Sauer', 58, ['Schützen Aktive']],
        ['Monika', 'Kraft', 54, ['Schützen Aktive']],
        ['Rainer', 'Vogel', 49, ['Schützen Aktive']],
        ['Lisa', 'Reuter', 15, ['Bogen', 'Schützen Jugend']],
        ['Tom', 'Fuchs', 13, ['Bogen']],
    ];

    /** Mannschaft → [Gegner der drei Spieltage, Wettbewerb, Spielort] */
    private const MATCHES = [
        'Fußball E-Jugend' => [['SG Nachbarort', 'JSG Flusstal', 'FC Beispielheim'], 'Kreisklasse E-Junioren', 'Rasenplatz 1'],
        'Fußball Erste Herren' => [['SV Musterhausen', 'TSV Beispieldorf', 'FC Beispielheim II'], 'Kreisliga A', 'Rasenplatz 1'],
        'TT Herren I' => [['TTC Beispielstadt', 'SV Musterhausen', 'TTF Flusstal'], 'Bezirksklasse', 'Tischtennishalle'],
        'Hockey Damen' => [['HC Beispielstadt', 'THC Flusstal', 'HTC Musterhausen'], 'Oberliga', 'Kunstrasenplatz Hockey'],
        'Handball Herren' => [['HSG Flusstal', 'TV Beispieldorf', 'SG Musterhausen'], 'Bezirksliga', 'Sporthalle'],
        'Basketball Herren' => [['BC Beispielstadt', 'TV Flusstal Baskets', 'SV Musterhausen'], 'Kreisliga', 'Sporthalle'],
        'Volleyball Damen' => [['VC Beispielstadt', 'TSV Flusstal', 'SG Musterhausen'], 'Bezirksliga', 'Sporthalle'],
        'Tennis Herren 40' => [['TC Beispielstadt', 'TC Grün-Weiß Flusstal', 'TC Musterhausen'], 'Bezirksklasse Herren 40', 'Tennisplatz 1'],
    ];

    /** @var array<string, ClubGroup> */
    private array $groups = [];

    /** @var array<string, ClubMember> */
    private array $members = [];

    public function __construct(
        private readonly ClubStarterPackService $packs,
        private readonly ClubMemberService $memberService,
        private readonly ClubGroupService $groupService,
        private readonly ClubEventService $events,
        private readonly ClubAttendanceService $attendance,
        private readonly ClubFeeService $fees,
        private readonly ClubFeeRunService $feeRuns,
        private readonly ClubFeePaymentService $payments,
        private readonly ClubTeamService $teams,
        private readonly ClubMatchService $matches,
        private readonly ClubCompetitionService $competitions,
        private readonly ClubExamService $exams,
        private readonly ClubGradingService $grading,
        private readonly ClubHorseService $horses,
        private readonly ClubResourceService $resources,
    ) {}

    /**
     * @param  Collection<int, User>  $users  Demo-Nutzer in Seed-Reihenfolge (Admin, Operator A, Operator B, Disponent, …)
     * @return array{packs: int, members: int, events: int, sheets: int, matches: int, claims: int, payments: int}
     */
    public function seed(Organization $organization, User $actor, Collection $users): array {
        $today = CarbonImmutable::today(self::TIMEZONE);
        $this->groups = [];
        $this->members = [];
        $counts = ['packs' => 0, 'members' => 0, 'events' => 0, 'sheets' => 0, 'matches' => 0, 'claims' => 0, 'payments' => 0];

        foreach (array_keys(self::SCHEDULE) as $code) {
            $this->packs->install($organization, $code, $actor);
            $counts['packs']++;
        }
        foreach (ClubGroup::query()->where('organization_id', $organization->id)->get() as $group) {
            $this->groups[$group->name] = $group;
        }

        /** @var User $leader Übungsleitung (Demo Disponent) */
        $leader = $users->get(3) ?? $actor;
        /** @var User $playerCoach Spielertrainer mit eigener Mitgliedschaft (Demo Operator A) */
        $playerCoach = $users->get(1) ?? $actor;
        /** @var User $guardianUser Elternteil mit Login (Demo Operator B) */
        $guardianUser = $users->get(2) ?? $actor;
        foreach ($this->groups as $name => $group) {
            $group->update(['leader_user_id' => in_array($name, ['Fußball Erste Herren', 'TT Herren I'], true) ? $playerCoach->id : $leader->id]);
        }

        $counts['members'] = $this->seedMembers($organization, $actor, $today, $playerCoach, $guardianUser);
        $this->seedFees($organization, $actor, $today);
        $counts['events'] += $this->seedTrainings($organization, $actor, $leader, $today);
        $counts['sheets'] = $this->seedAttendance($organization, $actor, $today);
        [$matchCount, $matchEvents] = $this->seedTeams($organization, $actor, $today, $playerCoach);
        $counts['matches'] = $matchCount;
        $counts['events'] += $matchEvents;
        $counts['events'] += $this->seedCompetitions($organization, $actor, $today);
        $counts['events'] += $this->seedGrading($organization, $actor, $leader, $today);
        $this->seedHorses($organization, $actor, $today);
        [$counts['claims'], $counts['payments']] = $this->seedFeeRun($organization, $actor, $today);

        return $counts;
    }

    /** Alle Vereinsdaten der Organisation entfernen (Demo-Reset); Kunden der Beitragskonten löscht der Demo-Seeder danach. */
    public function purge(Organization $organization): void {
        $eventIds = DB::table('club_event_details')->where('organization_id', $organization->id)->pluck('event_id');
        DB::table('events')->whereIn('id', $eventIds)->delete();

        $candidateIds = DB::table('club_exam_candidates')->where('organization_id', $organization->id)->pluck('id');
        DB::table('club_exam_candidate_records')->whereIn('club_exam_candidate_id', $candidateIds)->delete();
        $offerIds = DB::table('club_exam_offers')->where('organization_id', $organization->id)->pluck('id');
        DB::table('club_exam_offer_grades')->whereIn('club_exam_offer_id', $offerIds)->delete();

        // Blätter zuerst, dann Gruppen/Abteilungen, Graduierung, Sportarten, Personen.
        $tables = [
            'club_horse_uses', 'club_horse_assignments', 'club_horse_groups', 'club_resource_clearances', 'club_resource_closures', 'club_resource_bookings',
            'club_performances', 'club_start_rights', 'club_competition_entries', 'club_competition_details', 'club_attendance_requirements',
            'club_lineup_entries', 'club_match_availabilities', 'club_event_roles', 'club_match_proposals', 'club_match_details', 'club_squad_members', 'club_squads', 'club_seasons',
            'club_fee_dunnings', 'club_fee_payments', 'club_fee_claim_items', 'club_fee_claims', 'club_fee_runs', 'club_fee_assignments', 'club_fee_exemptions', 'club_fee_surcharges',
            'club_fee_accounts', 'club_fee_tariff_rates', 'club_fee_tariffs',
            'club_member_grades', 'club_exam_candidates', 'club_exam_offers', 'club_member_proofs',
            'club_attendance_revisions', 'club_attendance_confirmations', 'club_attendance_records', 'club_attendance_sheets',
            'club_event_participations', 'club_event_groups', 'club_event_details', 'club_notifications', 'club_group_change_proposals', 'club_group_memberships',
            'club_horses',
        ];
        foreach ($tables as $table) {
            DB::table($table)->where('organization_id', $organization->id)->delete();
        }
        // Ressourcenbaum von den Blättern her — die Elternbeziehung verbietet das Löschen belegter Knoten.
        for ($depth = 0; $depth <= ClubResource::MAX_DEPTH; $depth++) {
            $parentIds = DB::table('club_resources')->where('organization_id', $organization->id)->whereNotNull('parent_id')->pluck('parent_id');
            DB::table('club_resources')->where('organization_id', $organization->id)->whereNotIn('id', $parentIds)->delete();
        }
        foreach (['club_groups', 'club_departments', 'club_grade_requirements', 'club_grading_versions', 'club_grades', 'club_grading_systems', 'club_sport_profiles', 'club_guardians', 'club_membership_periods', 'club_members'] as $table) {
            DB::table($table)->where('organization_id', $organization->id)->delete();
        }
    }

    // ── Personen ────────────────────────────────────────────────────────

    private function seedMembers(Organization $organization, User $actor, CarbonImmutable $today, User $playerCoach, User $guardianUser): int {
        $joined = $today->subYears(2)->startOfYear();
        $admitted = $today->subMonths(2);
        $count = 0;
        foreach ($this->people() as $index => $person) {
            [$first, $last, $age, $groupNames] = $person;
            $member = $this->memberService->create($organization, $actor, [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $person[4] ?? null,
                'birth_date' => $today->subYears($age)->subDays(100)->toDateString(),
                'joined_on' => $joined->addMonths($index % 18)->toDateString(),
                'city' => 'Musterstadt',
                'user_id' => $first === 'Thomas' && $last === 'Brandt' ? $playerCoach->id : null,
            ]);
            $this->members[$first . ' ' . $last] = $member;
            $count++;

            if ($age < 18) {
                $isBrandt = $last === 'Brandt';
                $this->memberService->addGuardian($member, [
                    'name' => $isBrandt ? 'Sabine Brandt' : 'Erziehungsberechtigte Familie ' . $last,
                    'email' => $isBrandt ? 'sabine.brandt@verein.demo.test' : null,
                    'user_id' => $isBrandt ? $guardianUser->id : null,
                    'permissions' => [ClubGuardianPermission::Register->value, ClubGuardianPermission::ViewAttendance->value, ClubGuardianPermission::ReceiveMessages->value],
                ], $actor);
            }

            foreach ($groupNames as $groupName) {
                $this->groupService->admit($this->group($groupName), $member, $admitted, $actor);
            }
        }

        return $count;
    }

    // ── Beiträge ────────────────────────────────────────────────────────

    private function seedFees(Organization $organization, User $actor, CarbonImmutable $today): void {
        $rateFrom = $today->subYears(3)->startOfYear()->toDateString();
        $adult = $this->fees->createTariff($organization, ['name' => 'Erwachsene', 'kind' => ClubFeeTariffKind::Individual->value, 'min_age' => 18, 'description' => 'Vollmitglied ab 18 Jahren', 'sort_order' => 1]);
        $this->fees->saveRate($adult, ['valid_from' => $rateFrom, 'interval' => 'quarterly', 'amount' => '36.00', 'anchor_month' => 1, 'due_days' => 14, 'admission_fee' => '10.00']);
        $youth = $this->fees->createTariff($organization, ['name' => 'Kinder und Jugendliche', 'kind' => ClubFeeTariffKind::Individual->value, 'max_age' => 17, 'description' => 'Bis 17 Jahre', 'sort_order' => 2]);
        $this->fees->saveRate($youth, ['valid_from' => $rateFrom, 'interval' => 'quarterly', 'amount' => '21.00', 'anchor_month' => 1, 'due_days' => 14]);
        $family = $this->fees->createTariff($organization, ['name' => 'Familie', 'kind' => ClubFeeTariffKind::Family->value, 'description' => 'Ein Beitrag je Haushalt, alle Mitglieder eines Kontos', 'sort_order' => 3]);
        $this->fees->saveRate($family, ['valid_from' => $rateFrom, 'interval' => 'quarterly', 'amount' => '60.00', 'anchor_month' => 1, 'due_days' => 14]);

        foreach ([['Reiten', '15.00', 'monthly', 'Reitzuschlag (Pferdehaltung)'], ['Tennis', '10.00', 'quarterly', 'Platzumlage Tennis']] as [$department, $amount, $interval, $name]) {
            $departmentId = (int) DB::table('club_departments')->where('organization_id', $organization->id)->where('name', $department)->value('id');
            $this->fees->saveSurcharge($organization, ['club_department_id' => $departmentId, 'name' => $name, 'interval' => $interval, 'amount' => $amount, 'anchor_month' => 1, 'valid_from' => $rateFrom]);
        }

        $familyAccount = $this->fees->createAccount($organization, ['name' => 'Familie Brandt', 'email' => 'thomas.brandt@verein.demo.test'], $actor);
        foreach ($this->members as $name => $member) {
            $isBrandt = str_ends_with($name, ' Brandt');
            $isAdult = ($member->ageOn($today) ?? 0) >= 18;
            $account = $isBrandt ? $familyAccount : $this->fees->createAccount($organization, [
                'name' => $isAdult ? $name : 'Familie ' . $member->last_name . ' (' . $member->first_name . ')',
                'email' => $member->email,
            ], $actor);
            $attributes = ['valid_from' => CarbonImmutable::instance($member->joined_on)->toDateString()];
            if ($name === 'Heinz Baumann') {
                $attributes += ['discount_percent' => '50', 'discount_reason' => 'Ehrenmitglied seit 40 Jahren'];
            }
            $this->fees->assign($account, $member, $isBrandt ? $family : ($isAdult ? $adult : $youth), $attributes);
        }
    }

    /** @return array{0: int, 1: int} Forderungen, Zahlungen */
    private function seedFeeRun(Organization $organization, User $actor, CarbonImmutable $today): array {
        $month = $today->month - (($today->month - 1) % 3);
        $run = $this->feeRuns->prepare($organization, $today->year, $month, $actor);
        if ($run->hasIssues() || $this->feeRuns->externalBillingMode($organization) !== null) {
            return [0, 0];
        }
        $claims = $this->feeRuns->release($run, $actor)->sortBy('id')->values();
        $payments = 0;
        foreach ($claims as $index => $claim) {
            /** @var ClubFeeClaim $claim */
            $slot = $index % 6;
            if ($slot === 5) {
                // Offener Rückstand: erste Mahnung, sobald fällig.
                if ($claim->refresh()->isOverdue()) {
                    $this->payments->dun($claim, ['pay_until' => $today->addDays(14)->toDateString(), 'note' => 'Zahlungserinnerung per E-Mail'], $actor);
                }

                continue;
            }
            $amount = $slot === 4 ? $claim->total->dividedBy(2)->getAmount() : $claim->total->getAmount();
            $paidOn = CarbonImmutable::instance($claim->due_on)->subDays(3);
            $account = ClubFeeAccount::query()->findOrFail($claim->club_fee_account_id);
            $this->payments->recordPayment($account, [
                'amount' => (string) $amount,
                'paid_on' => ($paidOn->greaterThan($today) ? $today : $paidOn)->toDateString(),
                'method' => $slot % 2 === 0 ? ClubFeePaymentMethod::Transfer->value : ClubFeePaymentMethod::Cash->value,
                'reference' => 'Beitrag ' . $claim->number,
                'claim_id' => $claim->id,
            ], $actor);
            $payments++;
        }

        return [$claims->count(), $payments];
    }

    // ── Training und Anwesenheit ────────────────────────────────────────

    private function seedTrainings(Organization $organization, User $actor, User $leader, CarbonImmutable $today): int {
        $count = 0;
        foreach (self::SCHEDULE as $code => [$weekday, $hour]) {
            $groups = $this->groupsOfPack($organization, $code);
            $resources = self::TRAINING_RESOURCES[$code];
            foreach ($groups as $index => $group) {
                $start = $today->previous($weekday)->setTime($hour, 0)->addMinutes(90 * $index);
                $resource = $resources !== [] ? $this->resource($organization, $resources[$index % count($resources)]) : null;
                foreach ([-3, -2, -1, 0, 1] as $week) {
                    $begin = $start->addWeeks($week);
                    if ($week === 0 && $begin->greaterThan($today->endOfDay())) {
                        continue;
                    }
                    $event = $this->events->create($organization, $actor, [
                        'kind' => ClubEventKind::Training->value,
                        'title' => 'Training ' . $group->name,
                        'visibility' => ClubEventVisibility::Groups->value,
                        'club_department_id' => $group->club_department_id,
                        'discipline' => $group->discipline,
                        'club_group_ids' => [$group->id],
                        'started_at' => $begin->utc()->format('Y-m-d H:i:s'),
                        'ended_at' => $begin->addMinutes(90)->utc()->format('Y-m-d H:i:s'),
                        'timezone' => self::TIMEZONE,
                        'leader_user_id' => $group->leader_user_id ?? $leader->id,
                    ]);
                    if ($resource !== null) {
                        $this->resources->book($event, $resource, [], $actor);
                    }
                    $count++;
                }
            }
        }

        return $count;
    }

    private function seedAttendance(Organization $organization, User $actor, CarbonImmutable $today): int {
        $count = 0;
        $past = Event::query()->withoutGlobalScopes()->where('organization_id', $organization->id)
            ->whereHas('clubDetails', fn($q) => $q->where('kind', ClubEventKind::Training->value))
            ->where('ended_at', '<', $today->utc())
            ->orderBy('started_at')->get();
        foreach ($past as $index => $event) {
            $sheet = $this->attendance->sheetFor($event);
            $roster = $this->attendance->rosterFor($sheet, $event);
            if ($roster->isEmpty()) {
                continue;
            }
            $rows = [];
            foreach ($roster->values() as $position => $member) {
                $rows[$member->id] = ['status' => 'present'];
                if ($position === $roster->count() - 1) {
                    $rows[$member->id] = $index % 3 === 0 ? ['status' => 'excused'] : ($index % 3 === 1 ? ['status' => 'absent'] : ['status' => 'partial', 'minutes' => 45]);
                }
            }
            $sheet = $this->attendance->saveRows($sheet, $rows, $actor, $sheet->version, 90);
            $this->attendance->confirm($sheet, $actor, $sheet->version);
            $count++;
        }

        return $count;
    }

    // ── Mannschaften und Spieltage ──────────────────────────────────────

    /** @return array{0: int, 1: int} Spieltage, davon angelegte Termine */
    private function seedTeams(Organization $organization, User $actor, CarbonImmutable $today, User $playerCoach): array {
        $seasonStart = $today->subMonths(6)->startOfMonth();
        $seasonEnd = $today->addMonths(6)->endOfMonth();
        $season = $this->teams->createSeason($organization, [
            'name' => 'Saison ' . $seasonStart->format('Y') . '/' . $seasonEnd->format('y'),
            'starts_on' => $seasonStart->toDateString(),
            'ends_on' => $seasonEnd->toDateString(),
        ]);

        $matchCount = 0;
        foreach (self::MATCHES as $teamName => [$opponents, $competition, $venue]) {
            $team = $this->group($teamName);
            $profile = $this->teams->profileFor($team);
            $squad = $this->teams->squadFor($team, $season);
            if ($squad === null || $profile === null) {
                continue;
            }
            $positions = $profile->positionCodes();
            $roster = $this->teamMembers($organization, $team);
            foreach ($roster as $index => $member) {
                $this->teams->addSquadMember($squad, [
                    'club_member_id' => $member->id,
                    'jersey_no' => $index + 1,
                    'position_code' => $positions !== [] ? $positions[$index % count($positions)] : null,
                    'strength_rank' => $profile->family->value === 'racket' ? $index + 1 : null,
                ], $actor);
            }
            if ($teamName === 'Fußball E-Jugend') {
                // Spielgemeinschaft: Gastspieler aus dem Nachbarverein ohne eigene Mitgliedschaft.
                $this->teams->addSquadMember($squad, [
                    'guest_first_name' => 'Mika', 'guest_last_name' => 'Sonnenberg',
                    'guest_birth_date' => $today->subYears(9)->subDays(200)->toDateString(),
                    'guest_origin' => 'SG Nachbarort (Spielgemeinschaft)',
                    'jersey_no' => $roster->count() + 1,
                ], $actor);
            }

            foreach ([-14 => 0, -7 => 1, 7 => 2] as $offset => $slot) {
                $kickoff = $today->addDays($offset)->setTime(14, 0);
                $event = $this->matches->createMatch($organization, $actor, [
                    'club_group_id' => $team->id,
                    'club_season_id' => $season->id,
                    'opponent_name' => $opponents[$slot],
                    'competition' => $competition,
                    'venue' => $slot === 1 ? 'Sportanlage ' . $opponents[$slot] : $venue,
                    'is_home' => $slot !== 1,
                    'started_at' => $kickoff->utc()->format('Y-m-d H:i:s'),
                    'ended_at' => $kickoff->addMinutes(120)->utc()->format('Y-m-d H:i:s'),
                    'timezone' => self::TIMEZONE,
                    'meet_at' => $kickoff->subMinutes(60)->utc()->format('Y-m-d H:i:s'),
                    'leader_user_id' => $team->leader_user_id,
                ]);
                $matchCount++;

                $candidates = $this->matches->candidatesFor($event)->pluck('member');
                $unavailable = $slot === 2 && $candidates->count() > 3 ? $candidates->last() : null;
                foreach ($candidates as $position => $candidate) {
                    /** @var ClubMember $candidate */
                    $status = $candidate === $unavailable ? ClubAvailabilityStatus::Unavailable : ($position % 4 === 3 ? ClubAvailabilityStatus::Maybe : ClubAvailabilityStatus::Available);
                    $this->matches->setAvailability($event, $candidate, $status, $actor, $status === ClubAvailabilityStatus::Unavailable ? 'Dienstreise' : null);
                }
                $this->matches->saveLineup($event, $actor, $this->lineupRows($profile, $candidates->reject(fn(ClubMember $m): bool => $m === $unavailable)->values()));
                $this->matches->releaseLineup($event, $actor);

                if ($teamName === 'Fußball E-Jugend') {
                    $this->matches->assignRole($event, ClubEventRoleKind::Driver, ['name' => 'Fahrdienst: Familie Brandt, Familie Wolf (2 Fahrzeuge)'], $actor);
                } elseif ($teamName === 'Fußball Erste Herren') {
                    $this->matches->assignRole($event, ClubEventRoleKind::Referee, ['name' => 'Kreisschiedsrichter (Ansetzung Verband)'], $actor);
                    $this->matches->assignRole($event, ClubEventRoleKind::Coach, ['user_id' => $playerCoach->id], $actor);
                }
                if ($slot < 2) {
                    $this->matches->recordResult($event, $actor, $this->resultInput($profile->result_format, $slot));
                }
            }
        }

        return [$matchCount, $matchCount];
    }

    /**
     * @param  Collection<int, ClubMember>  $members
     * @return list<array{club_member_id: int, slot: string, position_code?: string|null, jersey_no?: int, pairing_no?: int}>
     */
    private function lineupRows(ClubSportProfile $profile, Collection $members): array {
        $rows = [];
        if ($profile->family->value === 'racket') {
            foreach ($members->take(4) as $index => $member) {
                $rows[] = ['club_member_id' => $member->id, 'slot' => ClubLineupSlot::Single->value, 'pairing_no' => $index + 1];
            }
            if ($profile->has_doubles && $members->count() >= 2) {
                foreach ($members->take(2) as $member) {
                    $rows[] = ['club_member_id' => $member->id, 'slot' => ClubLineupSlot::Double->value, 'pairing_no' => 1];
                }
            }

            return $rows;
        }
        $positions = $profile->positionCodes();
        $fieldSize = $profile->squad_size_field ?? $members->count();
        foreach ($members as $index => $member) {
            $rows[] = [
                'club_member_id' => $member->id,
                'slot' => $index < $fieldSize ? ClubLineupSlot::Field->value : ClubLineupSlot::Bench->value,
                'position_code' => $positions !== [] ? $positions[$index % count($positions)] : null,
                'jersey_no' => $index + 1,
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function resultInput(ClubResultFormat $format, int $slot): array {
        return match ($format) {
            ClubResultFormat::Goals => $slot === 0 ? ['home' => 3, 'away' => 1] : ['home' => 1, 'away' => 2],
            ClubResultFormat::Sets => ['periods' => $slot === 0
                ? [['home' => 11, 'away' => 7], ['home' => 9, 'away' => 11], ['home' => 11, 'away' => 5], ['home' => 11, 'away' => 8]]
                : [['home' => 25, 'away' => 22], ['home' => 21, 'away' => 25], ['home' => 23, 'away' => 25], ['home' => 25, 'away' => 19], ['home' => 13, 'away' => 15]]],
            ClubResultFormat::PeriodPoints => ['periods' => [['home' => 18, 'away' => 15], ['home' => 20, 'away' => 22], ['home' => 17, 'away' => 14], ['home' => 21, 'away' => 19]]],
            ClubResultFormat::None => [],
        };
    }

    // ── Wettkampf ───────────────────────────────────────────────────────

    private function seedCompetitions(Organization $organization, User $actor, CarbonImmutable $today): int {
        $athletics = $this->profile($organization, 'Leichtathletik');
        $day = $today->subDays(10)->setTime(9, 0);
        $meet = $this->competitions->create($organization, $actor, [
            'title' => 'Kreismeisterschaft Leichtathletik',
            'club_sport_profile_id' => $athletics->id,
            'disciplines' => ['100m', 'weit'],
            'venue' => 'Stadion Musterstadt',
            'organizer' => 'Kreis-Leichtathletik-Ausschuss (fiktiv)',
            'entry_fee' => '5.00',
            'requires_start_right' => true,
            'club_group_ids' => [$this->group('LA Jugend (12–17)')->id, $this->group('LA Aktive')->id],
            'started_at' => $day->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $day->addHours(8)->utc()->format('Y-m-d H:i:s'),
            'timezone' => self::TIMEZONE,
        ]);
        foreach (['Sarah Böhm' => ['13.42', '5.21', 2], 'Philipp Jung' => ['12.08', '6.02', 1]] as $name => [$sprint, $jump, $placement]) {
            $athlete = $this->member($name);
            $this->competitions->grantStartRight($athlete, ['club_sport_profile_id' => $athletics->id, 'reference' => 'Startpass LA-' . $today->format('Y') . '-' . (400 + $placement) . ' (fiktiv)', 'valid_from' => $today->startOfYear()->toDateString()], $actor);
            $this->competitions->enter($meet, $athlete, ['100m', 'weit'], $actor, ClubParticipationSource::Leader, true);
            foreach (['100m' => $sprint, 'weit' => $jump] as $code => $value) {
                $this->competitions->recordPerformance($athlete, ['club_sport_profile_id' => $athletics->id, 'discipline_code' => $code, 'value' => $value, 'performed_on' => $day->toDateString(), 'event_id' => $meet->id, 'placement' => $placement, 'confirm' => true], $actor);
            }
        }
        // Meldung ohne Startrecht bleibt zur Klärung stehen.
        $this->competitions->enter($meet, $this->member('Amelie Kaiser'), ['100m'], $actor, ClubParticipationSource::Leader, true);

        $shooting = $this->profile($organization, 'Schießsport');
        $round = $today->subDays(4)->setTime(10, 0);
        $event = $this->competitions->create($organization, $actor, [
            'title' => 'Rundenwettkampf Luftgewehr, 2. Runde',
            'club_sport_profile_id' => $shooting->id,
            'disciplines' => ['lg'],
            'venue' => 'Luftgewehrstand',
            'organizer' => 'Schützenkreis (fiktiv)',
            'requires_start_right' => false,
            'club_group_ids' => [$this->group('Schützen Aktive')->id],
            'started_at' => $round->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $round->addHours(3)->utc()->format('Y-m-d H:i:s'),
            'timezone' => self::TIMEZONE,
        ]);
        $this->matches->assignRole($event, ClubEventRoleKind::RangeOfficer, ['club_member_id' => $this->member('Heinz Baumann')->id], $actor);
        foreach (['Gerd Sauer' => '372', 'Monika Kraft' => '365'] as $name => $rings) {
            $shooter = $this->member($name);
            $this->competitions->enter($event, $shooter, ['lg'], $actor, ClubParticipationSource::Leader, true);
            $this->competitions->recordPerformance($shooter, ['club_sport_profile_id' => $shooting->id, 'discipline_code' => 'lg', 'value' => $rings, 'performed_on' => $round->toDateString(), 'event_id' => $event->id, 'confirm' => true], $actor);
        }

        return 2;
    }

    // ── Graduierung ─────────────────────────────────────────────────────

    private function seedGrading(Organization $organization, User $actor, User $leader, CarbonImmutable $today): int {
        /** @var ClubGradingSystem $system */
        $system = ClubGradingSystem::query()->where('organization_id', $organization->id)->where('name', 'Judo Kyu-Grade')->firstOrFail();
        $grades = ClubGrade::query()->where('club_grading_system_id', $system->id)->orderBy('rank')->get()->keyBy('rank');
        $recognized = ['Finn Hoffmann' => 1, 'Mia Krüger' => 1, 'Lena Brandt' => 1, 'Paul Lindner' => 2, 'Jonas Brandt' => 2, 'Anke Feldmann' => 4, 'Murat Yilmaz' => 5, 'Sven Richter' => 6];
        foreach ($recognized as $name => $rank) {
            $this->grading->recognizeGrade($this->member($name), $this->grade($grades, $rank), $today->subMonths(8 + $rank), 'Urkunde des vorherigen Vereins (fiktiv)', $actor);
        }
        $start = $today->addDays(21)->setTime(10, 0);
        $offer = $this->exams->createOffer($organization, $actor, [
            'title' => 'Kyu-Prüfung Judo',
            'club_grading_system_id' => $system->id,
            'target_grade_ids' => [$this->grade($grades, 2)->id, $this->grade($grades, 3)->id],
            'examiner_user_ids' => [$leader->id],
            'club_group_ids' => [$this->group('Judo Kinder (6–9)')->id, $this->group('Judo Jugend (10–15)')->id],
            'started_at' => $start->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $start->addHours(3)->utc()->format('Y-m-d H:i:s'),
            'timezone' => self::TIMEZONE,
            'leader_user_id' => $leader->id,
        ]);
        foreach (['Finn Hoffmann' => 2, 'Mia Krüger' => 2, 'Paul Lindner' => 3] as $name => $rank) {
            $this->exams->addCandidate($offer, $this->member($name), $this->grade($grades, $rank), $actor);
        }

        return 1;
    }

    // ── Reitbetrieb ─────────────────────────────────────────────────────

    private function seedHorses(Organization $organization, User $actor, CarbonImmutable $today): void {
        $owner = $this->member('Charlotte Winter');
        $this->horses->create($organization, ['name' => 'Luna', 'kind' => ClubHorseKind::Private->value, 'owner_member_id' => $owner->id, 'suitable_for' => 'Fortgeschrittene, Dressur', 'max_uses_per_day' => 2, 'rest_minutes' => 30, 'contact' => 'Besitzerin: Charlotte Winter']);
        $horses = ClubHorse::query()->where('organization_id', $organization->id)->get()->keyBy('name');
        $riders = ['Fanny' => ['Emma Fischer', 'Ben Schäfer'], 'Max' => ['Sophie Lang', 'Ute Brenner'], 'Luna' => ['Charlotte Winter']];
        foreach ($riders as $horseName => $names) {
            /** @var ClubHorse $horse */
            $horse = $horses->get($horseName);
            $resource = $horse->resource()->firstOrFail();
            foreach ($names as $name) {
                // Freigabe vor den vergangenen Reitstunden datieren, sonst gälte sie erst ab heute.
                $this->resources->grantClearance($resource, $this->member($name), $actor, null, 'Reitprobe bestanden')
                    ->update(['granted_on' => $today->subYear()->toDateString()]);
            }
        }

        $lessons = Event::query()->withoutGlobalScopes()->where('organization_id', $organization->id)
            ->where('title', 'like', 'Training Reitgruppe%')->orderBy('started_at')->get();
        foreach ($lessons as $lesson) {
            $beginners = str_contains((string) $lesson->title, 'Anfänger');
            // Der Ruhepuffer der Schulpferde verbietet den Anschlusseinsatz — Ute Brenner reitet ihr eigenes Pferd.
            $plan = $beginners ? [['Fanny', 'Emma Fischer'], [null, 'Ute Brenner']] : [['Max', 'Sophie Lang'], ['Luna', 'Charlotte Winter']];
            foreach ($plan as [$horseName, $riderName]) {
                /** @var ClubHorse|null $horse */
                $horse = $horseName !== null ? $horses->get($horseName) : null;
                $this->horses->assign($lesson, $this->member($riderName), $horse, $actor, $horse === null);
                if ($horse !== null && CarbonImmutable::instance($lesson->ended_at)->lessThan($today->utc())) {
                    $this->horses->recordUse($lesson, $horse, $this->member($riderName), 45, $actor);
                }
            }
        }

        $hall = $this->resource($organization, 'Reithalle');
        $closure = $today->addDays(3);
        $this->resources->close($hall, $closure->setTime(8, 0)->utc(), $closure->setTime(20, 0)->utc(), 'Bodenpflege und Hufschmied', $actor);
    }

    // ── Helfer ──────────────────────────────────────────────────────────

    /** @return list<array{0: string, 1: string, 2: int, 3: list<string>, 4?: string}> */
    private function people(): array {
        return self::PEOPLE;
    }

    /** @param  Collection<int, ClubGrade>  $grades */
    private function grade(Collection $grades, int $rank): ClubGrade {
        return $grades->get($rank) ?? throw new \RuntimeException('Demo-Verein: Grad mit Rang ' . $rank . ' fehlt.');
    }

    /** @return Collection<int, ClubGroup> */
    private function groupsOfPack(Organization $organization, string $code): Collection {
        $names = array_map(static fn(array $g): string => (string) $g['name'], (array) ($this->packs->load($code)['groups'] ?? []));

        return collect($names)->map(fn(string $name): ClubGroup => $this->group($name))->values();
    }

    /**
     * Aktive Mitglieder der Mannschaft in fester Reihenfolge.
     *
     * @return Collection<int, ClubMember>
     */
    private function teamMembers(Organization $organization, ClubGroup $team): Collection {
        $ids = DB::table('club_group_memberships')->where('club_group_id', $team->id)->where('status', 'active')->orderBy('id')->pluck('club_member_id');

        return ClubMember::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', $ids)->orderBy('member_no')->get();
    }

    private function group(string $name): ClubGroup {
        return $this->groups[$name] ?? throw new \RuntimeException('Demo-Verein: Gruppe fehlt: ' . $name);
    }

    private function member(string $name): ClubMember {
        return $this->members[$name] ?? throw new \RuntimeException('Demo-Verein: Mitglied fehlt: ' . $name);
    }

    private function profile(Organization $organization, string $name): ClubSportProfile {
        return ClubSportProfile::query()->where('organization_id', $organization->id)->where('name', $name)->firstOrFail();
    }

    private function resource(Organization $organization, string $name): ClubResource {
        return ClubResource::query()->where('organization_id', $organization->id)->where('name', $name)->firstOrFail();
    }
}
