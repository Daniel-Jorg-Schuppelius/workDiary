<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Enums\Search\SearchSourceType;
use App\Enums\ServiceTicket\TicketMessageKind;
use App\Enums\User\Permission;
use App\Models\{Comment, Customer, DiaryEntry, ForeignCustomer, Organization, Project, SearchDocument, SearchSynonymGroup, SearchTerm, ServiceTicket, ServiceTicketMessage, TimeEntry, Timesheet, User};
use App\Services\Search\{ActivitySearchCriteria, ActivitySearchResult, ActivitySearchService};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Tätigkeitsrecherche (Feature 153): der Anlass-Anruf („wir haben irgendwas
 * am SMTP und Exchange gemacht — was, wann, bei welchem Kunden?"), die
 * Suchregeln, der Kontext-Nachzug, die Rechte je Quelle und die Heilung des
 * Index. Die Testsuite nutzt die LIKE-Engine mit identischer Semantik.
 *
 * Die Suche wird hier direkt am Service aufgerufen — ohne die Middleware
 * `SetOrganizationContext`. Deren Spatie-Team-Kontext setzt deshalb der Helfer
 * {@see search()} vor jedem Aufruf: Factories, die nebenbei eine Organisation
 * anlegen, verstellen ihn sonst, und org-gebundene Rollen wie „Buchhaltung“
 * lösen nicht auf.
 */
final class ActivitySearchTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        config(['search.indexing' => true]);
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = $this->orgAdmin();
    }

    public function test_the_phone_call_scenario_answers_what_when_and_for_whom(): void {
        $s = $this->partnerScenario();
        TimeEntry::factory()->create([
            'project_id' => $s['project']->id,
            'user_id' => $this->admin->id,
            'date' => '2026-02-12',
            'minutes' => 30,
            'description' => 'Druckertreiber aktualisiert',
        ]);

        $result = $this->search($this->admin, 'smtp exchange');

        $this->assertSame(1, $result->hits->total());
        $hit = $result->hits->first();
        $this->assertSame(SearchSourceType::TimeEntry, $hit->type);
        $this->assertSame('Partnerhaus IT GmbH › Metallbau Sonnenschein', $hit->customerLabel());
        $this->assertSame('Exchange-Migration', $hit->projectName);
        $this->assertSame(90, $hit->minutes);
        $this->assertSame('03.03.2026', $hit->dateLabel());
        // Der Projekt-Reiter folgt dem Header-Zeitraum — der Link springt über search.open.
        $this->assertStringContainsString('/suche/treffer/time_entry/', (string) $hit->url);
        $this->assertContains(['SMTP', true], $hit->snippet);

        $this->assertCount(1, $result->aggregates);
        $this->assertSame('Partnerhaus IT GmbH › Metallbau Sonnenschein', $result->aggregates[0]->label());
        $this->assertSame(['time_entry' => 1], $result->typeCounts);

        // So, wie der Partner am Telefon fragt: Füllwörter fallen weg.
        $question = $this->search($this->admin, 'wann haben wir irgendwas am smtp und exchange gemacht');
        $this->assertSame(1, $question->hits->total());
        $this->assertContains('wann', $question->parsed->ignored);
    }

    public function test_word_starts_umlauts_phrases_and_exclusions(): void {
        $this->partnerScenario();
        DiaryEntry::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Postfächer migriert',
            'content' => 'Alle Postfächer nach Exchange Online verschoben',
        ]);

        $this->assertSame(1, $this->search($this->admin, 'postfaech')->hits->total());
        $this->assertSame(1, $this->search($this->admin, 'POSTFÄCHER')->hits->total());
        $this->assertSame(1, $this->search($this->admin, '"smtp relay"')->hits->total());
        $this->assertSame(1, $this->search($this->admin, 'smtprelay')->hits->total());
        $this->assertSame(0, $this->search($this->admin, '"relay smtp"')->hits->total());
        $this->assertSame(1, $this->search($this->admin, 'exchange -sendeconnector')->hits->total());
        $this->assertSame(0, $this->search($this->admin, 'relay -srv')->hits->total());
        // Mitten im Wort wird nicht gesucht.
        $this->assertSame(0, $this->search($this->admin, 'change')->hits->total());
    }

    public function test_unknown_words_are_matched_by_similar_spelling(): void {
        $s = $this->partnerScenario();

        $result = $this->search($this->admin, 'exchnage smpt');
        $this->assertSame(1, $result->hits->total());
        $this->assertContains('exchange', $result->parsed->corrections['exchnage']);
        $this->assertContains('smtp', $result->parsed->corrections['smpt']);

        // Tippfehler in den Notizen: ein bekanntes Suchwort findet sie nur mit dem Schalter.
        TimeEntry::factory()->create(['project_id' => $s['project']->id, 'user_id' => $this->admin->id, 'date' => '2026-03-04', 'minutes' => 20, 'description' => 'Zertifikat erneuert']);
        TimeEntry::factory()->create(['project_id' => $s['project']->id, 'user_id' => $this->admin->id, 'date' => '2026-03-05', 'minutes' => 20, 'description' => 'Zertifkat getauscht']);

        $this->assertSame(1, $this->search($this->admin, 'zertifikat')->hits->total());
        $this->assertSame(2, $this->search($this->admin, 'zertifikat', ['similar' => true])->hits->total());
    }

    public function test_synonym_groups_including_multiword_terms(): void {
        $this->partnerScenario();
        SearchSynonymGroup::query()->create(['terms' => ['Mailrelay', 'SMTP'], 'active' => true]);
        SearchSynonymGroup::query()->create(['terms' => ['send connector', 'Sendeconnector'], 'active' => true]);
        SearchSynonymGroup::query()->create(['terms' => ['Postfix', 'SMTP'], 'active' => false]);

        $result = $this->search($this->admin, 'mailrelay exchange');
        $this->assertSame(1, $result->hits->total());
        $this->assertSame(['smtp'], $result->parsed->synonyms['mailrelay']);

        $this->assertSame(1, $this->search($this->admin, 'send connector')->hits->total());
        $this->assertSame(0, $this->search($this->admin, 'postfix')->hits->total(), 'Deaktivierte Gruppen wirken nicht.');
    }

    /** UI-Fuzz 2026-09-21: ein Zahlbegriff in einer Synonymgruppe legte jede Suche der Organisation lahm (TypeError). */
    public function test_numeric_synonym_terms_do_not_break_the_search(): void {
        $this->partnerScenario();
        SearchSynonymGroup::query()->create(['terms' => ['4711', 'Mailrelay'], 'active' => true]);

        $result = $this->search($this->admin, 'mailrelay');

        $this->assertSame(['4711'], $result->parsed->synonyms['mailrelay']);
    }

    /** UI-Fuzz 2026-09-21: ein Jahr 1239 im Tagebuch brach die Neuindizierung ab — das Projekt ließ sich nicht mehr speichern. */
    public function test_implausible_source_dates_do_not_block_reindexing(): void {
        $s = $this->partnerScenario();
        $diary = DiaryEntry::factory()->create([
            'project_id' => $s['project']->id,
            'user_id' => $this->admin->id,
            'start_at' => '1239-07-09 00:00:00',
            'end_at' => '1239-07-09 01:00:00',
        ]);

        $s['project']->update(['name' => 'Exchange-Migration 2']);

        $document = SearchDocument::query()->where('source_type', SearchSourceType::DiaryEntry->value)->where('source_id', $diary->id)->firstOrFail();
        $this->assertNull($document->occurred_at);
    }

    public function test_renaming_the_context_reindexes_the_documents(): void {
        $s = $this->partnerScenario();

        $s['project']->update(['name' => 'Mailumstellung Nord']);
        $this->assertSame(1, $this->search($this->admin, 'mailumstellung smtp')->hits->total());
        $this->assertSame(0, $this->search($this->admin, 'migration smtp')->hits->total());

        $s['endCustomer']->update(['name' => 'Bergwerk Sonnenhang']);
        $this->assertSame(1, $this->search($this->admin, 'bergwerk smtp')->hits->total());
    }

    /**
     * `projects.keywords` ist ein Array-Cast (Schlüsselwort-Routing). Die
     * erste Fassung schob das Array als Ganzes in die Textliste, der Indexer
     * verwarf es still — Schlüsselwörter waren nie durchsuchbar (PHPStan-Fund).
     */
    public function test_project_keywords_are_part_of_the_context(): void {
        $partner = Customer::factory()->create(['name' => 'Partnerhaus IT GmbH']);
        $project = Project::factory()->create([
            'customer_id' => $partner->id,
            'name' => 'Wartungsvertrag',
            'keywords' => ['Postfachmigration', 'Mailbox'],
        ]);
        TimeEntry::factory()->create(['project_id' => $project->id, 'user_id' => $this->admin->id, 'date' => '2026-03-07', 'minutes' => 30, 'description' => 'Umstellung abgeschlossen']);

        $this->assertSame(1, $this->search($this->admin, 'postfachmigration umstellung')->hits->total());
        $this->assertSame(1, $this->search($this->admin, 'mailbox')->hits->total());
    }

    public function test_deleting_a_source_removes_its_document(): void {
        $s = $this->partnerScenario();
        $this->assertSame(1, SearchDocument::query()->count());

        $s['entry']->delete();

        $this->assertSame(0, SearchDocument::query()->count());
    }

    public function test_filters_by_customer_end_customer_project_and_period(): void {
        $s = $this->partnerScenario();
        $direct = Customer::factory()->create(['name' => 'Direktkunde AG']);
        $directProject = Project::factory()->create(['customer_id' => $direct->id, 'name' => 'Serverbetreuung']);
        TimeEntry::factory()->create(['project_id' => $directProject->id, 'user_id' => $this->admin->id, 'date' => '2026-03-10', 'minutes' => 60, 'description' => 'SMTP Auth für Scanner eingerichtet']);
        $child = Project::factory()->create([
            'customer_id' => $s['partner']->id,
            'foreign_customer_id' => $s['endCustomer']->id,
            'parent_id' => $s['project']->id,
            'name' => 'Teilprojekt Logs',
        ]);
        TimeEntry::factory()->create(['project_id' => $child->id, 'user_id' => $this->admin->id, 'date' => '2026-03-05', 'minutes' => 15, 'description' => 'SMTP Logs geprüft']);

        $this->assertSame(3, $this->search($this->admin, 'smtp')->hits->total());
        $this->assertSame(2, $this->search($this->admin, 'smtp', ['customerId' => $s['partner']->id])->hits->total());
        $this->assertSame(2, $this->search($this->admin, 'smtp', ['foreignCustomerId' => $s['endCustomer']->id])->hits->total());
        $this->assertSame(2, $this->search($this->admin, 'smtp', ['projectId' => $s['project']->id])->hits->total());
        $this->assertSame(1, $this->search($this->admin, 'smtp', ['customerId' => $direct->id])->hits->total());
        $this->assertSame(1, $this->search($this->admin, 'smtp', ['from' => '2026-03-04', 'to' => '2026-03-06'])->hits->total());
        $this->assertSame(0, $this->search($this->admin, 'smtp', ['types' => [SearchSourceType::DiaryEntry]])->hits->total());

        // Ohne Suchwort, aber mit Bezug: die neuesten Tätigkeiten zuerst.
        $latest = $this->search($this->admin, '', ['customerId' => $s['partner']->id]);
        $this->assertTrue($latest->searched);
        $this->assertSame('SMTP Logs geprüft', $latest->hits->first()->excerpt);

        // Ohne Suchwort und ohne Bezug gibt es keine Liste.
        $this->assertFalse($this->search($this->admin, '')->searched);
    }

    public function test_time_entries_of_others_follow_the_time_entry_policy(): void {
        $this->partnerScenario();
        $colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $viewer = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($viewer, [Permission::TimeEntryViewAny]);

        $this->assertSame(0, $this->search($colleague, 'smtp')->hits->total());
        $this->assertSame(1, $this->search($accounting, 'smtp')->hits->total());
        $this->assertSame(1, $this->search($viewer, 'smtp')->hits->total());

        $project = Project::query()->firstOrFail();
        TimeEntry::factory()->create(['project_id' => $project->id, 'user_id' => $colleague->id, 'date' => '2026-03-04', 'minutes' => 10, 'description' => 'SMTP Testmail gesendet']);
        $this->assertSame(1, $this->search($colleague, 'smtp')->hits->total(), 'Eigene Zeiten sind immer sichtbar.');
    }

    public function test_tickets_index_messages_and_hide_restricted_ones(): void {
        $agent = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($agent, [Permission::ServiceTicketView]);
        $outsider = User::factory()->create(['organization_id' => $this->organization->id]);

        $open = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Relay stört Mailfluss']);
        $restricted = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Relay Vorfall Geschäftsführung', 'confidentiality' => 'restricted']);
        ServiceTicketMessage::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $open->id,
            'kind' => TicketMessageKind::PublicReply,
            'subject' => 'Re: Relay',
            'body' => '<p>Mailfluss über <b>Postfix</b> geprüft</p>',
            'channel' => 'mail',
        ]);

        $this->assertSame(1, $this->search($agent, 'relay')->hits->total());
        $this->assertSame(1, $this->search($agent, 'postfix')->hits->total());
        $this->assertSame(0, $this->search($outsider, 'relay')->hits->total(), 'Ohne serviceTicket.view keine Tickets.');
        $this->assertSame(2, $this->search($this->admin, 'relay')->hits->total());

        $restricted->update(['assigned_to_user_id' => $agent->id]);
        $this->assertSame(2, $this->search($agent, 'relay')->hits->total(), 'Bearbeitung sieht das vertrauliche Ticket.');
    }

    public function test_comments_and_timesheet_notes_are_searchable(): void {
        $diary = DiaryEntry::factory()->create(['user_id' => $this->admin->id, 'title' => 'Serverraum']);
        Comment::query()->create([
            'organization_id' => $this->organization->id,
            'commentable_type' => MorphMap::alias(DiaryEntry::class),
            'commentable_id' => $diary->id,
            'user_id' => $this->admin->id,
            'body' => 'USV Batterie getauscht',
        ]);
        $this->assertSame(1, $this->search($this->admin, 'usv batterie')->hits->total());

        $s = $this->partnerScenario();
        $sheet = Timesheet::query()->create([
            'project_id' => $s['project']->id,
            'user_id' => $this->admin->id,
            'work_date' => '2026-03-03',
            'status' => 'draft',
            'notes' => 'Kunde hat die Umstellung vor Ort bestätigt',
        ]);
        $this->assertSame(1, $this->search($this->admin, 'bestaetigt umstellung', ['types' => [SearchSourceType::Timesheet]])->hits->total());

        $sheet->update(['notes' => null]);
        $this->assertFalse(SearchDocument::query()->where('source_type', SearchSourceType::Timesheet->value)->exists());
    }

    public function test_reconcile_and_rebuild_heal_the_index(): void {
        config(['search.indexing' => false]);
        $s = $this->partnerScenario();
        $this->assertSame(0, SearchDocument::query()->count());

        $this->artisan('search:reconcile')->assertSuccessful();
        $this->assertSame(1, $this->search($this->admin, 'smtp')->hits->total());

        // Massenänderung am Observer vorbei (Eloquent-Builder setzt updated_at).
        TimeEntry::query()->whereKey($s['entry']->id)->update(['description' => 'Firewall Regel angepasst', 'updated_at' => now()->addMinute()]);
        $this->artisan('search:reconcile')->assertSuccessful();
        $this->assertSame(0, $this->search($this->admin, 'smtp')->hits->total());
        $this->assertSame(1, $this->search($this->admin, 'firewall')->hits->total());

        DB::table('time_entries')->where('id', $s['entry']->id)->delete();
        $this->artisan('search:reconcile')->assertSuccessful();
        $this->assertSame(0, SearchDocument::query()->count());

        TimeEntry::factory()->create(['project_id' => $s['project']->id, 'user_id' => $this->admin->id, 'date' => '2026-03-06', 'minutes' => 45, 'description' => 'Datensicherung geprüft']);
        $this->artisan('search:rebuild', ['--organization' => (string) $this->organization->id])->assertSuccessful();
        $this->assertSame(1, $this->search($this->admin, 'datensicherung')->hits->total());
        $this->assertTrue(SearchTerm::query()->where('term', 'datensicherung')->exists());
    }

    public function test_documents_of_other_organizations_stay_invisible(): void {
        $this->partnerScenario();
        $other = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $other->id]);
        app()->instance('currentOrganization', $other);

        $this->assertSame(0, $this->search($foreignAdmin, 'smtp')->hits->total());
    }

    /**
     * Wie `SetOrganizationContext` je Request: Spatie-Team-Kontext aus der
     * Organisation des Suchenden, geladene Rollen/Rechte verwerfen.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function search(User $user, string $query, array $criteria = []): ActivitySearchResult {
        $this->actAsTeam($user->organization_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return app(ActivitySearchService::class)->search($user, new ActivitySearchCriteria(...array_merge(['query' => $query], $criteria)));
    }

    /**
     * Der Anlass: Partner → Endkunde → Projekt → Fernwartungs-Zeiteintrag.
     *
     * @return array{partner: Customer, endCustomer: ForeignCustomer, project: Project, entry: TimeEntry}
     */
    private function partnerScenario(): array {
        $partner = Customer::factory()->create(['name' => 'Partnerhaus IT GmbH']);
        $endCustomer = ForeignCustomer::factory()->create([
            'customer_id' => $partner->id,
            'name' => 'Metallbau Sonnenschein',
            'company' => 'Metallbau Sonnenschein',
            'created_by' => $this->admin->id,
        ]);
        $project = Project::factory()->create([
            'customer_id' => $partner->id,
            'foreign_customer_id' => $endCustomer->id,
            'name' => 'Exchange-Migration',
        ]);
        $entry = TimeEntry::factory()->create([
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => '2026-03-03',
            'minutes' => 90,
            'description' => 'SRV-EX01 (Sendeconnector auf SMTP-Relay umgestellt)',
        ]);

        return ['partner' => $partner, 'endCustomer' => $endCustomer, 'project' => $project, 'entry' => $entry];
    }
}
