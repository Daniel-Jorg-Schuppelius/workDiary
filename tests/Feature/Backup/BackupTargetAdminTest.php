<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Models\Backup\{BackupGeneration, BackupTargetConnection};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Verwaltungsseite der Backupziele (Feature 017 Phase 32, MVP-366):
 * Plattform-Gating auf allen Aktionen, Legal-Hold-Toggle, Generation
 * löschen (Hold blockiert), Master-Key-Warnung, Generationen-Tabelle.
 */
class BackupTargetAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $platformAdmin;

    private User $orgAdmin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_shows_generations_and_master_key_warning(): void {
        config(['backup_targets.master_key' => null]);
        $connection = BackupTargetConnection::factory()->active()->create();
        $generation = BackupGeneration::factory()->verified()->create(['connection_id' => $connection->id]);

        $this->actingAs($this->platformAdmin)
            ->get(route('admin.backup-targets.index'))
            ->assertOk()
            ->assertSee(__('backup_targets.master_key_missing'))
            ->assertSee($connection->name)
            ->assertSee(mb_substr($generation->snapshot_uuid, 0, 13));
    }

    public function test_org_admin_cannot_touch_generations(): void {
        $generation = BackupGeneration::factory()->verified()->create();

        $this->actingAs($this->orgAdmin)
            ->post(route('admin.backup-targets.generations.hold', $generation))
            ->assertForbidden();
        $this->actingAs($this->orgAdmin)
            ->delete(route('admin.backup-targets.generations.destroy', $generation))
            ->assertForbidden();
        $this->assertNotNull($generation->fresh());
    }

    public function test_hold_toggle_sets_and_releases(): void {
        $generation = BackupGeneration::factory()->verified()->create();

        $this->actingAs($this->platformAdmin)
            ->post(route('admin.backup-targets.generations.hold', $generation))
            ->assertRedirect(route('admin.backup-targets.index'));
        $this->assertTrue((bool) $generation->fresh()?->legal_hold);

        $this->actingAs($this->platformAdmin)
            ->post(route('admin.backup-targets.generations.hold', $generation));
        $this->assertFalse((bool) $generation->fresh()?->legal_hold);
    }

    public function test_destroy_generation_blocked_by_legal_hold(): void {
        $generation = BackupGeneration::factory()->verified()->create(['legal_hold' => true]);

        $this->actingAs($this->platformAdmin)
            ->delete(route('admin.backup-targets.generations.destroy', $generation))
            ->assertSessionHas('error');
        $this->assertNotNull($generation->fresh());
    }

    public function test_destroy_generation_without_connection_removes_evidence(): void {
        // Verwaiste Generation (Verbindung getrennt): kein Remote-Zugriff nötig.
        $generation = BackupGeneration::factory()->verified()->create(['connection_id' => null]);

        $this->actingAs($this->platformAdmin)
            ->delete(route('admin.backup-targets.generations.destroy', $generation))
            ->assertSessionHas('success');
        $this->assertNull($generation->fresh());
    }
}
