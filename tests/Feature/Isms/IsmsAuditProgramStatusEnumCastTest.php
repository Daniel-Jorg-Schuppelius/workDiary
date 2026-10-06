<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditProgramStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Isms;

use App\Enums\Isms\IsmsAuditProgramStatus;
use App\Models\Isms\IsmsAuditProgram;
use App\Models\Platform\User;
use App\Services\Isms\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): das Auditprogramm führt
 * seinen Stand als Enum. Gegen die frühere Zeichenkette verglichen, erschiene
 * jedes Programm als abgebrochen.
 */
final class IsmsAuditProgramStatusEnumCastTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private IsmsAuditProgram $program;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $this->admin->organization);

        $this->actingAs($this->admin)->post(route('isms.audit-programs.store'), [
            'name' => 'ISO-27001-Zyklus 2026–2028',
            'isms_scope_id' => app(ScopeService::class)->ensureDefaultScope((int) $this->admin->organization_id)->sqid,
            'cycle_years' => 3,
            'starts_on' => '2026-01-01',
        ])->assertRedirect(route('isms.audit-programs.index'));
        $this->program = IsmsAuditProgram::query()->firstOrFail();
    }

    private function assertBadge(IsmsAuditProgramStatus $status, string $tone): void {
        $html = (string) $this->actingAs($this->admin)->get(route('isms.audit-programs.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/badge-' . $tone . '"[^>]*>\s*' . preg_quote(e($status->label()), '/') . '\s*</u', $html), "Kein Abzeichen „{$status->value}“ im Ton {$tone}.");
    }

    public function test_program_starts_active_and_shows_each_status_with_its_tone(): void {
        $this->assertSame(IsmsAuditProgramStatus::Active, $this->program->status);
        $this->assertBadge(IsmsAuditProgramStatus::Active, 'success');

        foreach ([[IsmsAuditProgramStatus::Completed, 'info'], [IsmsAuditProgramStatus::Cancelled, 'neutral'], [IsmsAuditProgramStatus::Active, 'success']] as [$status, $tone]) {
            $this->actingAs($this->admin)->put(route('isms.audit-programs.update', $this->program), ['status' => $status->value])->assertSessionHasNoErrors();

            $this->assertSame($status, $this->program->fresh()->status);
            $this->assertBadge($status, $tone);
        }
    }

    /** Die Auswahl bietet genau die drei Stände, die die Prüfung annimmt. */
    public function test_status_update_accepts_exactly_the_three_states(): void {
        $this->assertSame(['active', 'completed', 'cancelled'], IsmsAuditProgramStatus::values());

        $html = (string) $this->actingAs($this->admin)->get(route('isms.audit-programs.index'))->assertOk()->getContent();
        foreach (IsmsAuditProgramStatus::cases() as $status) {
            $this->assertSame(1, preg_match('/<option value="' . $status->value . '">\s*' . preg_quote(e($status->label()), '/') . '\s*</u', $html), "Auswahl ohne {$status->value}.");
        }

        foreach (['archived', 'ACTIVE', 'draft'] as $unknown) {
            $this->actingAs($this->admin)->put(route('isms.audit-programs.update', $this->program), ['status' => $unknown])->assertSessionHasErrors('status');
        }
        $this->assertSame(IsmsAuditProgramStatus::Active, $this->program->fresh()->status);
    }
}
