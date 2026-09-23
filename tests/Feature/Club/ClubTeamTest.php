<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubTeamTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubAvailabilityStatus, ClubEventRoleKind, ClubLineupStatus, ClubMatchProposalSource, ClubMembershipKind, ClubParticipationStatus, ClubProposalStatus};
use App\Enums\User\UserRole;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventParticipation, ClubGroup, ClubLineupEntry, ClubMatchProposal, ClubMember, ClubSeason, ClubSportProfile, ClubSquad};
use App\Models\Platform\User;
use App\Services\Club\{ClubFeeCalculator, ClubMatchService, ClubTeamService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Mannschaften und Spielbetrieb (Feature 159, MVP-852): Einzel/Doppel zählt
 * eine Person einmal; Zusage ist keine Nominierung; zeitgleicher Einsatz in
 * zwei Mannschaften erkennbar; Fußball-Elf mit Bank und Volleyball-Sätze aus
 * demselben Modell; Gastspieler ohne Beitrag; importierter Spielplan ändert
 * vor Bestätigung nichts; Vorsaison bleibt erhalten.
 */
class ClubTeamTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClubSeason $season;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->season = $this->teams()->createSeason($this->organization, ['name' => '2026/27', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
    }

    private function teams(): ClubTeamService {
        return app(ClubTeamService::class);
    }

    private function matchService(): ClubMatchService {
        return app(ClubMatchService::class);
    }

    private function football(): ClubSportProfile {
        return $this->teams()->createProfile($this->organization, [
            'name' => 'Fußball', 'family' => 'team_ball', 'result_format' => 'goals', 'squad_size_field' => 11, 'squad_size_bench' => 7,
            'positions' => "tw=Torwart\nab=Abwehr\nmf=Mittelfeld\nst=Sturm", 'age_cutoff' => '01-01',
        ]);
    }

    private function team(ClubSportProfile $profile, string $name = 'Erste Herren', ?User $leader = null): ClubGroup {
        return ClubGroup::factory()->create(['name' => $name, 'is_team' => true, 'club_sport_profile_id' => $profile->id, 'leader_user_id' => $leader?->id]);
    }

    /** @return list<ClubMember> */
    private function squadOf(ClubGroup $team, int $count): array {
        $squad = $this->teams()->squadFor($team, $this->season);
        $members = [];
        for ($i = 0; $i < $count; $i++) {
            $member = ClubMember::factory()->aged(22)->create();
            $this->teams()->addSquadMember($squad, ['club_member_id' => $member->id, 'jersey_no' => $i + 1], $this->admin);
            $members[] = $member;
        }

        return $members;
    }

    private function match(ClubGroup $team, string $start = '2026-09-26 15:00:00', string $opponent = 'FC Beispiel'): Event {
        $begin = CarbonImmutable::parse($start, 'Europe/Berlin');

        return $this->matchService()->createMatch($this->organization, $this->admin, [
            'club_group_id' => $team->id, 'opponent_name' => $opponent, 'competition' => 'Kreisliga', 'is_home' => true,
            'started_at' => $begin->utc()->format('Y-m-d H:i:s'), 'ended_at' => $begin->addHours(2)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin',
        ]);
    }

    /** @param  list<array{ClubMember, string, ?int}>  $rows  Mitglied, Platz, Paarung */
    private function lineup(Event $event, array $rows, bool $release = false): void {
        $this->matchService()->saveLineup($event, $this->admin, array_map(static fn(array $r): array => ['club_member_id' => $r[0]->id, 'slot' => $r[1], 'pairing_no' => $r[2] ?? null], $rows));
        if ($release) {
            $this->matchService()->releaseLineup($event, $this->admin);
        }
    }

    public function test_singles_and_doubles_count_a_person_once_and_an_answer_is_no_nomination(): void {
        $profile = $this->teams()->createProfile($this->organization, ['name' => 'Tischtennis', 'family' => 'racket', 'result_format' => 'sets', 'has_doubles' => true]);
        $team = $this->team($profile, 'TT Herren I');
        [$a, $b, $c] = $this->squadOf($team, 3);
        $event = $this->match($team, '2026-10-03 18:00:00', 'TTC Gegner');

        $this->matchService()->setAvailability($event, $a, ClubAvailabilityStatus::Available, $this->admin);
        $this->assertSame(0, ClubLineupEntry::query()->where('event_id', $event->id)->count(), 'Zusage ist keine Nominierung.');
        $this->assertSame(0, ClubEventParticipation::query()->where('event_id', $event->id)->count());

        $this->lineup($event, [[$a, 'single', 1], [$a, 'double', 1], [$b, 'double', 1], [$b, 'single', 2], [$c, 'single', 3]], true);
        $this->assertSame(5, ClubLineupEntry::query()->where('event_id', $event->id)->count());
        $this->assertSame(3, ClubLineupEntry::query()->where('event_id', $event->id)->distinct()->count('club_member_id'));
        $this->assertSame(3, ClubEventParticipation::query()->where('event_id', $event->id)->where('status', ClubParticipationStatus::Registered->value)->count(), 'Einzel und Doppel: eine Person, eine Teilnahme.');
        $this->assertSame(3, $this->matchService()->candidatesFor($event)->count(), 'Eine Person je Zeile.');

        try {
            $this->lineup($event, [[$a, 'single', 1], [$a, 'single', 1]]);
            $this->fail('Dieselbe Person zweimal auf demselben Platz.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lineup', $e->errors());
        }
        try {
            $this->lineup($event, [[$a, 'double', 1], [$b, 'double', 1], [$c, 'double', 1]]);
            $this->fail('Doppel mit drei Personen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lineup', $e->errors());
        }
    }

    public function test_simultaneous_lineups_in_two_teams_are_detected_and_need_an_explicit_override(): void {
        $profile = $this->football();
        $first = $this->team($profile, 'Erste Herren');
        $second = $this->team($profile, 'Zweite Herren');
        [$player] = $this->squadOf($first, 1);
        $this->teams()->addSquadMember($this->teams()->squadFor($second, $this->season), ['club_member_id' => $player->id], $this->admin);
        $one = $this->match($first, '2026-09-26 15:00:00', 'FC Eins');
        $two = $this->match($second, '2026-09-26 16:00:00', 'FC Zwei');

        $this->lineup($one, [[$player, 'field', null]], true);
        $this->lineup($two, [[$player, 'field', null]]);
        $conflicts = $this->matchService()->conflictsFor($two);
        $this->assertCount(1, $conflicts);
        $this->assertSame('overlap', $conflicts[0]['reason']);
        $this->assertSame($one->id, $conflicts[0]['event']?->id);
        try {
            $this->matchService()->releaseLineup($two, $this->admin);
            $this->fail('Zeitgleicher Einsatz sperrt die Freigabe.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lineup', $e->errors());
        }
        $this->assertSame(ClubLineupStatus::Draft, $this->matchService()->detailsOf($two)->lineup_status);
        try {
            $this->matchService()->releaseLineup($two, $this->admin, true);
            $this->fail('Übergehen braucht eine Begründung.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }
        $details = $this->matchService()->releaseLineup($two, $this->admin, true, 'Spielt nach Absprache beide Spiele');
        $this->assertSame(ClubLineupStatus::Released, $details->lineup_status);
        $this->assertNotNull($details->lineup_conflict_note);

        $this->matchService()->setAvailability($one, $player, ClubAvailabilityStatus::Unavailable, $this->admin, 'krank');
        $this->assertContains('unavailable', array_column($this->matchService()->conflictsFor($one), 'reason'), 'Absage eines Aufgestellten ist ein Konflikt.');
    }

    public function test_football_eleven_with_bench_and_volleyball_sets_come_from_the_same_model(): void {
        $football = $this->football();
        $eleven = $this->team($football, 'Fußball I');
        $players = $this->squadOf($eleven, 13);
        $event = $this->match($eleven);
        $rows = [];
        foreach ($players as $i => $player) {
            $rows[] = [$player, $i < 11 ? 'field' : 'bench', null];
        }
        $this->lineup($event, $rows, true);
        $this->assertSame(11, ClubLineupEntry::query()->where('event_id', $event->id)->where('slot', 'field')->count());
        $this->assertSame(2, ClubLineupEntry::query()->where('event_id', $event->id)->where('slot', 'bench')->count());
        try {
            $rows[12] = [$players[12], 'field', null];
            $rows[11] = [$players[11], 'field', null];
            $this->lineup($event, $rows);
            $this->fail('Mehr als elf Feldspieler.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lineup', $e->errors());
        }
        try {
            $this->matchService()->saveLineup($event, $this->admin, [['club_member_id' => $players[0]->id, 'slot' => 'single']]);
            $this->fail('Einzel gibt es nicht im Fußballprofil.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lineup', $e->errors());
        }
        $details = $this->matchService()->recordResult($event, $this->admin, ['home' => '3', 'away' => '1', 'result_note' => 'Tore: 2× Müller, 1× Schulz']);
        $this->assertSame('3:1', $details->result_summary);
        $this->assertSame(['home' => 3, 'away' => 1], $details->result);

        $volleyball = $this->teams()->createProfile($this->organization, ['name' => 'Volleyball', 'family' => 'team_ball', 'result_format' => 'sets', 'squad_size_field' => 6, 'squad_size_bench' => 6]);
        $six = $this->team($volleyball, 'Volleyball Damen');
        $this->squadOf($six, 6);
        $game = $this->match($six, '2026-10-10 14:00:00', 'VC Netz');
        $sets = $this->matchService()->recordResult($game, $this->admin, ['periods' => [['home' => 25, 'away' => 20], ['home' => 25, 'away' => 23], ['home' => 23, 'away' => 25], ['home' => 25, 'away' => 18], ['home' => '', 'away' => '']]]);
        $this->assertSame('3:1 (25:20, 25:23, 23:25, 25:18)', $sets->result_summary);
        $this->assertSame(3, $sets->result['home']);

        $basketball = $this->teams()->createProfile($this->organization, ['name' => 'Basketball', 'family' => 'team_ball', 'result_format' => 'period_points', 'squad_size_field' => 5]);
        $five = $this->team($basketball, 'Basketball U16');
        $quarters = $this->matchService()->recordResult($this->match($five, '2026-10-11 11:00:00', 'BC Korb'), $this->admin, ['periods' => [['home' => 20, 'away' => 18], ['home' => 22, 'away' => 25], ['home' => 19, 'away' => 17], ['home' => 20, 'away' => 15]]]);
        $this->assertSame('81:75 (20:18, 22:25, 19:17, 20:15)', $quarters->result_summary);
    }

    public function test_guest_players_carry_their_origin_and_owe_no_fee(): void {
        $team = $this->team($this->football(), 'Fußball A-Jugend');
        $squad = $this->teams()->squadFor($team, $this->season);
        $entry = $this->teams()->addSquadMember($squad, ['guest_first_name' => 'Gast', 'guest_last_name' => 'Spieler', 'guest_birth_date' => '2009-03-01', 'guest_origin' => 'SG Partner'], $this->admin);
        $this->assertTrue($entry->isGuest());
        $guest = $entry->member()->firstOrFail();
        $this->assertSame(ClubMembershipKind::Guest, $guest->kind);
        $this->assertNull($guest->user_id);
        $this->assertSame(0, $guest->groupMemberships()->count(), 'Gast ist kein Gruppenmitglied.');
        $this->assertSame(0, $guest->feeAssignments()->count());
        $this->assertCount(0, app(ClubFeeCalculator::class)->calculateMonth($this->organization, 2026, 9)['positions'], 'Gastspieler ohne Beitrag.');
        $this->assertTrue($this->teams()->rosterOn($team, CarbonImmutable::parse('2026-09-26'), $this->season)->contains(fn(array $row): bool => $row['member']->id === $guest->id));
        try {
            $this->teams()->addSquadMember($squad, ['guest_first_name' => 'Ohne', 'guest_last_name' => 'Herkunft'], $this->admin);
            $this->fail('Gast ohne Herkunftsverein.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('guest_origin', $e->errors());
        }
        $this->assertSame(17, $this->teams()->ageClassOf($guest, $team, CarbonImmutable::parse('2026-09-26')), 'Alter am Stichtag 01.01.2027 der Saison, nicht am Spieltag.');
        $this->assertSame(17, $guest->ageOn(CarbonImmutable::parse('2027-01-01')));
    }

    public function test_imported_fixtures_change_nothing_before_acceptance_and_reimport_creates_no_duplicates(): void {
        $team = $this->team($this->football(), 'Erste Herren');
        $csv = "Datum;Zeit;Gegner;Heim;Spielort;Wettbewerb\n05.09.2026;15:00;SV Nord;H;Sportplatz Nord;Kreisliga\n12.09.2026;14:30;TuS Süd;A;Waldstadion;Kreisliga\n19.09.2026;15:00;FC West;H;;Kreisliga\n";
        $summary = $this->matchService()->importProposals($team, $this->admin, ClubMatchProposalSource::Csv, $csv, 'Europe/Berlin');
        $this->assertSame(3, $summary['created']);
        $this->assertSame([], $summary['errors']);
        $this->assertSame(0, Event::query()->whereHas('clubMatch')->count(), 'Vorschläge legen nichts an.');

        $proposal = ClubMatchProposal::query()->where('opponent_name', 'TuS Süd')->firstOrFail();
        $this->assertFalse($proposal->is_home);
        $this->assertSame('Waldstadion', $proposal->venue);
        $this->assertSame('2026-09-12 12:30:00', $proposal->starts_at->utc()->format('Y-m-d H:i:s'));
        $event = $this->matchService()->acceptProposal($proposal, $this->admin, ['venue' => 'Waldstadion, Platz 2']);
        $this->assertSame(ClubProposalStatus::Confirmed, $proposal->refresh()->status);
        $this->assertSame($event->id, $proposal->event_id);
        $this->assertSame('TuS Süd – Erste Herren', $event->title);
        $this->assertSame('Waldstadion, Platz 2', $this->matchService()->detailsOf($event)->venue);
        $this->assertSame(1, Event::query()->whereHas('clubMatch')->count());

        $again = $this->matchService()->importProposals($team, $this->admin, ClubMatchProposalSource::Csv, $csv, 'Europe/Berlin');
        $this->assertSame(0, $again['created']);
        $this->assertSame(3, $again['skipped'], 'Bekannte Zeilen werden übersprungen.');
        $this->assertSame(3, ClubMatchProposal::query()->count());

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//DE\r\nBEGIN:VEVENT\r\nUID:x1\r\nDTSTART;TZID=Europe/Berlin:20260912T150000\r\nDTEND;TZID=Europe/Berlin:20260912T170000\r\nSUMMARY:TuS Süd - Erste Herren\r\nLOCATION:Waldstadion\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $fromIcs = $this->matchService()->importProposals($team, $this->admin, ClubMatchProposalSource::Ics, $ics, 'Europe/Berlin');
        $this->assertSame(1, $fromIcs['created']);
        $this->assertSame(1, $fromIcs['duplicates'], 'Bestehender Spieltag am selben Tag als Dublette markiert, auch bei anderer Anstoßzeit.');
        $dup = ClubMatchProposal::query()->where('source', 'ics')->firstOrFail();
        $this->assertSame($event->id, $dup->duplicate_event_id);
        $this->matchService()->dismissProposal($dup, $this->admin);
        $this->assertSame(ClubProposalStatus::Dismissed, $dup->refresh()->status);
    }

    public function test_previous_season_squad_is_kept_when_the_next_season_starts(): void {
        $team = $this->team($this->football());
        [$veteran, $stays] = $this->squadOf($team, 2);
        $next = $this->teams()->createSeason($this->organization, ['name' => '2027/28', 'starts_on' => '2027-07-01', 'ends_on' => '2028-06-30']);
        $newSquad = $this->teams()->squadFor($team, $next);
        $this->teams()->addSquadMember($newSquad, ['club_member_id' => $stays->id, 'jersey_no' => 10], $this->admin);
        $rookie = ClubMember::factory()->aged(19)->create();
        $this->teams()->addSquadMember($newSquad, ['club_member_id' => $rookie->id], $this->admin);

        $old = $this->teams()->squadFor($team, $this->season, false);
        $this->assertNotNull($old);
        $this->assertSame(2, $old->members()->count(), 'Vorsaison unverändert.');
        $this->assertSame(2, $newSquad->members()->count());
        $this->assertSame(2, ClubSquad::query()->where('club_group_id', $team->id)->count());
        $this->assertTrue($this->teams()->rosterOn($team, CarbonImmutable::parse('2027-09-01'), $next)->pluck('member.id')->doesntContain($veteran->id));
        $this->assertTrue($this->teams()->rosterOn($team, CarbonImmutable::parse('2026-09-01'), $this->season)->pluck('member.id')->contains($veteran->id));
        $ended = $this->teams()->endSquadMember($old->members()->where('club_member_id', $veteran->id)->firstOrFail(), CarbonImmutable::parse('2026-12-31'), $this->admin);
        $this->assertSame('2026-12-31', $ended->valid_to?->toDateString());
        $this->assertSame(2, $old->members()->count(), 'Beenden löscht nicht.');
    }

    public function test_roles_count_as_participation_and_pages_and_rights_work(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $profile = $this->football();
        $team = $this->team($profile, 'Erste Herren', $lead);
        $other = $this->team($profile, 'Zweite Herren');
        [$player, $driver] = $this->squadOf($team, 2);
        $this->squadOf($other, 1);
        $event = $this->match($team);
        $foreign = $this->match($other, '2026-09-27 15:00:00', 'FC Fremd');

        $role = $this->matchService()->assignRole($event, ClubEventRoleKind::Driver, ['club_member_id' => $driver->id], $this->admin);
        $this->assertSame($driver->id, $role->club_member_id);
        $this->assertSame(1, ClubEventParticipation::query()->where('event_id', $event->id)->where('club_member_id', $driver->id)->count(), 'Rolle zählt als Teilnahme.');
        $this->assertSame(0, ClubLineupEntry::query()->where('event_id', $event->id)->count(), 'Rolle ist kein Kaderplatz.');
        $this->matchService()->assignRole($event, ClubEventRoleKind::Referee, ['name' => 'SR Verband'], $this->admin);

        $this->actingAs($this->admin)->get(route('club.profiles.index'))->assertOk()->assertSee('Fußball');
        $this->actingAs($this->admin)->get(route('club.profiles.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('club.profiles.store'), ['name' => 'Handball', 'family' => 'team_ball', 'result_format' => 'goals', 'squad_size_field' => 7, 'positions' => "tw=Torwart\nkreis=Kreisläufer"])->assertRedirect(route('club.profiles.index'));
        $this->assertSame(['tw', 'kreis'], ClubSportProfile::query()->where('name', 'Handball')->firstOrFail()->positionCodes());
        $this->actingAs($this->admin)->get(route('club.groups.show', [$team, 'season' => $this->season->sqid]))->assertOk()->assertSee(__('club.teams.card.squad'))->assertSee($player->fullName());
        $this->actingAs($this->admin)->get(route('club.groups.squad.create', [$team, 'season' => $this->season->sqid]))->assertOk();
        $this->actingAs($this->admin)->get(route('club.matches.index'))->assertOk()->assertSee('FC Beispiel');
        $this->actingAs($this->admin)->get(route('club.matches.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('club.matches.show', $event))->assertOk()->assertSee(__('club.matches.card.lineup'))->assertSee('SR Verband');
        $this->actingAs($this->admin)->get(route('club.matches.edit', $event))->assertOk();
        $this->actingAs($this->admin)->get(route('club.matches.result.edit', $event))->assertOk();
        $this->actingAs($this->admin)->get(route('club.matches.roles.create', $event))->assertOk();
        $this->actingAs($this->admin)->get(route('club.matches.proposals.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('club.matches.proposals.import.create'))->assertOk();

        // Leitung: eigene Mannschaft ja, fremde nein.
        $this->actingAs($lead)->get(route('club.matches.show', $event))->assertOk();
        $this->actingAs($lead)->post(route('club.matches.lineup', $event), ['lineup_member' => [$player->sqid], 'lineup_slot' => ['field'], 'lineup_jersey' => [7], 'release' => 1])->assertRedirect(route('club.matches.show', $event));
        $this->assertSame(ClubLineupStatus::Released, $this->matchService()->detailsOf($event)->lineup_status);
        $this->actingAs($lead)->post(route('club.matches.result', $event), ['home' => 2, 'away' => 2])->assertRedirect();
        $this->assertSame('2:2', $this->matchService()->detailsOf($event)->result_summary);
        $this->actingAs($lead)->get(route('club.matches.show', $foreign))->assertForbidden();
        $this->actingAs($lead)->post(route('club.matches.lineup', $foreign), ['lineup_member' => [$player->sqid], 'lineup_slot' => ['field']])->assertForbidden();
        $this->actingAs($lead)->get(route('club.profiles.create'))->assertForbidden();
        $this->actingAs($this->orgUser())->get(route('club.matches.index'))->assertForbidden();

        // Portal: Zusage durch das Mitglied selbst.
        $login = $this->orgUser();
        $player->update(['user_id' => $login->id]);
        $this->actingAs($login)->get(route('club.my.index'))->assertOk()->assertSee(__('club.matches.card.my'));
        $this->actingAs($login)->post(route('club.my.availability', $event), ['status' => 'unavailable'])->assertRedirect(route('club.my.index'));
        $this->assertSame(ClubAvailabilityStatus::Unavailable, $this->matchService()->candidatesFor($event)->firstWhere('member.id', $player->id)['availability']?->status);
        $this->actingAs($login)->post(route('club.my.availability', $foreign), ['status' => 'available'])->assertForbidden();
        $this->assertSame(ClubLineupStatus::Released, $this->matchService()->detailsOf($event)->lineup_status, 'Absage ändert die Nominierung nicht.');
    }
}
