<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskKnowledgeRecurrenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\{KnowledgeArticle, KnowledgeArticleLink, Organization, Problem, ServiceQueue, ServiceTicket, User};
use App\Services\ServiceTicket\HelpdeskMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * „Probleme trotz Artikel" (Feature 011, MVP-338/Bauturbo A20):
 * Hand-Fixture-Nachrechnung (nur Incidents NACH Artikel-Publikation
 * zählen, Trend aus Zeitraum-Hälften, Handlungsliste absteigend),
 * Berichts-Sektion, signierter Drilldown (403 ohne Signatur/Recht,
 * Org-Isolation), CSV-Export der neuen Kennzahl.
 */
final class HelpdeskKnowledgeRecurrenceTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    /**
     * Artikel (published) ↔ Problem ↔ Incidents; Tickets werden über das
     * problem_ticket-Pivot angehängt.
     *
     * @param list<Carbon> $reportedAts
     * @return array{0: KnowledgeArticle, 1: Problem, 2: list<ServiceTicket>}
     */
    private function makeChain(Carbon $publishedAt, array $reportedAts, ?int $organizationId = null, string $ticketPrefix = 'ST-KNOW'): array {
        $orgId = $organizationId ?? $this->org->id;

        $article = KnowledgeArticle::factory()->published()->create([
            'organization_id' => $orgId,
            'title' => 'Known Error: Drucker offline ' . $ticketPrefix,
            'published_at' => $publishedAt,
            'created_by_user_id' => $this->agent->id,
        ]);
        $problem = Problem::query()->create([
            'organization_id' => $orgId,
            'title' => 'Druckerspooler hängt ' . $ticketPrefix,
            'owner_id' => $this->agent->id,
        ]);
        KnowledgeArticleLink::query()->create([
            'knowledge_article_id' => $article->id,
            'linkable_type' => $problem->getMorphClass(),
            'linkable_id' => $problem->id,
            'created_by_user_id' => $this->agent->id,
        ]);

        $tickets = [];
        foreach ($reportedAts as $i => $reportedAt) {
            $ticket = ServiceTicket::factory()->create([
                'organization_id' => $orgId,
                'ticket_no' => $ticketPrefix . '-' . ($i + 1),
                'reported_at' => $reportedAt,
            ]);
            $problem->tickets()->attach($ticket->id);
            $tickets[] = $ticket;
        }

        return [$article, $problem, $tickets];
    }

    public function test_recurrence_metric_reproduces_hand_fixture(): void {
        // Publikation 01.07. 10:00; Zeitraum Juli → Mitte ≈ 16.07. 12:00.
        // Vor Publikation (30.06.) zählt NICHT; 05.07. (1. Hälfte) +
        // 20.07./28.07. (2. Hälfte) zählen → count 3, Trend steigend.
        [$article] = $this->makeChain(Carbon::parse('2026-07-01 10:00:00'), [
            Carbon::parse('2026-06-30 09:00:00'),
            Carbon::parse('2026-07-05 09:00:00'),
            Carbon::parse('2026-07-20 09:00:00'),
            Carbon::parse('2026-07-28 09:00:00'),
        ]);

        // Problem MIT Incidents, aber OHNE Artikel → erscheint nicht.
        $orphanProblem = Problem::query()->create([
            'organization_id' => $this->org->id,
            'title' => 'Problem ohne Artikel',
            'owner_id' => $this->agent->id,
        ]);
        $orphanTicket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'reported_at' => Carbon::parse('2026-07-10 09:00:00'),
        ]);
        $orphanProblem->tickets()->attach($orphanTicket->id);

        // Publizierter Artikel, dessen Incidents alle VOR der Publikation
        // liegen → kein Handlungsbedarf, erscheint nicht.
        $this->makeChain(Carbon::parse('2026-07-30 10:00:00'), [
            Carbon::parse('2026-07-02 09:00:00'),
        ], ticketPrefix: 'ST-OLD');

        // Entwurf (nicht publiziert) zählt nie — publishKnownError legt
        // Artikel zunächst als Draft an.
        $draft = KnowledgeArticle::factory()->create([
            'organization_id' => $this->org->id,
            'title' => 'Entwurf ohne Publikation',
            'created_by_user_id' => $this->agent->id,
        ]);
        KnowledgeArticleLink::query()->create([
            'knowledge_article_id' => $draft->id,
            'linkable_type' => $orphanProblem->getMorphClass(),
            'linkable_id' => $orphanProblem->id,
            'created_by_user_id' => $this->agent->id,
        ]);

        $rows = app(HelpdeskMetricsService::class)->recurringDespiteArticle(
            Carbon::parse('2026-07-01 00:00:00'),
            Carbon::parse('2026-07-31 23:59:59'),
        );

        $this->assertCount(1, $rows, 'Nur der publizierte Artikel mit neuen Incidents erscheint.');
        $this->assertSame($article->id, $rows[0]['article']->id);
        $this->assertSame(3, $rows[0]['count'], 'Incident vor Publikation zählt nicht.');
        $this->assertSame(1, $rows[0]['first_half']);
        $this->assertSame(2, $rows[0]['second_half']);
        $this->assertSame('rising', $rows[0]['trend']);
        $this->assertTrue($rows[0]['last_at']->eq(Carbon::parse('2026-07-28 09:00:00')));
        $this->assertSame(['Druckerspooler hängt ST-KNOW'], $rows[0]['problems']);
    }

    public function test_action_list_sorts_most_occurrences_first_and_detects_falling_trend(): void {
        // Artikel A: 1 neuer Incident (2. Hälfte); Artikel B: 2 neue
        // Incidents (beide 1. Hälfte → Trend fallend). B steht zuerst.
        [$a] = $this->makeChain(Carbon::parse('2026-07-01 08:00:00'), [
            Carbon::parse('2026-07-25 09:00:00'),
        ], ticketPrefix: 'ST-A');
        [$b] = $this->makeChain(Carbon::parse('2026-07-01 08:00:00'), [
            Carbon::parse('2026-07-03 09:00:00'),
            Carbon::parse('2026-07-06 09:00:00'),
        ], ticketPrefix: 'ST-B');

        $rows = app(HelpdeskMetricsService::class)->recurringDespiteArticle(
            Carbon::parse('2026-07-01 00:00:00'),
            Carbon::parse('2026-07-31 23:59:59'),
        );

        $this->assertSame([$b->id, $a->id], array_map(fn(array $row): int => $row['article']->id, $rows));
        $this->assertSame('falling', $rows[0]['trend']);
        $this->assertSame('rising', $rows[1]['trend']);
    }

    public function test_report_page_renders_recurrence_section(): void {
        ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Support', 'is_default' => true]);
        [$article] = $this->makeChain(now()->subWeeks(3), [now()->subWeek()]);

        $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.index'))
            ->assertOk()
            ->assertSee(__('Probleme trotz Wissensartikel'))
            ->assertSee($article->title)
            ->assertSee(route('knowledge.show', $article), false);
    }

    public function test_drilldown_requires_valid_signature_and_report_permission(): void {
        [$article] = $this->makeChain(now()->subWeeks(3), [now()->subWeek()]);

        // Ohne Signatur → 403, auch mit Berichts-Recht.
        $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.drilldown', ['kind' => 'knowledge_recurrence', 'key' => $article->sqid, 'expected' => 1]))
            ->assertForbidden();

        // Gültige Signatur, aber ohne sla.viewAny → 403 (Bestandsrecht
        // des Helpdesk-Berichts).
        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'knowledge_recurrence', 'key' => $article->sqid, 'expected' => 1,
            'from' => now()->subWeeks(8)->toDateString(), 'to' => now()->toDateString(),
        ]);
        $plain = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($plain)->get($url)->assertForbidden();
    }

    public function test_drilldown_lists_only_post_publication_incidents_and_is_org_scoped(): void {
        [$article] = $this->makeChain(now()->subWeeks(3), [
            now()->subWeeks(5), // vor Publikation → erscheint nicht
            now()->subWeek(),   // nach Publikation → erscheint
        ]);

        // Fremd-Org-Kette: kompletter Aufbau, darf nie sichtbar sein.
        $foreignOrg = Organization::factory()->create();
        [$foreignArticle] = $this->makeChain(now()->subWeeks(3), [now()->subWeek()], $foreignOrg->id, 'ST-FREMD');

        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'knowledge_recurrence', 'key' => $article->sqid, 'expected' => 1,
            'from' => now()->subWeeks(8)->toDateString(), 'to' => now()->toDateString(),
        ]);
        $this->actingAs($this->agent)->get($url)
            ->assertOk()
            ->assertSee('ST-KNOW-2')
            ->assertDontSee('ST-KNOW-1')
            ->assertDontSee('ST-FREMD')
            ->assertDontSee(__('Konsistenz-Hinweis'));

        // Fremder Artikel-Sqid → 404 (Org-Scope hart über findOrFail).
        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'knowledge_recurrence', 'key' => $foreignArticle->sqid, 'expected' => 1,
            'from' => now()->subWeeks(8)->toDateString(), 'to' => now()->toDateString(),
        ]);
        $this->actingAs($this->agent)->get($url)->assertNotFound();
    }

    public function test_csv_export_contains_recurrence_rows(): void {
        [$article] = $this->makeChain(now()->subWeeks(3), [now()->subWeek()]);

        $response = $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.csv', [
                'metric' => 'knowledge',
                'from' => now()->subWeeks(8)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('incidents_after_publication', $content);
        $this->assertStringContainsString($article->title, $content);
    }
}
