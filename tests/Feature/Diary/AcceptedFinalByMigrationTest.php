<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AcceptedFinalByMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Protocol\Protocol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1629: `accepted_final_by` wechselt von der Nutzer-ID zum Namen der
 * abnehmenden Person (Migration 2027_04_17_200000) — wie zur Laufzeit
 * (`OrderService::customerSignerName()`) die jüngste Kundenunterschrift,
 * geprüft gegen Altzeilen und auf beiden Datenbanken (CI: SQLite, `phpunit.xml.dist`).
 */
final class AcceptedFinalByMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function migration(): Migration {
        return require database_path('migrations/2027_04_17_200000_change_accepted_final_by_to_signer_name.php');
    }

    public function test_user_ids_become_the_customer_signer_and_down_restores_the_actor(): void {
        $this->setUpOrganization();
        $worker = $this->orgUser();
        $migration = $this->migration();
        $migration->down();

        $signed = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $worker->id]);
        $protocol = Protocol::factory()->signed()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $worker->id]);
        // Eingefügt in umgekehrter Reihenfolge: die ID-Folge darf das Ergebnis nicht bestimmen.
        foreach ([['customer', 'Max Kunde', '2030-03-01 10:06:00'], ['contractor', 'Erika Monteur', '2030-03-01 10:00:00'], ['customer', 'Vorabnahme Hausmeister', '2030-03-01 10:05:00']] as [$role, $name, $at]) {
            DB::table('protocol_signatures')->insert([
                'protocol_id' => $protocol->id, 'role' => $role, 'signer_name' => $name, 'signed_at' => $at,
                'method' => 'onscreen', 'hash' => str_repeat('a', 64), 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
        DB::table('diary_entries')->where('id', $signed->id)->update(['accepted_final_at' => '2030-03-01 10:10:00', 'accepted_final_by' => $worker->id, 'protocol_id' => $protocol->id]);
        $withoutProtocol = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $worker->id]);
        DB::table('diary_entries')->where('id', $withoutProtocol->id)->update(['accepted_final_at' => '2030-03-02 09:00:00', 'accepted_final_by' => $worker->id]);

        $migration->up();

        $this->assertSame('Max Kunde', DB::table('diary_entries')->where('id', $signed->id)->value('accepted_final_by'));
        $this->assertNull(DB::table('diary_entries')->where('id', $withoutProtocol->id)->value('accepted_final_by'));
        $this->assertSame('Max Kunde', DiaryEntry::query()->findOrFail($signed->id)->accepted_final_by);

        // Rückweg: der Name weicht wieder der Person, die die Abnahme erfasst hat (Ersteller des Protokolls).
        $migration->down();
        $this->assertSame((int) $worker->id, (int) DB::table('diary_entries')->where('id', $signed->id)->value('accepted_final_by'));
        $migration->up();
        $this->assertSame('Max Kunde', DB::table('diary_entries')->where('id', $signed->id)->value('accepted_final_by'));
    }
}
