<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiarySearchExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\Platform\User;
use App\Services\UI\DateRangeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiarySearchExportTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        // Tagebuch-Listing wird jetzt vom globalen Range gefiltert; die
        // Factory erzeugt zufällige Daten ±1 Monat, daher hier auf das
        // ganze Jahr stellen, damit alle Test-Einträge sichtbar sind.
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_THIS_YEAR);
    }

    public function test_search_filters_entries_by_content_and_response(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user);

        DiaryEntry::factory()->for($user)->create(['content' => 'Server gestürzt im Rechenzentrum']);
        DiaryEntry::factory()->for($user)->create(['content' => 'Kaffee gekocht', 'response' => 'Server läuft wieder']);
        DiaryEntry::factory()->for($user)->create(['content' => 'Belanglos', 'response' => 'Nichts']);

        $response = $this->get(route('diary.index', ['q' => 'server']));
        $response->assertOk();
        $response->assertSeeText('Server gestürzt');
        $response->assertSeeText('Kaffee gekocht'); // matched via response
        $response->assertDontSeeText('Belanglos');
    }

    public function test_csv_export_returns_csv_with_filtered_entries(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user);

        DiaryEntry::factory()->for($user)->create(['content' => 'Eintrag Alpha']);
        DiaryEntry::factory()->for($user)->create(['content' => 'Eintrag Beta']);

        $response = $this->get(route('diary.export.csv', ['q' => 'Alpha']));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('Eintrag Alpha', $body);
        $this->assertStringNotContainsString('Eintrag Beta', $body);
    }

    public function test_pdf_export_renders_printable_html(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user);

        DiaryEntry::factory()->for($user)->create(['content' => 'Druckbarer Eintrag']);

        $response = $this->get(route('diary.export.pdf'));
        $response->assertOk();
        $response->assertSee('Druckbarer Eintrag');
        $response->assertSee('window.print()', false);
    }

    /** Sicherheitsaudit 2026-09-17 (authz-diary-1): ohne diary.viewAny nur eigene Aufträge im Export und in der API. */
    public function test_exports_and_api_list_only_own_entries_without_view_any(): void {
        $user = User::factory()->user()->create();
        $colleague = User::factory()->user()->create(['organization_id' => $user->organization_id]);
        DiaryEntry::factory()->for($user)->create(['organization_id' => $user->organization_id, 'content' => 'Eigener Auftrag']);
        DiaryEntry::factory()->for($colleague)->create(['organization_id' => $user->organization_id, 'content' => 'Fremder Auftrag Geheimnis']);

        $this->actingAs($user);
        $csv = $this->get(route('diary.export.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Eigener Auftrag', $csv);
        $this->assertStringNotContainsString('Fremder Auftrag Geheimnis', $csv);

        $this->get(route('diary.export.pdf'))->assertOk()->assertDontSee('Fremder Auftrag Geheimnis');
    }
}
