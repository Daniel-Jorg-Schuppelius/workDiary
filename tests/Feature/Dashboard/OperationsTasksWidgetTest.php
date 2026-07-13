<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTasksWidgetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Dashboard;

use App\Dashboard\WidgetRegistry;
use App\Dashboard\Widgets\OperationsTasksWidget;
use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\User;
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard-Kachel „Offene Betriebsaufgaben" (B3/MVP-344): registriert,
 * nur für `platform.operations.view`-Berechtigte sichtbar, zeigt den
 * korrekten Zähler und die Top-3 nach Dringlichkeit.
 */
class OperationsTasksWidgetTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function makeTask(string $dedupeKey, OperationsTaskSeverity $severity = OperationsTaskSeverity::Warning): void {
        app(OperationsAlertService::class)->report(new OperationsSignal(
            type: OperationsTaskType::BackupOverdue,
            dedupeKey: $dedupeKey,
            severity: $severity,
            titleKey: 'operations.task.backup_overdue',
            params: ['hours' => 30, 'threshold' => 26],
            organizationId: (int) $this->admin->organization_id,
        ));
    }

    public function test_widget_is_registered(): void {
        $this->assertInstanceOf(
            OperationsTasksWidget::class,
            app(WidgetRegistry::class)->find('operations-tasks'),
        );
    }

    public function test_widget_is_available_only_with_permission(): void {
        $widget = app(OperationsTasksWidget::class);
        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);

        $this->assertTrue($widget->availableFor($this->admin));
        $this->assertFalse($widget->availableFor($user));
    }

    public function test_widget_renders_count_and_top_tasks(): void {
        $this->makeTask('a');
        $this->makeTask('b');
        $this->makeTask('c', OperationsTaskSeverity::Critical);
        $this->makeTask('d');

        $result = app(OperationsTasksWidget::class)->render($this->admin);
        $html = $result instanceof View ? $result->render() : (string) $result;

        $this->assertStringContainsString(e(__('operations.title.widget')), $html);
        $this->assertStringContainsString('>4<', $html); // Zähler-Badge
        $this->assertStringContainsString(e(__('operations.task.backup_overdue', ['hours' => 30, 'threshold' => 26])), $html);
        $this->assertStringContainsString(e(OperationsTaskSeverity::Critical->label()), $html); // Critical in Top-3
        $this->assertStringContainsString(route('admin.operations.index'), $html);
    }

    public function test_widget_renders_empty_hint_without_tasks(): void {
        $result = app(OperationsTasksWidget::class)->render($this->admin);
        $html = $result instanceof View ? $result->render() : (string) $result;

        $this->assertStringContainsString(e(__('operations.widget.empty')), $html);
        $this->assertStringContainsString('>0<', $html);
    }

    public function test_dashboard_shows_widget_only_for_authorized_users(): void {
        $this->makeTask('a');

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('operations.title.widget'));

        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('operations.title.widget'));
    }
}
