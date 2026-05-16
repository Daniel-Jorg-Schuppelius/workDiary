<?php

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\User;
use App\Services\UI\DateRangeContext;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiarySearchExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        // Tagebuch-Listing wird jetzt vom globalen Range gefiltert; die
        // Factory erzeugt zufällige Daten ±1 Monat, daher hier auf das
        // ganze Jahr stellen, damit alle Test-Einträge sichtbar sind.
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_THIS_YEAR);
    }

    public function test_search_filters_entries_by_content_and_response(): void
    {
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

    public function test_csv_export_returns_csv_with_filtered_entries(): void
    {
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

    public function test_pdf_export_renders_printable_html(): void
    {
        $user = User::factory()->user()->create();
        $this->actingAs($user);

        DiaryEntry::factory()->for($user)->create(['content' => 'Druckbarer Eintrag']);

        $response = $this->get(route('diary.export.pdf'));
        $response->assertOk();
        $response->assertSee('Druckbarer Eintrag');
        $response->assertSee('window.print()', false);
    }
}
