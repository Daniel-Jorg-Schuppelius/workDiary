<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MorphMapProbeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Models\{Attachment, AuditLog, Customer, Organization, User};
use App\Support\{EntityUrl, MorphMap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sonde (MVP-860): Bestandszeilen mit altem Klassennamen lösen weiter auf,
 * neue Zeilen tragen den Alias, und die Kettentabelle `audit_logs` findet
 * alte wie neue Zeilen über den stabilen Schlüssel.
 */
class MorphMapProbeTest extends TestCase {
    use RefreshDatabase;

    public function test_legacy_class_name_rows_still_resolve(): void {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->for($org)->create();

        DB::table('audit_logs')->insert([
            'organization_id' => $org->id,
            'event' => 'created',
            'auditable_type' => 'App\\Models\\Customer',
            'auditable_id' => $customer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $log = AuditLog::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertTrue($log->auditable->is($customer));
        $this->assertSame('Customer', MorphMap::basename($log->auditable_type));
    }

    public function test_new_rows_use_the_alias_and_audit_rows_the_stable_key(): void {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $customer = Customer::factory()->for($org)->create();

        $attachment = Attachment::factory()->create([
            'organization_id' => $org->id,
            'attachable_type' => $customer->getMorphClass(),
            'attachable_id' => $customer->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame('customers', $attachment->fresh()?->attachable_type);
        $this->assertTrue($attachment->attachable->is($customer));

        $this->actingAs($user);
        $customer->audit('touched');
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'App\\Models\\Customer',
            'auditable_id' => $customer->id,
            'event' => 'touched',
        ]);
        $this->assertSame(1, $customer->auditLogs()->where('event', 'touched')->count());
    }

    public function test_entity_url_understands_alias_and_legacy_name(): void {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->for($org)->create();

        $byAlias = EntityUrl::byType('customers', $customer->id);
        $byLegacy = EntityUrl::byType('App\\Models\\Customer', $customer->id);

        $this->assertNotNull($byAlias);
        $this->assertSame($byAlias, $byLegacy);
    }
}
