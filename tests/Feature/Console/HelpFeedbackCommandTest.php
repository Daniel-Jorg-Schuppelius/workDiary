<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpFeedbackCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\HelpFeedbackCommand;
use App\Models\Platform\{HelpView, Organization};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MVP-1623 (gl-135): {@see HelpFeedbackCommand} (`help:feedback`) wertet
 * `help_views.was_helpful` aus — plattformweit, ohne Aufrufe, wenig
 * hilfreiche Themen zuerst, nur im gewählten Zeitraum. Aufrufe zählen in den
 * „Beliebten Themen“ ohne die Rückmeldungen.
 */
final class HelpFeedbackCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_lists_feedback_per_topic_with_unhelpful_topics_first(): void {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $this->feedback($first, 'invoices.manage', true, 2);
        $this->feedback($second, 'invoices.manage', false, 1);
        $this->feedback($first, 'finance.reconciliation', false, 3);
        $this->feedback($first, 'finance.reconciliation', null, 5);
        $this->feedback($first, 'quotes.manage', false, 1, now()->subDays(120));

        $this->artisan('help:feedback')
            ->expectsTable(['Thema', 'hilfreich', 'nicht hilfreich'], [
                ['finance.reconciliation', 0, 3],
                ['invoices.manage', 2, 1],
            ])
            ->assertExitCode(0);

        $this->artisan('help:feedback', ['--days' => 365, '--limit' => 1])
            ->expectsTable(['Thema', 'hilfreich', 'nicht hilfreich'], [['finance.reconciliation', 0, 3]])
            ->assertExitCode(0);
    }

    public function test_reports_when_there_is_no_feedback(): void {
        $this->feedback(Organization::factory()->create(), 'invoices.manage', null, 4);

        $this->artisan('help:feedback', ['--days' => 30])
            ->expectsOutput('Keine Rückmeldungen in den letzten 30 Tagen.')
            ->assertExitCode(0);
    }

    private function feedback(Organization $organization, string $topic, ?bool $helpful, int $count, ?\DateTimeInterface $at = null): void {
        for ($i = 0; $i < $count; $i++) {
            HelpView::query()->create([
                'organization_id' => $organization->id,
                'topic' => $topic,
                'locale' => 'de',
                'was_helpful' => $helpful,
                'created_at' => $at ?? now()->subDays(2),
            ]);
        }
    }
}
